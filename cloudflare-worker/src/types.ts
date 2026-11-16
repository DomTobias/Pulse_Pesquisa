// Contratos compartilhados (seção 13). Mantidos iguais no frontend, no Worker e no sincronizador.

export interface Env {
  SUPABASE_URL: string;
  SUPABASE_SERVICE_ROLE_KEY: string; // secret do Worker, nunca exposto
  TURNSTILE_SECRET_KEY: string;      // secret do Worker
  RATE_KV: KVNamespace;
  ALLOWED_ORIGINS: string;           // lista separada por vírgula
  RATE_LIMIT_PER_HOUR?: string;
  RATE_SALT_SECRET?: string;         // base do sal rotativo do hash de IP
}

export type Recorrencia = 'NENHUMA' | 'SEMANAL' | 'QUINZENAL' | 'MENSAL';
export type TipoPergunta =
  | 'CURTA' | 'LONGA' | 'NUMERO' | 'EMAIL' | 'TELEFONE' | 'DATA'
  | 'UNICA' | 'MULTIPLA' | 'LISTA' | 'ESCALA' | 'SIM_NAO' | 'INFO';

export interface Opcao { id: string; rotulo: string }

export interface Pergunta {
  id: string;
  tipo: TipoPergunta;
  titulo: string;
  orientacao: string;
  obrigatoria: boolean;
  minimo: number | null;
  maximo: number | null;
  rotulo_minimo: string;
  rotulo_maximo: string;
  aparencia: 'NEUTRA' | 'ZONAS';
  opcoes: Opcao[];
}

export interface Topico { id: string; titulo: string; descricao: string; perguntas: Pergunta[] }

/** Definição gravada pelo sincronizador em formularios_publicados.definicao */
export interface DefinicaoArmazenada {
  id: string;
  slug: string;
  versao: number;
  titulo: string;
  descricao: string;
  inicio_em: string | null;
  fim_em: string | null;
  recorrencia: Recorrencia;
  /** Rodízio: cada ciclo libera só um tópico (ciclo 1 = 1º tópico, ciclo 2 = 2º, ... e recomeça) */
  topico_por_ciclo?: boolean;
  fuso_horario: string;
  segmentado: boolean;
  exige_identificacao: boolean;
  permite_multiplas: boolean;
  formato: 'AUTO' | 'ETAPAS' | 'PAGINA_UNICA';
  cor: string;
  mensagem_apresentacao: string;
  mensagem_conclusao: string;
  capa: Record<string, unknown>;
  areas: { id: string; nome: string }[];
  gestores: { id: string; nome: string; area_id: string | null }[];
  topicos: Topico[];
}

export type StatusPublico = 'RASCUNHO' | 'AGENDADO' | 'PUBLICADO' | 'ENCERRADO';

export interface Ciclo { numero: number; inicio: string; fim: string; inicio_ms: number; fim_ms: number }

export interface RespostaEnviada {
  pergunta_id: string;
  valor?: string | number | boolean | null;
  opcoes?: string[];
}

export interface EnvioRecebido {
  envio_id?: string;
  formulario_id: string;
  versao: number;
  area_id?: string | null;
  gestor_id?: string | null;
  identificacao?: { nome?: string; email?: string } | null;
  respostas: RespostaEnviada[];
  turnstile_token: string;
}
