"""Conversão do payload da fila (contrato 13.2 + dados do Worker) para linhas do MySQL.

Função pura: recebe os mapas de UUID → id do MySQL e devolve o que deve ser gravado.
"""
from __future__ import annotations

import re
from dataclasses import dataclass, field
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
from typing import Any

UUID_RE = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$", re.I)
DATA_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
MAX_TEXTO = 2000


class PayloadInvalido(ValueError):
    """Erro permanente do registro (vai para registrar_erro)."""


@dataclass
class Mapas:
    """Dados do MySQL necessários para gravar respostas de UM formulário."""
    formulario_id: int
    exige_identificacao: bool
    # uuid da pergunta → (id, tipo)
    perguntas: dict[str, tuple[int, str]]
    # uuid da opção → (id, id da pergunta)
    opcoes: dict[str, tuple[int, int]]
    areas: dict[str, int] = field(default_factory=dict)
    gestores: dict[str, int] = field(default_factory=dict)


@dataclass
class RespostaDecomposta:
    uuid_externo: str
    resposta: dict[str, Any]
    itens: list[dict[str, Any]]


def para_utc(valor: Any) -> datetime:
    if not isinstance(valor, str) or not valor:
        raise PayloadInvalido("recebido_em ausente")
    try:
        dt = datetime.fromisoformat(valor.replace("Z", "+00:00"))
    except ValueError as exc:
        raise PayloadInvalido("recebido_em inválido") from exc
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(timezone.utc).replace(tzinfo=None, microsecond=0)


def _numero(valor: Any) -> Decimal:
    if isinstance(valor, bool):
        raise PayloadInvalido("valor numérico inválido")
    try:
        d = Decimal(str(valor))
    except (InvalidOperation, ValueError) as exc:
        raise PayloadInvalido("valor numérico inválido") from exc
    if not d.is_finite() or abs(d) >= Decimal("100000000"):
        raise PayloadInvalido("valor numérico fora do limite")
    return d.quantize(Decimal("0.01"))


def decompor_payload(payload: dict[str, Any], mapas: Mapas, agora: datetime | None = None) -> RespostaDecomposta:
    if not isinstance(payload, dict):
        raise PayloadInvalido("payload não é um objeto")
    envio_id = str(payload.get("envio_id") or "")
    if not UUID_RE.match(envio_id):
        raise PayloadInvalido("envio_id inválido")

    area_uuid, gestor_uuid = payload.get("area_id"), payload.get("gestor_id")
    area_id = gestor_id = None
    if area_uuid:
        if area_uuid not in mapas.areas:
            raise PayloadInvalido("área desconhecida")
        area_id = mapas.areas[area_uuid]
    if gestor_uuid:
        if gestor_uuid not in mapas.gestores:
            raise PayloadInvalido("gestor desconhecido")
        gestor_id = mapas.gestores[gestor_uuid]

    # Anonimato: identificação só é gravada quando o formulário exige
    identificacao = None
    ident = payload.get("identificacao")
    if mapas.exige_identificacao and isinstance(ident, dict):
        identificacao = {k: str(ident.get(k) or "")[:190] for k in ("nome", "email")}

    ciclo = payload.get("ciclo")
    if ciclo is not None and (not isinstance(ciclo, int) or isinstance(ciclo, bool) or ciclo < 1):
        raise PayloadInvalido("ciclo inválido")

    versao = payload.get("versao", 1)
    if not isinstance(versao, int) or isinstance(versao, bool) or versao < 1:
        raise PayloadInvalido("versão inválida")

    status = payload.get("status", "COMPLETA")
    if status not in ("COMPLETA", "PARCIAL"):
        raise PayloadInvalido("status inválido")

    agora = agora or datetime.now(timezone.utc).replace(tzinfo=None, microsecond=0)
    resposta = {
        "uuid_externo": envio_id,
        "formulario_id": mapas.formulario_id,
        "versao": versao,
        "ciclo": ciclo,
        "area_id": area_id,
        "gestor_id": gestor_id,
        "status": status,
        "identificacao": identificacao,
        "recebido_em": para_utc(payload.get("recebido_em")),
        "sincronizado_em": agora,
    }

    itens: list[dict[str, Any]] = []
    vistos: set[str] = set()
    respostas = payload.get("respostas")
    if not isinstance(respostas, list):
        raise PayloadInvalido("respostas ausentes")
    for r in respostas:
        if not isinstance(r, dict):
            raise PayloadInvalido("item de resposta inválido")
        p_uuid = r.get("pergunta_id")
        if p_uuid not in mapas.perguntas:
            raise PayloadInvalido(f"pergunta desconhecida: {p_uuid}")
        if p_uuid in vistos:
            raise PayloadInvalido(f"pergunta repetida: {p_uuid}")
        vistos.add(p_uuid)
        pergunta_id, tipo = mapas.perguntas[p_uuid]
        base = {"pergunta_id": pergunta_id, "valor_numerico": None, "valor_texto": None, "opcao_id": None}

        if tipo in ("UNICA", "LISTA", "MULTIPLA"):
            opcoes = r.get("opcoes")
            if not isinstance(opcoes, list) or not opcoes or (tipo != "MULTIPLA" and len(opcoes) != 1):
                raise PayloadInvalido(f"opções inválidas para {p_uuid}")
            for o_uuid in dict.fromkeys(opcoes):
                if o_uuid not in mapas.opcoes or mapas.opcoes[o_uuid][1] != pergunta_id:
                    raise PayloadInvalido(f"opção desconhecida: {o_uuid}")
                itens.append({**base, "opcao_id": mapas.opcoes[o_uuid][0]})
            continue

        valor = r.get("valor")
        if valor is None or valor == "":
            continue
        if tipo in ("ESCALA", "NUMERO"):
            itens.append({**base, "valor_numerico": _numero(valor)})
        elif tipo == "SIM_NAO":
            if valor not in ("SIM", "NAO"):
                raise PayloadInvalido("valor de sim/não inválido")
            itens.append({**base, "valor_texto": valor})
        elif tipo == "DATA":
            if not isinstance(valor, str) or not DATA_RE.match(valor):
                raise PayloadInvalido("data inválida")
            itens.append({**base, "valor_texto": valor})
        elif tipo == "INFO":
            continue
        else:
            itens.append({**base, "valor_texto": str(valor)[:MAX_TEXTO]})

    return RespostaDecomposta(envio_id, resposta, itens)
