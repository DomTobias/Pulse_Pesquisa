"""Fluxo A: busca respostas na fila do Supabase e grava no MySQL com idempotência."""
from __future__ import annotations

import logging
from dataclasses import dataclass

import pymysql

from app.config.settings import Config
from app.database.mysql import MySQL
from app.repositories.respostas_repo import RespostasRepo
from app.repositories.sync_repo import SyncRepo, agora_utc
from app.services.supabase import Supabase
from app.synchronization.payload import PayloadInvalido, decompor_payload
from app.utils.logger import log


@dataclass
class ResultadoLote:
    reservados: int = 0
    gravados: int = 0
    duplicados: int = 0
    erros: int = 0


class SincronizadorRespostas:
    def __init__(self, cfg: Config, db: MySQL, supa: Supabase, logger: logging.Logger):
        self.cfg, self.db, self.supa, self.logger = cfg, db, supa, logger
        self.repo = RespostasRepo(db)
        self.sync = SyncRepo(db)

    def executar(self) -> ResultadoLote:
        """Processa lotes até esvaziar a fila (ou até 20 lotes por ciclo)."""
        total = ResultadoLote()
        self.repo.limpar_cache()
        for _ in range(20):
            r = self._lote()
            total.reservados += r.reservados
            total.gravados += r.gravados
            total.duplicados += r.duplicados
            total.erros += r.erros
            if r.reservados < self.cfg.limite_registros_por_lote:
                break
        return total

    def _lote(self) -> ResultadoLote:
        res = ResultadoLote()
        registros = self.supa.reservar_envios(self.cfg.limite_registros_por_lote, self.cfg.tempo_reserva)
        res.reservados = len(registros)
        confirmar: list[str] = []
        for reg in registros:
            envio_id = str(reg.get("id"))
            try:
                payload = reg.get("payload") or {}
                if payload.get("envio_id") != envio_id:
                    raise PayloadInvalido("envio_id do payload difere do registro")
                with self.db.transacao() as cur:
                    if self.repo.ja_gravada(cur, envio_id):
                        res.duplicados += 1
                    else:
                        mapas = self.repo.mapas(cur, str(reg.get("formulario_id")))
                        dec = decompor_payload(payload, mapas)
                        self.repo.gravar(cur, dec, payload, str(reg.get("formulario_id")))
                        res.gravados += 1
                confirmar.append(envio_id)
            except pymysql.err.IntegrityError as exc:
                # UNIQUE uuid_externo: outro processo já gravou → idempotente
                if exc.args and exc.args[0] == 1062:
                    res.duplicados += 1
                    confirmar.append(envio_id)
                else:
                    self._falha(envio_id, f"Integridade: {exc.args[1] if len(exc.args) > 1 else exc}")
                    res.erros += 1
            except PayloadInvalido as exc:
                self._falha(envio_id, f"Payload inválido: {exc}")
                res.erros += 1
            except (pymysql.err.OperationalError, ConnectionError) as exc:
                # MySQL fora: não marca erro; a reserva expira e o registro volta para a fila.
                log(self.logger, logging.ERROR, "mysql_indisponivel", f"MySQL indisponível: {exc}", envio_id)
                self.db.fechar()
                break
            except Exception as exc:  # erro inesperado do registro
                self._falha(envio_id, f"{type(exc).__name__}: {exc}")
                res.erros += 1

        if confirmar:
            # Confirmar só depois do COMMIT. Se falhar aqui, a reserva expira e o UUID repetido é ignorado.
            self.supa.confirmar_envios(confirmar)
        if res.reservados:
            log(self.logger, logging.INFO, "lote_respostas",
                f"reservados={res.reservados} gravados={res.gravados} duplicados={res.duplicados} erros={res.erros}")
            self.sync.registrar_log("INFO", "lote_respostas",
                                    f"Lote: {res.gravados} gravadas, {res.duplicados} duplicadas, {res.erros} com erro")
        return res

    def _falha(self, envio_id: str, mensagem: str) -> None:
        log(self.logger, logging.WARNING, "erro_registro", mensagem, envio_id)
        self.sync.registrar_log("WARNING", "erro_registro", mensagem, envio_id)
        try:
            self.supa.registrar_erro(envio_id, mensagem)
        except Exception as exc:
            log(self.logger, logging.ERROR, "registrar_erro_falhou", str(exc), envio_id)

    # ---- comandos do gerenciador e estatísticas
    def executar_comandos(self) -> None:
        for cmd in self.sync.comandos_pendentes():
            try:
                if cmd["comando"] == "REPROCESSAR":
                    ok = self.supa.reprocessar_envio(cmd["alvo"])
                    self.sync.concluir_comando(cmd["id"], ok, "Registro devolvido para a fila" if ok else "Registro não está em ERRO")
                    self.sync.registrar_log("INFO", "reprocessar", "Reprocessamento solicitado pelo gerenciador", cmd["alvo"])
            except Exception as exc:
                self.sync.concluir_comando(cmd["id"], False, str(exc))

    def atualizar_estatisticas(self, resultado: ResultadoLote | None) -> None:
        est = self.supa.estatisticas()
        valores = {
            "fila_pendentes": est.get("pendentes", 0),
            "fila_processados": est.get("processados", 0),
            "fila_erros": len(est.get("erros") or []),
            "supabase_uso_bytes": est.get("uso_bytes", 0),
            "supabase_capacidade_bytes": est.get("capacidade_bytes", 0),
            "ultima_sincronizacao": agora_utc().isoformat(sep=" "),
        }
        if resultado is not None:
            valores["ultimo_lote"] = f"{resultado.gravados} gravadas, {resultado.duplicados} duplicadas, {resultado.erros} erros"
        self.sync.status(valores)
        self.sync.espelhar_erros(est.get("erros") or [])
