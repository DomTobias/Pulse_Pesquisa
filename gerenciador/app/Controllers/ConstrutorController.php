<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Configuracao;
use App\Models\Formulario;
use App\Services\ConstrutorService;
use App\Services\DefinicaoBuilder;
use App\Services\PublicacaoService;
use App\Services\RegraConstrutor;
use App\Services\UploadService;

final class ConstrutorController extends Controller
{
    public function editar(string $id): void
    {
        $u = $this->exigirLogin();
        $f = Formulario::buscar((int) $id);
        if (!$f) {
            Session::flash('erro', 'Formulário não encontrado.');
            $this->redirecionar('formularios');
        }
        $this->view('construtor/index', [
            'titulo' => 'Construtor de formulário', 'usuario' => $u, 'form' => $f,
            'construtor' => $this->dadosConstrutor($f),
            'somenteLeitura' => $u['perfil'] !== 'ADMIN',
            'totalRespostas' => Formulario::totalRespostas((int) $f['id']),
        ], 'admin');
    }

    /** Estado inicial do construtor (JSON embutido na página). */
    private function dadosConstrutor(array $f): array
    {
        $capa = $f['capa'] + ['tipo' => 'NENHUMA'];
        $imagens = [];
        foreach (['simbolo', 'foto', 'imagem'] as $k) {
            if (!empty($capa[$k]) && preg_match('/^local:(.+)$/', (string) $capa[$k], $m)) {
                $imagens[$capa[$k]] = url('uploads/' . $m[1]);
            }
        }
        $previa = DefinicaoBuilder::paraPrevia(DefinicaoBuilder::montar(['segmentado' => 1] + $f));
        return [
            'form' => [
                'titulo' => $f['titulo'], 'descricao' => (string) $f['descricao'], 'slug' => $f['slug'],
                'mensagem_apresentacao' => (string) $f['mensagem_apresentacao'], 'mensagem_conclusao' => (string) $f['mensagem_conclusao'],
                'inicio_em' => para_input_local($f['inicio_em']), 'fim_em' => para_input_local($f['fim_em']),
                'recorrencia' => $f['recorrencia'], 'topico_por_ciclo' => (int) $f['topico_por_ciclo'] === 1,
                'segmentado' => (int) $f['segmentado'] === 1,
                'exige_identificacao' => (int) $f['exige_identificacao'] === 1, 'permite_multiplas' => (int) $f['permite_multiplas'] === 1,
                'formato' => $f['formato'], 'cor' => $f['cor'], 'capa' => $capa,
                'topicos' => array_map(static fn ($t) => [
                    'uuid' => $t['uuid'], 'titulo' => $t['titulo'], 'descricao' => (string) $t['descricao'],
                    'perguntas' => array_map(static fn ($p) => [
                        'uuid' => $p['uuid'], 'tipo' => $p['tipo'], 'titulo' => $p['titulo'], 'orientacao' => (string) $p['orientacao'],
                        'obrigatoria' => (int) $p['obrigatoria'] === 1,
                        'minimo' => $p['minimo'] === null ? null : (float) $p['minimo'], 'maximo' => $p['maximo'] === null ? null : (float) $p['maximo'],
                        'rotulo_minimo' => (string) $p['rotulo_minimo'], 'rotulo_maximo' => (string) $p['rotulo_maximo'], 'aparencia' => $p['aparencia'],
                        'opcoes' => array_map(static fn ($o) => ['uuid' => $o['uuid'], 'rotulo' => $o['rotulo']], $p['opcoes']),
                    ], $t['perguntas']),
                ], Formulario::estrutura((int) $f['id'])),
            ],
            'imagens' => $imagens,
            'base_previa' => ['id' => $previa['id'], 'versao' => $previa['versao'], 'fuso_horario' => $previa['fuso_horario'],
                'areas' => $previa['areas'], 'gestores' => $previa['gestores']],
            'urls' => [
                'salvar' => url("formularios/{$f['id']}/salvar"),
                'imagem' => url("formularios/{$f['id']}/imagem"),
                'previa' => url("formularios/{$f['id']}/previa?embed=1"),
                'link_base' => rtrim((string) Configuracao::obter('dominio_links', ''), '/') . '/formulario/',
            ],
            'estado' => $f['estado'],
        ];
    }

    public function salvar(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $f = Formulario::buscar((int) $id);
        if (!$f) {
            $this->json(['ok' => false, 'mensagem' => 'Formulário não encontrado.'], 404);
        }
        try {
            $r = (new ConstrutorService())->salvar((int) $f['id'], $this->corpoJson());
        } catch (RegraConstrutor $e) {
            $this->json(['ok' => false, 'erros' => [$e->getMessage()]], 422);
        }
        if (!$r['ok']) {
            $this->json(['ok' => false, 'erros' => $r['erros']], 422);
        }
        $republicar = PublicacaoService::atualizarSeNoAr((int) $f['id']);
        Auditoria::registrar('SALVAR', 'formulario', (string) $f['id'], ['versao' => $r['versao']]);
        $novo = Formulario::buscar((int) $f['id']);
        $this->json([
            'ok' => true,
            'mensagem' => $republicar ? 'Alterações salvas. O sincronizador atualiza o link público em instantes.' : 'Alterações salvas.',
            'versao' => $r['versao'],
            'estado' => $novo['estado'],
            'dados' => $this->dadosConstrutor($novo)['form'],
        ]);
    }

    public function imagem(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        if (!Formulario::buscar((int) $id)) {
            $this->json(['ok' => false, 'mensagem' => 'Formulário não encontrado.'], 404);
        }
        $r = UploadService::salvar($_FILES['arquivo'] ?? []);
        if ($r['ok']) {
            Auditoria::registrar('ENVIAR_IMAGEM', 'formulario', $id, ['arquivo' => $r['ref']]);
        }
        $this->json($r, $r['ok'] ? 200 : 422);
    }
}
