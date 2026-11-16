"""Conexão com o MySQL local, com timeout e reconexão automática."""
from __future__ import annotations

import time
from contextlib import contextmanager
from typing import Any, Iterator

import pymysql
from pymysql.cursors import DictCursor

from app.config.settings import Config


class MySQL:
    def __init__(self, cfg: Config, tentativas: int = 3):
        self.cfg = cfg
        self.tentativas = tentativas
        self._con: pymysql.connections.Connection | None = None

    def _conectar(self) -> pymysql.connections.Connection:
        return pymysql.connect(
            host=self.cfg.mysql_host, port=self.cfg.mysql_port, user=self.cfg.mysql_user,
            password=self.cfg.mysql_password, database=self.cfg.mysql_database,
            charset="utf8mb4", cursorclass=DictCursor, autocommit=False,
            connect_timeout=10, read_timeout=30, write_timeout=30,
            init_command="SET time_zone = '+00:00'",
        )

    def conexao(self) -> pymysql.connections.Connection:
        ultimo_erro: Exception | None = None
        for n in range(self.tentativas):
            try:
                if self._con is None or not self._con.open:
                    self._con = self._conectar()
                else:
                    self._con.ping(reconnect=True)
                return self._con
            except pymysql.err.OperationalError as exc:
                ultimo_erro = exc
                self.fechar()
                time.sleep(min(2 ** n, 10))
        raise ConnectionError(f"MySQL indisponível: {ultimo_erro}")

    def fechar(self) -> None:
        if self._con is not None:
            try:
                self._con.close()
            except Exception:
                pass
        self._con = None

    @contextmanager
    def transacao(self) -> Iterator[DictCursor]:
        con = self.conexao()
        cur = con.cursor()
        try:
            yield cur
            con.commit()
        except Exception:
            con.rollback()
            raise
        finally:
            cur.close()

    def consultar(self, sql: str, params: tuple | dict | None = None) -> list[dict[str, Any]]:
        with self.transacao() as cur:
            cur.execute(sql, params)
            return list(cur.fetchall())

    def executar(self, sql: str, params: tuple | dict | None = None) -> int:
        with self.transacao() as cur:
            return cur.execute(sql, params)
