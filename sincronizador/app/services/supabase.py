"""Cliente HTTPS do Supabase (somente RPCs e Storage). A chave service_role fica só no .env."""
from __future__ import annotations

import time
from typing import Any

import requests

from app.config.settings import Config


class SupabaseErro(RuntimeError):
    def __init__(self, mensagem: str, status: int = 0):
        super().__init__(mensagem)
        self.status = status


class Supabase:
    def __init__(self, cfg: Config, timeout: float = 20.0, tentativas: int = 3):
        self.url = cfg.supabase_url
        self.bucket = cfg.supabase_bucket
        self.timeout = timeout
        self.tentativas = tentativas
        self._sessao = requests.Session()
        self._sessao.headers.update({
            "apikey": cfg.supabase_service_role_key,
            "Authorization": f"Bearer {cfg.supabase_service_role_key}",
        })

    def _pedir(self, metodo: str, caminho: str, **kw: Any) -> requests.Response:
        ultimo: Exception | None = None
        for n in range(self.tentativas):
            try:
                r = self._sessao.request(metodo, f"{self.url}{caminho}", timeout=self.timeout, **kw)
            except (requests.ConnectionError, requests.Timeout) as exc:
                ultimo = exc
                time.sleep(min(2 ** n, 8))
                continue
            if r.status_code >= 500 or r.status_code == 429:
                ultimo = SupabaseErro(f"Supabase respondeu {r.status_code}", r.status_code)
                time.sleep(min(2 ** n, 8))
                continue
            if r.status_code >= 400:
                raise SupabaseErro(f"Supabase respondeu {r.status_code}: {r.text[:300]}", r.status_code)
            return r
        raise SupabaseErro(f"Falha de comunicação com o Supabase: {type(ultimo).__name__}: {str(ultimo)[:200]}")

    def rpc(self, funcao: str, parametros: dict[str, Any] | None = None) -> Any:
        r = self._pedir("POST", f"/rest/v1/rpc/{funcao}", json=parametros or {},
                        headers={"Content-Type": "application/json", "Accept": "application/json"})
        return r.json() if r.content else None

    # ---- fila (Fluxo A)
    def reservar_envios(self, limite: int, segundos_reserva: int) -> list[dict[str, Any]]:
        return self.rpc("reservar_envios", {"limite": limite, "segundos_reserva": segundos_reserva}) or []

    def confirmar_envios(self, ids: list[str]) -> int:
        return int(self.rpc("confirmar_envios", {"ids": ids}) or 0) if ids else 0

    def registrar_erro(self, envio_id: str, mensagem: str) -> str:
        return str(self.rpc("registrar_erro", {"id": envio_id, "mensagem": mensagem[:1000]}))

    def reprocessar_envio(self, envio_id: str) -> bool:
        return bool(self.rpc("reprocessar_envio", {"id": envio_id}))

    def estatisticas(self) -> dict[str, Any]:
        return self.rpc("estatisticas_fila") or {}

    # ---- publicação (Fluxo B)
    def publicar_formulario(self, definicao: dict[str, Any]) -> dict[str, Any]:
        return self.rpc("publicar_formulario", {"definicao": definicao}) or {}

    def enviar_imagem(self, caminho: str, conteudo: bytes, tipo: str) -> str:
        self._pedir("POST", f"/storage/v1/object/{self.bucket}/{caminho}", data=conteudo,
                    headers={"Content-Type": tipo, "x-upsert": "true", "Cache-Control": "max-age=31536000"})
        return f"{self.url}/storage/v1/object/public/{self.bucket}/{caminho}"
