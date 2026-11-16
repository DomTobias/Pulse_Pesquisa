import unittest

from app.publicacao.publicador import ImagemInvalida, substituir_imagens
from app.utils.logger import sanitizar

SHA = "a" * 64


class SubstituirImagensTest(unittest.TestCase):
    def test_troca_referencias_locais(self):
        chamadas = []

        def resolver(sha, ext):
            chamadas.append((sha, ext))
            return f"https://x.supabase.co/storage/v1/object/public/pulse-capas/{sha}.{ext}"

        definicao = {"capa": {"foto": f"local:{SHA}.jpg", "simbolo": None, "titulo": "local de trabalho"}, "topicos": [{"titulo": "A"}]}
        saida = substituir_imagens(definicao, resolver)
        self.assertTrue(saida["capa"]["foto"].startswith("https://"))
        self.assertEqual(saida["capa"]["titulo"], "local de trabalho")
        self.assertEqual(chamadas, [(SHA, "jpg")])

    def test_rejeita_referencia_malformada(self):
        with self.assertRaises(ImagemInvalida):
            substituir_imagens({"foto": "local:../../etc/passwd"}, lambda s, e: "")


class SanitizarTest(unittest.TestCase):
    def test_remove_segredos(self):
        texto = 'Authorization: Bearer abc.def.ghi password=Segredo123 "token": "xyz" eyJhbGciOiJIUzI1NiJ9.eyJyb2xlIjoic2VydmljZSJ9.assinatura'
        s = sanitizar(texto)
        for segredo in ("abc.def.ghi", "Segredo123", "xyz", "eyJhbGciOiJIUzI1NiJ9"):
            self.assertNotIn(segredo, s)

    def test_remove_chaves_novas_do_supabase(self):
        s = sanitizar("falha com chave sb_secret_AbC123-_xyz na url")
        self.assertNotIn("AbC123", s)


if __name__ == "__main__":
    unittest.main()
