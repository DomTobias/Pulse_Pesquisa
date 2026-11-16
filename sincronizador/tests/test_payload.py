import unittest
from datetime import datetime
from decimal import Decimal

from app.synchronization.payload import Mapas, PayloadInvalido, decompor_payload

ENVIO = "6f1c2a3b-4d5e-4f60-8a1b-2c3d4e5f6a7b"
P_ESC, P_TXT, P_MULT, P_SN, P_DATA = (f"00000000-0000-4000-8000-00000000000{i}" for i in range(1, 6))
O1, O2, O_OUTRA = "10000000-0000-4000-8000-000000000001", "10000000-0000-4000-8000-000000000002", "10000000-0000-4000-8000-000000000003"
AREA, GESTOR = "20000000-0000-4000-8000-000000000001", "30000000-0000-4000-8000-000000000001"


def mapas(exige=False):
    return Mapas(
        formulario_id=7, exige_identificacao=exige,
        perguntas={P_ESC: (1, "ESCALA"), P_TXT: (2, "LONGA"), P_MULT: (3, "MULTIPLA"), P_SN: (4, "SIM_NAO"), P_DATA: (5, "DATA")},
        opcoes={O1: (11, 3), O2: (12, 3), O_OUTRA: (13, 99)},
        areas={AREA: 5}, gestores={GESTOR: 9},
    )


def payload(**kw):
    base = {
        "envio_id": ENVIO, "formulario_id": "x", "versao": 2, "area_id": AREA, "gestor_id": GESTOR,
        "respostas": [
            {"pergunta_id": P_ESC, "valor": 8},
            {"pergunta_id": P_TXT, "valor": "Mais treinamentos"},
            {"pergunta_id": P_MULT, "opcoes": [O1, O2]},
            {"pergunta_id": P_SN, "valor": "SIM"},
            {"pergunta_id": P_DATA, "valor": "2026-11-10"},
        ],
        "recebido_em": "2026-11-10T13:45:00.000Z", "ciclo": 3, "status": "COMPLETA",
    }
    base.update(kw)
    return base


class DecomporPayloadTest(unittest.TestCase):
    def test_decompoe_resposta_completa(self):
        d = decompor_payload(payload(), mapas(), agora=datetime(2026, 11, 10, 14, 0))
        self.assertEqual(d.uuid_externo, ENVIO)
        r = d.resposta
        self.assertEqual((r["formulario_id"], r["versao"], r["ciclo"], r["area_id"], r["gestor_id"]), (7, 2, 3, 5, 9))
        self.assertEqual(r["recebido_em"], datetime(2026, 11, 10, 13, 45))
        self.assertIsNone(r["identificacao"])
        self.assertEqual(len(d.itens), 6)  # múltipla vira duas linhas
        self.assertEqual(d.itens[0]["valor_numerico"], Decimal("8.00"))
        self.assertEqual([i["opcao_id"] for i in d.itens if i["pergunta_id"] == 3], [11, 12])
        self.assertEqual(d.itens[4]["valor_texto"], "SIM")

    def test_identificacao_descartada_em_formulario_anonimo(self):
        d = decompor_payload(payload(identificacao={"nome": "Ana", "email": "a@b.com"}), mapas(exige=False))
        self.assertIsNone(d.resposta["identificacao"])
        d = decompor_payload(payload(identificacao={"nome": "Ana", "email": "a@b.com"}), mapas(exige=True))
        self.assertEqual(d.resposta["identificacao"], {"nome": "Ana", "email": "a@b.com"})

    def test_rejeita_pergunta_desconhecida(self):
        with self.assertRaises(PayloadInvalido):
            decompor_payload(payload(respostas=[{"pergunta_id": "99999999-0000-4000-8000-000000000000", "valor": 1}]), mapas())

    def test_rejeita_opcao_de_outra_pergunta(self):
        with self.assertRaises(PayloadInvalido):
            decompor_payload(payload(respostas=[{"pergunta_id": P_MULT, "opcoes": [O_OUTRA]}]), mapas())

    def test_rejeita_area_e_uuid_invalidos(self):
        with self.assertRaises(PayloadInvalido):
            decompor_payload(payload(area_id="20000000-0000-4000-8000-0000000000ff"), mapas())
        with self.assertRaises(PayloadInvalido):
            decompor_payload(payload(envio_id="nao-e-uuid"), mapas())

    def test_rejeita_valores_invalidos(self):
        for resp in ({"pergunta_id": P_SN, "valor": "TALVEZ"}, {"pergunta_id": P_DATA, "valor": "10/11/2026"},
                     {"pergunta_id": P_ESC, "valor": "abc"}, {"pergunta_id": P_ESC, "valor": True}):
            with self.subTest(resp=resp), self.assertRaises(PayloadInvalido):
                decompor_payload(payload(respostas=[resp]), mapas())

    def test_sem_respostas_opcionais(self):
        d = decompor_payload(payload(respostas=[{"pergunta_id": P_TXT, "valor": ""}]), mapas())
        self.assertEqual(d.itens, [])

    def test_sem_segmentacao(self):
        d = decompor_payload(payload(area_id=None, gestor_id=None), mapas())
        self.assertIsNone(d.resposta["area_id"])
        self.assertIsNone(d.resposta["gestor_id"])


if __name__ == "__main__":
    unittest.main()
