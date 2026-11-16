-- Atualização de bancos criados antes de 16/11/2026 (o mysql.sql atual já inclui a coluna).
-- Uso: mysql -u root -p pulse < database\migracoes\2026-11-16-topico-por-ciclo.sql
-- Rodízio de tópicos: em cada ciclo só um tópico fica liberado (ciclo 1 = 1º tópico, ciclo 2 = 2º, ... e recomeça).
ALTER TABLE formularios
  ADD COLUMN topico_por_ciclo TINYINT(1) NOT NULL DEFAULT 0 AFTER recorrencia;
