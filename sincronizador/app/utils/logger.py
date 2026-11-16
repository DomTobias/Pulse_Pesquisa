"""Logs estruturados em JSON, com rotação diária e sem segredos."""
from __future__ import annotations

import json
import logging
import re
from datetime import datetime, timezone
from logging.handlers import TimedRotatingFileHandler
from pathlib import Path

# Remove tudo que se pareça com credenciais antes de gravar
_PADROES = [
    (re.compile(r"(Bearer\s+)[A-Za-z0-9._\-]+", re.I), r"\1***"),
    (re.compile(r"sb_(?:secret|publishable)_[A-Za-z0-9_\-]+"), "sb_***"),
    (re.compile(r"eyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]+"), "***"),
    (re.compile(r"((?:password|senha|token|apikey|api_key|service_role[a-z_]*|secret)[\"']?\s*[:=]\s*[\"']?)[^\s,\"'}]+", re.I), r"\1***"),
]


def sanitizar(texto: object) -> str:
    s = str(texto)
    for padrao, troca in _PADROES:
        s = padrao.sub(troca, s)
    return s[:4000]


class FormatoJson(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        dados = {
            "data": datetime.now(timezone.utc).isoformat(timespec="seconds"),
            "nivel": record.levelname,
            "evento": getattr(record, "evento", record.name),
            "mensagem": sanitizar(record.getMessage()),
        }
        uuid_envio = getattr(record, "uuid_envio", None)
        if uuid_envio:
            dados["uuid_envio"] = uuid_envio
        if record.exc_info:
            dados["erro"] = sanitizar(self.formatException(record.exc_info).splitlines()[-1])
        return json.dumps(dados, ensure_ascii=False)


def configurar_logger(log_dir: Path, retencao_dias: int = 30) -> logging.Logger:
    log_dir.mkdir(parents=True, exist_ok=True)
    logger = logging.getLogger("pulse")
    logger.setLevel(logging.INFO)
    logger.handlers.clear()
    arquivo = TimedRotatingFileHandler(log_dir / "sincronizador.log", when="midnight", backupCount=retencao_dias,
                                       encoding="utf-8", utc=False)
    arquivo.suffix = "%Y-%m-%d"
    arquivo.setFormatter(FormatoJson())
    console = logging.StreamHandler()
    console.setFormatter(FormatoJson())
    logger.addHandler(arquivo)
    logger.addHandler(console)
    logger.propagate = False
    return logger


def log(logger: logging.Logger, nivel: int, evento: str, mensagem: str, uuid_envio: str | None = None,
        exc_info: bool = False) -> None:
    logger.log(nivel, mensagem, extra={"evento": evento, "uuid_envio": uuid_envio}, exc_info=exc_info)
