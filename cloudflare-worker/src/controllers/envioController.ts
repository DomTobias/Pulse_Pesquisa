// POST /api/formularios/enviar — valida, confere o Turnstile, calcula o ciclo e grava na fila.
import type { EnvioRecebido, Env } from '../types.ts';
import { buscarPorId, enfileirar } from '../services/supabase.ts';
import { validarTurnstile } from '../services/turnstile.ts';
import { cicloEm, statusPublico, topicosDoCiclo } from '../services/ciclos.ts';
import { isUuid, validarEnvio } from '../validators/envio.ts';
import { dentroDoLimite } from '../middlewares/rateLimit.ts';
import { erro, json } from '../middlewares/http.ts';

const MAX_CORPO = 128 * 1024;

export async function receberEnvio(req: Request, env: Env, agora = Date.now()): Promise<Response> {
  const tipo = req.headers.get('Content-Type') ?? '';
  if (!tipo.includes('application/json')) return erro(env, req, 415, 'Envie os dados em JSON.');
  const tamanho = Number(req.headers.get('Content-Length') ?? '0');
  if (tamanho > MAX_CORPO) return erro(env, req, 413, 'Envio grande demais.');

  const ip = req.headers.get('CF-Connecting-IP');
  if (!(await dentroDoLimite(env, ip))) return erro(env, req, 429, 'Muitos envios em pouco tempo. Tente novamente mais tarde.');

  let envio: EnvioRecebido;
  try {
    const texto = await req.text();
    if (texto.length > MAX_CORPO) return erro(env, req, 413, 'Envio grande demais.');
    envio = JSON.parse(texto);
  } catch {
    return erro(env, req, 400, 'JSON inválido.');
  }
  if (!envio || typeof envio !== 'object') return erro(env, req, 400, 'JSON inválido.');
  if (!isUuid(envio.formulario_id)) return erro(env, req, 400, 'Formulário inválido.');
  if (envio.envio_id !== undefined && !isUuid(envio.envio_id)) return erro(env, req, 400, 'Identificador do envio inválido.');

  if (!(await validarTurnstile(env, envio.turnstile_token, ip))) {
    return erro(env, req, 403, 'Não foi possível confirmar que você não é um robô. Tente novamente.', { code: 'TURNSTILE' });
  }

  const linha = await buscarPorId(env, envio.formulario_id);
  if (!linha) return erro(env, req, 404, 'Formulário não encontrado.');
  const status = statusPublico(linha.estado, linha.inicio_em, linha.fim_em, agora);
  if (status !== 'PUBLICADO') {
    const msg = status === 'AGENDADO' ? 'Este formulário ainda não está aberto.' : status === 'ENCERRADO' ? 'Este formulário foi encerrado.' : 'Este formulário não está publicado.';
    return erro(env, req, 409, msg, { code: status });
  }

  const def = linha.definicao;
  const ciclo = cicloEm(linha.inicio_em, def.recorrencia, agora, def.fuso_horario || 'America/Cuiaba');
  // Com rodízio, só o tópico do ciclo atual é aceito (e só as obrigatórias dele são exigidas)
  const topicosAtuais = topicosDoCiclo(def, ciclo ? ciclo.numero : null);
  const v = validarEnvio({ ...def, topicos: topicosAtuais }, envio);
  if (!v.ok) {
    const doCiclo = new Set(topicosAtuais.flatMap((t) => t.perguntas.map((p) => p.id)));
    const deOutroTopico = def.topicos.some((t) => t.perguntas.some((p) => !doCiclo.has(p.id)
      && Array.isArray(envio.respostas) && envio.respostas.some((r) => r?.pergunta_id === p.id)));
    // Versão mudou ou o ciclo virou enquanto a pessoa respondia (respondeu o tópico da quinzena anterior)
    const desatualizado = Number(envio.versao) !== linha.versao || deOutroTopico;
    return erro(env, req, desatualizado ? 409 : 422,
      desatualizado ? 'O formulário foi atualizado. Recarregue a página para responder à versão atual.' : 'Algumas respostas precisam de atenção.',
      { code: desatualizado ? 'VERSAO_DESATUALIZADA' : 'VALIDACAO', erros: v.erros });
  }

  const id = envio.envio_id ?? crypto.randomUUID();

  // Payload da fila: sem IP, user agent, token do Turnstile ou identificador de dispositivo.
  const payload = {
    envio_id: id,
    formulario_id: def.id,
    versao: linha.versao,
    area_id: def.segmentado ? envio.area_id : null,
    gestor_id: def.segmentado ? envio.gestor_id : null,
    ...(def.exige_identificacao
      ? { identificacao: { nome: envio.identificacao!.nome!.trim(), email: envio.identificacao!.email!.trim().toLowerCase() } }
      : {}),
    respostas: v.respostas,
    recebido_em: new Date(agora).toISOString(),
    ciclo: ciclo ? ciclo.numero : null,
    status: 'COMPLETA',
  };

  await enfileirar(env, id, def.id, payload); // reenvio com o mesmo UUID não cria outro registro
  return json(env, req, { success: true, message: 'Formulário recebido com sucesso', id }, 200, { 'Cache-Control': 'no-store' });
}
