"""Estado da sincronização, espelho de erros, comandos do gerenciador e log no MySQL."""
from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from app.database.mysql import MySQL
from app.utils.logger import sanitizar


def agora_utc() -> datetime:
    return datetime.now(timezone.utc).replace(tzinfo=None, microsecond=0)


class SyncRepo:
    def __init__(self, db: MySQL):
        self.db = db

    def status(self, valores: dict[str, Any]) -> None:
        with self.db.transacao() as cur:
            cur.executemany(
                """INSERT INTO sync_status (chave, valor, atualizado_em) VALUES (%s, %s, %s)
                   ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = VALUES(atualizado_em)""",
                [(k, None if v is None else str(v)[:255], agora_utc()) for k, v in valores.items()],
            )

    def espelhar_erros(self, erros: list[dict[str, Any]]) -> None:
        """Substitui o espelho local pelos registros em ERRO informados pelo Supabase."""
        with self.db.transacao() as cur:
            cur.execute("DELETE FROM fila_erros")
            if erros:
                cur.executemany(
                    """INSERT INTO fila_erros (uuid_envio, formulario_id, tentativas, mensagem_erro, criado_em)
                       VALUES (%s, %s, %s, %s, %s)""",
                    [(e.get("id"), e.get("formulario_id"), int(e.get("tentativas") or 0),
                      sanitizar(e.get("mensagem_erro") or "")[:2000], _iso_para_utc(e.get("criado_em"))) for e in erros],
                )

    def comandos_pendentes(self) -> list[dict[str, Any]]:
        return self.db.consultar("SELECT id, comando, alvo FROM comandos_sync WHERE status = 'PENDENTE' ORDER BY id LIMIT 50")

    def concluir_comando(self, comando_id: int, ok: bool, mensagem: str) -> None:
        self.db.executar("UPDATE comandos_sync SET status = %s, mensagem = %s, executado_em = %s WHERE id = %s",
                         ("EXECUTADO" if ok else "ERRO", sanitizar(mensagem)[:1000], agora_utc(), comando_id))

    def registrar_log(self, nivel: str, evento: str, mensagem: str, uuid_envio: str | None = None) -> None:
        try:
            self.db.executar("INSERT INTO sincronizacao_log (nivel, evento, uuid_envio, mensagem, criado_em) VALUES (%s, %s, %s, %s, %s)",
                             (nivel, evento[:60], uuid_envio, sanitizar(mensagem), agora_utc()))
        except Exception:
            pass  # o log em arquivo continua valendo se o MySQL estiver fora

    def limpar_logs_antigos(self, dias: int = 90) -> None:
        self.db.executar("DELETE FROM sincronizacao_log WHERE criado_em < (UTC_TIMESTAMP() - INTERVAL %s DAY)", (dias,))


def _iso_para_utc(valor: Any) -> datetime | None:
    if not valor:
        return None
    try:
        dt = datetime.fromisoformat(str(valor).replace("Z", "+00:00"))
    except ValueError:
        return None
    if dt.tzinfo:
        dt = dt.astimezone(timezone.utc).replace(tzinfo=None)
    return dt.replace(microsecond=0)
