"""Fluxo B: envia as definições publicadas no gerenciador para o Supabase."""
from __future__ import annotations

import hashlib
import logging
import re
from pathlib import Path
from typing import Any, Callable

from app.config.settings import Config
from app.database.mysql import MySQL
from app.repositories.publicacoes_repo import PublicacoesRepo
from app.repositories.sync_repo import SyncRepo, agora_utc
from app.services.supabase import Supabase
from app.utils.logger import log

# Referências a imagens locais gravadas pelo gerenciador: "local:<sha256>.<ext>"
REF_LOCAL = re.compile(r"^local:([a-f0-9]{64})\.(png|jpg|jpeg|webp|svg)$")
TIPOS = {"png": "image/png", "jpg": "image/jpeg", "jpeg": "image/jpeg", "webp": "image/webp", "svg": "image/svg+xml"}
LIMITE_IMAGEM = 1_572_864  # 1,5 MB (mesmo limite do bucket)


class ImagemInvalida(ValueError):
    pass


def substituir_imagens(valor: Any, resolver: Callable[[str, str], str]) -> Any:
    """Percorre a definição e troca cada "local:..." pela URL pública devolvida por resolver(sha, ext)."""
    if isinstance(valor, dict):
        return {k: substituir_imagens(v, resolver) for k, v in valor.items()}
    if isinstance(valor, list):
        return [substituir_imagens(v, resolver) for v in valor]
    if isinstance(valor, str) and valor.startswith("local:"):
        m = REF_LOCAL.match(valor)
        if not m:
            raise ImagemInvalida(f"referência de imagem inválida: {valor[:80]}")
        return resolver(m.group(1), m.group(2))
    return valor


class Publicador:
    def __init__(self, cfg: Config, db: MySQL, supa: Supabase, logger: logging.Logger):
        self.cfg, self.db, self.supa, self.logger = cfg, db, supa, logger
        self.repo = PublicacoesRepo(db)
        self.sync = SyncRepo(db)

    def _resolver_imagem(self, sha: str, ext: str) -> str:
        url = self.repo.url_imagem(sha)
        if url:
            return url
        arquivo = self.cfg.uploads_dir / f"{sha}.{ext}"
        if not arquivo.is_file():
            raise ImagemInvalida(f"imagem não encontrada em {self.cfg.uploads_dir.name}: {sha[:12]}…")
        conteudo = arquivo.read_bytes()
        if len(conteudo) > LIMITE_IMAGEM:
            raise ImagemInvalida("imagem maior que 1,5 MB")
        if hashlib.sha256(conteudo).hexdigest() != sha:
            raise ImagemInvalida("conteúdo da imagem não confere com o hash")
        url = self.supa.enviar_imagem(f"{sha}.{ext}", conteudo, TIPOS[ext])
        self.repo.salvar_imagem(sha, url)
        log(self.logger, logging.INFO, "imagem_enviada", f"Imagem {sha[:12]}… enviada ao Storage")
        return url

    def executar(self) -> int:
        self.repo.descartar_superadas()
        enviados = 0
        for pub in self.repo.pendentes():
            definicao: dict[str, Any] = pub["definicao"]
            try:
                definicao = substituir_imagens(definicao, self._resolver_imagem)
                resultado = self.supa.publicar_formulario(definicao)
                self.repo.marcar_enviada(pub["id"])
                enviados += 1
                aplicado = resultado.get("aplicado", True)
                msg = (f"Formulário {definicao.get('slug')} v{definicao.get('versao')} ({pub['motivo']}) "
                       f"{'publicado' if aplicado else 'ignorado: versão mais nova já publicada'}")
                log(self.logger, logging.INFO, "publicacao", msg)
                self.sync.registrar_log("INFO", "publicacao", msg)
            except Exception as exc:
                status = self.repo.marcar_falha(pub["id"], f"{type(exc).__name__}: {exc}", self.cfg.max_tentativas)
                log(self.logger, logging.ERROR, "publicacao_falhou", f"Publicação {pub['id']} falhou ({status}): {exc}")
                self.sync.registrar_log("ERROR", "publicacao_falhou", f"Publicação {pub['id']}: {exc}")
        if enviados:
            self.sync.status({"ultima_publicacao": agora_utc().isoformat(sep=" ")})
        return enviados
