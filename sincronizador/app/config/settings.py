"""Configuração carregada do arquivo .env (nunca do código-fonte)."""
from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[2]


class ConfigInvalida(RuntimeError):
    pass


def _carregar_env(caminho: Path) -> None:
    """Carrega o .env. Usa python-dotenv quando disponível; senão, um leitor simples."""
    if not caminho.exists():
        return
    try:
        from dotenv import load_dotenv
        load_dotenv(caminho, override=False)
        return
    except ImportError:
        pass
    for linha in caminho.read_text(encoding="utf-8").splitlines():
        linha = linha.strip()
        if not linha or linha.startswith("#") or "=" not in linha:
            continue
        chave, valor = linha.split("=", 1)
        os.environ.setdefault(chave.strip(), valor.strip().strip('"').strip("'"))


def _int(nome: str, padrao: int, minimo: int = 1) -> int:
    bruto = os.getenv(nome, str(padrao)).strip()
    try:
        valor = int(bruto)
    except ValueError as exc:
        raise ConfigInvalida(f"{nome} deve ser um número inteiro") from exc
    if valor < minimo:
        raise ConfigInvalida(f"{nome} deve ser maior ou igual a {minimo}")
    return valor


@dataclass(frozen=True)
class Config:
    supabase_url: str
    supabase_service_role_key: str
    supabase_bucket: str
    mysql_host: str
    mysql_port: int
    mysql_database: str
    mysql_user: str
    mysql_password: str
    intervalo_sincronizacao: int
    intervalo_publicacao: int
    tempo_reserva: int
    limite_registros_por_lote: int
    max_tentativas: int
    uploads_dir: Path
    log_dir: Path
    log_retencao_dias: int

    def __repr__(self) -> str:  # nunca imprime segredos
        return f"Config(supabase_url={self.supabase_url!r}, mysql={self.mysql_user}@{self.mysql_host}:{self.mysql_port}/{self.mysql_database})"


def _caminho(valor: str) -> Path:
    p = Path(valor)
    return p if p.is_absolute() else (BASE_DIR / p).resolve()


def carregar_config(arquivo_env: Path | None = None) -> Config:
    _carregar_env(arquivo_env or BASE_DIR / ".env")
    faltando = [n for n in ("SUPABASE_URL", "SUPABASE_SERVICE_ROLE_KEY", "MYSQL_HOST", "MYSQL_DATABASE", "MYSQL_USER")
                if not os.getenv(n, "").strip()]
    if faltando:
        raise ConfigInvalida("Variáveis obrigatórias ausentes no .env: " + ", ".join(faltando))
    url = os.environ["SUPABASE_URL"].strip().rstrip("/")
    if not url.startswith("https://"):
        raise ConfigInvalida("SUPABASE_URL deve usar https://")
    return Config(
        supabase_url=url,
        supabase_service_role_key=os.environ["SUPABASE_SERVICE_ROLE_KEY"].strip(),
        supabase_bucket=os.getenv("SUPABASE_BUCKET", "pulse-capas").strip() or "pulse-capas",
        mysql_host=os.environ["MYSQL_HOST"].strip(),
        mysql_port=_int("MYSQL_PORT", 3306),
        mysql_database=os.environ["MYSQL_DATABASE"].strip(),
        mysql_user=os.environ["MYSQL_USER"].strip(),
        mysql_password=os.getenv("MYSQL_PASSWORD", ""),
        intervalo_sincronizacao=_int("INTERVALO_SINCRONIZACAO", 30, 5),
        intervalo_publicacao=_int("INTERVALO_PUBLICACAO", 20, 5),
        tempo_reserva=_int("TEMPO_RESERVA", 300, 30),
        limite_registros_por_lote=_int("LIMITE_REGISTROS_POR_LOTE", 50),
        max_tentativas=_int("MAX_TENTATIVAS", 5),
        uploads_dir=_caminho(os.getenv("UPLOADS_DIR", "../gerenciador/storage/uploads")),
        log_dir=_caminho(os.getenv("LOG_DIR", "logs")),
        log_retencao_dias=_int("LOG_RETENCAO_DIAS", 30),
    )
