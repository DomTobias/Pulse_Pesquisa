"""Sincronizador Pulse — serviço local (Windows via NSSM).

Uso:
  python main.py              executa continuamente (modo serviço)
  python main.py --uma-vez    executa um ciclo dos dois fluxos e sai
  python main.py --verificar  testa as conexões com MySQL e Supabase e sai
"""
from __future__ import annotations

import argparse
import logging
import signal
import sys
import threading
import time

from app.config.settings import ConfigInvalida, carregar_config
from app.database.mysql import MySQL
from app.publicacao.publicador import Publicador
from app.repositories.sync_repo import SyncRepo, agora_utc
from app.services.supabase import Supabase
from app.synchronization.respostas import SincronizadorRespostas
from app.utils.logger import configurar_logger, log

parar = threading.Event()


def _sinal(*_):
    parar.set()


def verificar(db: MySQL, supa: Supabase, logger: logging.Logger) -> bool:
    ok = True
    try:
        db.consultar("SELECT 1 AS ok")
        log(logger, logging.INFO, "verificacao", "MySQL: conexão OK")
    except Exception as exc:
        ok = False
        log(logger, logging.ERROR, "verificacao", f"MySQL: falha — {exc}")
    try:
        est = supa.estatisticas()
        log(logger, logging.INFO, "verificacao", f"Supabase: conexão OK (pendentes={est.get('pendentes')})")
    except Exception as exc:
        ok = False
        log(logger, logging.ERROR, "verificacao", f"Supabase: falha — {exc}")
    return ok


def ciclo_respostas(sinc: SincronizadorRespostas, sync: SyncRepo, logger: logging.Logger) -> None:
    try:
        sinc.executar_comandos()
        resultado = sinc.executar()
        sinc.atualizar_estatisticas(resultado)
        sync.status({"servico_heartbeat": agora_utc().isoformat(sep=" "), "ultimo_erro": ""})
    except Exception as exc:
        log(logger, logging.ERROR, "ciclo_respostas", f"Falha no ciclo de respostas: {exc}", exc_info=True)
        try:
            sync.status({"servico_heartbeat": agora_utc().isoformat(sep=" "), "ultimo_erro": f"{type(exc).__name__}: {exc}"[:250]})
        except Exception:
            pass


def ciclo_publicacao(pub: Publicador, logger: logging.Logger) -> None:
    try:
        pub.executar()
    except Exception as exc:
        log(logger, logging.ERROR, "ciclo_publicacao", f"Falha no ciclo de publicação: {exc}", exc_info=True)


def main() -> int:
    parser = argparse.ArgumentParser(description="Sincronizador Pulse")
    parser.add_argument("--uma-vez", action="store_true")
    parser.add_argument("--verificar", action="store_true")
    args = parser.parse_args()

    try:
        cfg = carregar_config()
    except ConfigInvalida as exc:
        print(f"Configuração inválida: {exc}", file=sys.stderr)
        return 2

    logger = configurar_logger(cfg.log_dir, cfg.log_retencao_dias)
    db = MySQL(cfg)
    supa = Supabase(cfg)
    sync = SyncRepo(db)
    sinc = SincronizadorRespostas(cfg, db, supa, logger)
    pub = Publicador(cfg, db, supa, logger)

    if args.verificar:
        return 0 if verificar(db, supa, logger) else 1

    if args.uma_vez:
        ciclo_publicacao(pub, logger)
        ciclo_respostas(sinc, sync, logger)
        db.fechar()
        return 0

    signal.signal(signal.SIGINT, _sinal)
    signal.signal(signal.SIGTERM, _sinal)
    if hasattr(signal, "SIGBREAK"):
        signal.signal(signal.SIGBREAK, _sinal)  # NSSM envia Ctrl+Break ao parar o serviço

    log(logger, logging.INFO, "inicio", f"Sincronizador iniciado: respostas a cada {cfg.intervalo_sincronizacao}s, "
                                        f"publicações a cada {cfg.intervalo_publicacao}s")
    sync.registrar_log("INFO", "inicio", "Serviço de sincronização iniciado")
    proximo_resp = proximo_pub = 0.0
    proxima_limpeza = 0.0
    while not parar.is_set():
        agora = time.monotonic()
        if agora >= proximo_pub:
            ciclo_publicacao(pub, logger)
            proximo_pub = agora + cfg.intervalo_publicacao
        if agora >= proximo_resp:
            ciclo_respostas(sinc, sync, logger)
            proximo_resp = agora + cfg.intervalo_sincronizacao
        if agora >= proxima_limpeza:
            try:
                sync.limpar_logs_antigos()
            except Exception:
                pass
            proxima_limpeza = agora + 86400
        parar.wait(max(1.0, min(proximo_resp, proximo_pub) - time.monotonic()))

    log(logger, logging.INFO, "fim", "Sincronizador encerrado")
    sync.registrar_log("INFO", "fim", "Serviço de sincronização encerrado")
    db.fechar()
    return 0


if __name__ == "__main__":
    sys.exit(main())
