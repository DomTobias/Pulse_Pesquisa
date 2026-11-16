"""Fila local de publicações (Fluxo B) e cache de imagens enviadas."""
from __future__ import annotations

import json
from typing import Any

from app.database.mysql import MySQL
from app.repositories.sync_repo import agora_utc
from app.utils.logger import sanitizar


class PublicacoesRepo:
    def __init__(self, db: MySQL):
        self.db = db

    def pendentes(self, limite: int = 20) -> list[dict[str, Any]]:
        linhas = self.db.consultar(
            """SELECT p.id, p.formulario_id, p.versao, p.motivo, p.definicao, p.tentativas
                 FROM publicacoes p WHERE p.status = 'PENDENTE' ORDER BY p.id LIMIT %s""", (limite,))
        for l in linhas:
            if isinstance(l["definicao"], (str, bytes)):
                l["definicao"] = json.loads(l["definicao"])
        return linhas

    def marcar_enviada(self, pub_id: int) -> None:
        self.db.executar("UPDATE publicacoes SET status = 'ENVIADA', enviado_em = %s, mensagem_erro = NULL WHERE id = %s",
                         (agora_utc(), pub_id))

    def marcar_falha(self, pub_id: int, mensagem: str, max_tentativas: int) -> str:
        with self.db.transacao() as cur:
            cur.execute("SELECT tentativas FROM publicacoes WHERE id = %s FOR UPDATE", (pub_id,))
            linha = cur.fetchone() or {"tentativas": 0}
            tentativas = int(linha["tentativas"]) + 1
            status = "ERRO" if tentativas >= max_tentativas else "PENDENTE"
            cur.execute("UPDATE publicacoes SET tentativas = %s, status = %s, mensagem_erro = %s WHERE id = %s",
                        (tentativas, status, sanitizar(mensagem)[:2000], pub_id))
            return status

    def descartar_superadas(self) -> int:
        """Publicações pendentes de versão antiga são substituídas pela mais nova do mesmo formulário."""
        return self.db.executar(
            """UPDATE publicacoes p JOIN (SELECT formulario_id, MAX(id) AS ultimo FROM publicacoes
                                          WHERE status = 'PENDENTE' GROUP BY formulario_id) u
                   ON u.formulario_id = p.formulario_id
                  SET p.status = 'ENVIADA', p.enviado_em = %s, p.mensagem_erro = 'Substituída por publicação mais recente'
                WHERE p.status = 'PENDENTE' AND p.id < u.ultimo""", (agora_utc(),))

    def url_imagem(self, sha256: str) -> str | None:
        r = self.db.consultar("SELECT url FROM imagens_publicadas WHERE sha256 = %s", (sha256,))
        return r[0]["url"] if r else None

    def salvar_imagem(self, sha256: str, url: str) -> None:
        self.db.executar("INSERT INTO imagens_publicadas (sha256, url) VALUES (%s, %s) ON DUPLICATE KEY UPDATE url = VALUES(url)",
                         (sha256, url))
