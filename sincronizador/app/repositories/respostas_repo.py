"""Acesso ao MySQL para gravar respostas (Fluxo A)."""
from __future__ import annotations

import json
from typing import Any

from app.database.mysql import MySQL
from app.synchronization.payload import Mapas, PayloadInvalido, RespostaDecomposta


class RespostasRepo:
    def __init__(self, db: MySQL):
        self.db = db
        self._areas: dict[str, int] | None = None
        self._gestores: dict[str, int] | None = None

    def limpar_cache(self) -> None:
        self._areas = self._gestores = None

    def ja_gravada(self, cur, uuid_externo: str) -> bool:
        cur.execute("SELECT 1 FROM respostas WHERE uuid_externo = %s LIMIT 1", (uuid_externo,))
        return cur.fetchone() is not None

    def mapas(self, cur, formulario_uuid: str) -> Mapas:
        cur.execute("SELECT id, exige_identificacao FROM formularios WHERE uuid = %s", (formulario_uuid,))
        f = cur.fetchone()
        if not f:
            raise PayloadInvalido(f"formulário desconhecido: {formulario_uuid}")
        cur.execute("""SELECT p.uuid, p.id, p.tipo FROM perguntas p JOIN topicos t ON t.id = p.topico_id
                        WHERE t.formulario_id = %s""", (f["id"],))
        perguntas = {r["uuid"]: (r["id"], r["tipo"]) for r in cur.fetchall()}
        cur.execute("""SELECT o.uuid, o.id, o.pergunta_id FROM opcoes o JOIN perguntas p ON p.id = o.pergunta_id
                        JOIN topicos t ON t.id = p.topico_id WHERE t.formulario_id = %s""", (f["id"],))
        opcoes = {r["uuid"]: (r["id"], r["pergunta_id"]) for r in cur.fetchall()}
        if self._areas is None:
            cur.execute("SELECT uuid, id FROM areas")
            self._areas = {r["uuid"]: r["id"] for r in cur.fetchall()}
            cur.execute("SELECT uuid, id FROM gestores")
            self._gestores = {r["uuid"]: r["id"] for r in cur.fetchall()}
        return Mapas(f["id"], bool(f["exige_identificacao"]), perguntas, opcoes, self._areas, self._gestores or {})

    def gravar(self, cur, dec: RespostaDecomposta, payload_bruto: dict[str, Any], formulario_uuid: str) -> None:
        r = dec.resposta
        cur.execute(
            """INSERT INTO formularios_recebidos (uuid_externo, formulario_id, dados, recebido_em, sincronizado_em)
               VALUES (%s, %s, %s, %s, %s)""",
            (dec.uuid_externo, formulario_uuid, json.dumps(payload_bruto, ensure_ascii=False), r["recebido_em"], r["sincronizado_em"]),
        )
        cur.execute(
            """INSERT INTO respostas (uuid_externo, formulario_id, versao, ciclo, area_id, gestor_id, status,
                                      identificacao, recebido_em, sincronizado_em)
               VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)""",
            (r["uuid_externo"], r["formulario_id"], r["versao"], r["ciclo"], r["area_id"], r["gestor_id"], r["status"],
             json.dumps(r["identificacao"], ensure_ascii=False) if r["identificacao"] else None,
             r["recebido_em"], r["sincronizado_em"]),
        )
        resposta_id = cur.lastrowid
        if dec.itens:
            cur.executemany(
                """INSERT INTO respostas_itens (resposta_id, pergunta_id, valor_numerico, valor_texto, opcao_id)
                   VALUES (%s, %s, %s, %s, %s)""",
                [(resposta_id, i["pergunta_id"], i["valor_numerico"], i["valor_texto"], i["opcao_id"]) for i in dec.itens],
            )
