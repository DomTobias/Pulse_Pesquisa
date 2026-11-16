<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Cadastro;
use App\Models\Formulario;

/**
 * Monta a definição pública do formulário (contrato 13.1) a partir do MySQL.
 * É a mesma estrutura usada na publicação (Fluxo B) e na prévia local.
 * Nunca inclui respostas, contagens, usuários ou qualquer dado interno.
 */
final class DefinicaoBuilder
{
    /**
     * @param string $estado ATIVO | ENCERRADO | DESPUBLICADO (estado em formularios_publicados)
     */
    public static function montar(array $form, string $estado = 'ATIVO'): array
    {
        $areas = $gestores = [];
        if ((int) $form['segmentado'] === 1) {
            $areasAtivas = Cadastro::areas(true);
            $uuidArea = array_column($areasAtivas, 'uuid', 'id');
            $areas = array_map(static fn ($a) => ['id' => $a['uuid'], 'nome' => $a['nome']], $areasAtivas);
            foreach (Cadastro::gestores(true) as $g) {
                $gestores[] = ['id' => $g['uuid'], 'nome' => $g['nome'], 'area_id' => $g['area_id'] ? ($uuidArea[$g['area_id']] ?? null) : null];
            }
        }

        $topicos = [];
        foreach (Formulario::estrutura((int) $form['id']) as $t) {
            $topicos[] = [
                'id' => $t['uuid'],
                'titulo' => $t['titulo'],
                'descricao' => (string) $t['descricao'],
                'perguntas' => array_map(static fn ($p) => [
                    'id' => $p['uuid'],
                    'tipo' => $p['tipo'],
                    'titulo' => $p['titulo'],
                    'orientacao' => (string) $p['orientacao'],
                    'obrigatoria' => (int) $p['obrigatoria'] === 1,
                    'minimo' => $p['minimo'] === null ? null : (float) $p['minimo'],
                    'maximo' => $p['maximo'] === null ? null : (float) $p['maximo'],
                    'rotulo_minimo' => (string) $p['rotulo_minimo'],
                    'rotulo_maximo' => (string) $p['rotulo_maximo'],
                    'aparencia' => $p['aparencia'],
                    'opcoes' => array_map(static fn ($o) => ['id' => $o['uuid'], 'rotulo' => $o['rotulo']], $p['opcoes']),
                ], $t['perguntas']),
            ];
        }

        $capa = is_array($form['capa']) ? $form['capa'] : (json_decode((string) $form['capa'], true) ?: []);
        $capa += ['tipo' => 'NENHUMA'];

        return [
            'id' => $form['uuid'],
            'slug' => $form['slug'],
            'versao' => (int) $form['versao'],
            'estado' => $estado,
            'titulo' => $form['titulo'],
            'descricao' => (string) $form['descricao'],
            'inicio_em' => iso_utc($form['inicio_em']),
            'fim_em' => iso_utc($form['fim_em']),
            'recorrencia' => $form['recorrencia'],
            'topico_por_ciclo' => (int) ($form['topico_por_ciclo'] ?? 0) === 1 && $form['recorrencia'] !== 'NENHUMA',
            'fuso_horario' => App::fuso()->getName(),
            'segmentado' => (int) $form['segmentado'] === 1,
            'exige_identificacao' => (int) $form['exige_identificacao'] === 1,
            'permite_multiplas' => (int) $form['permite_multiplas'] === 1,
            'formato' => $form['formato'],
            'cor' => $form['cor'],
            'mensagem_apresentacao' => (string) $form['mensagem_apresentacao'],
            'mensagem_conclusao' => (string) $form['mensagem_conclusao'],
            'capa' => $capa,
            'areas' => $areas,
            'gestores' => $gestores,
            'topicos' => $topicos,
        ];
    }

    /** Versão para a prévia local: formato da resposta do Worker, sempre "PUBLICADO", imagens servidas pelo gerenciador. */
    public static function paraPrevia(array $definicao): array
    {
        unset($definicao['estado']);
        $definicao['status'] = 'PUBLICADO';
        $definicao['ciclo_atual'] = null;
        array_walk_recursive($definicao, static function (&$v) {
            if (is_string($v) && preg_match('/^local:([a-f0-9]{64}\.(png|jpg|jpeg|webp|svg))$/', $v, $m)) {
                $v = url('uploads/' . $m[1]);
            }
        });
        return $definicao;
    }
}
