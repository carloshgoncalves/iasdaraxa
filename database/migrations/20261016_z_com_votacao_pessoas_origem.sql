ALTER TABLE com_votacao_pessoas
  ADD COLUMN origem ENUM('admin','indicacao') NOT NULL DEFAULT 'admin' AFTER papel;

INSERT INTO com_votacao_pessoas
  (comissao_cargo_id, pessoa_id, votos, papel, origem, created_at, updated_at)
SELECT DISTINCT v.comissao_cargo_id, i.pessoa_id, 0, 'candidato', 'indicacao', NOW(), NOW()
FROM com_indicacoes i
INNER JOIN com_voto v ON v.id = i.voto_id
ON DUPLICATE KEY UPDATE updated_at = com_votacao_pessoas.updated_at;
