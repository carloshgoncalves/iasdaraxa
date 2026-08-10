CREATE TABLE obra_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  responsavel VARCHAR(160) NULL DEFAULT NULL,
  ordem INT(11) NOT NULL DEFAULT 0,
  status ENUM('Nao iniciada','Em andamento','Bloqueada','Pausada','Concluida','Cancelada') NOT NULL DEFAULT 'Nao iniciada',
  progresso TINYINT UNSIGNED NOT NULL DEFAULT 0,
  duracao_prevista_dias SMALLINT UNSIGNED NULL DEFAULT NULL,
  data_inicio_prevista DATE NULL DEFAULT NULL,
  data_fim_prevista DATE NULL DEFAULT NULL,
  data_inicio_real DATE NULL DEFAULT NULL,
  data_fim_real DATE NULL DEFAULT NULL,
  custo_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  custo_real DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  marco TINYINT(1) NOT NULL DEFAULT 0,
  legacy_phase_id BIGINT UNSIGNED NULL DEFAULT NULL,
  legacy_step_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_itens_obra_parent_ordem_index (obra_id, parent_id, ordem),
  KEY obra_itens_parent_id_foreign (parent_id),
  CONSTRAINT obra_itens_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_itens_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO obra_itens (
  obra_id, parent_id, nome, descricao, responsavel, ordem, status, progresso,
  duracao_prevista_dias, data_inicio_prevista, data_fim_prevista, data_inicio_real,
  data_fim_real, custo_previsto, custo_real, marco, legacy_phase_id, created_at, updated_at
)
SELECT
  obra_id, NULL, nome, descricao, NULL, ordem, status, progresso,
  NULL, data_inicio_prevista, data_fim_prevista, data_inicio_real,
  data_fim_real, custo_previsto, 0, 0, id, created_at, updated_at
FROM obra_fases;

INSERT INTO obra_itens (
  obra_id, parent_id, nome, descricao, responsavel, ordem, status, progresso,
  duracao_prevista_dias, data_inicio_prevista, data_fim_prevista, data_inicio_real,
  data_fim_real, custo_previsto, custo_real, marco, legacy_step_id, created_at, updated_at
)
SELECT
  f.obra_id, i.id, e.nome, e.descricao, e.responsavel, e.ordem, e.status, e.progresso,
  e.duracao_prevista_dias, e.data_inicio_prevista, e.data_fim_prevista, e.data_inicio_real,
  e.data_fim_real, e.custo_previsto, e.custo_real, e.marco, e.id, e.created_at, e.updated_at
FROM obra_etapas e
INNER JOIN obra_fases f ON f.id = e.fase_id
INNER JOIN obra_itens i ON i.legacy_phase_id = f.id;

UPDATE obra_itens child
INNER JOIN obra_itens parent
  ON parent.obra_id = child.obra_id
 AND parent.legacy_step_id IS NOT NULL
 AND SUBSTRING_INDEX(parent.nome, ' ', 1) = SUBSTRING_INDEX(SUBSTRING_INDEX(child.nome, ' ', 1), '.', 2)
SET child.parent_id = parent.id
WHERE child.legacy_step_id IS NOT NULL
  AND (LENGTH(SUBSTRING_INDEX(child.nome, ' ', 1)) - LENGTH(REPLACE(SUBSTRING_INDEX(child.nome, ' ', 1), '.', ''))) = 2
  AND (LENGTH(SUBSTRING_INDEX(parent.nome, ' ', 1)) - LENGTH(REPLACE(SUBSTRING_INDEX(parent.nome, ' ', 1), '.', ''))) = 1;

UPDATE obra_itens
SET nome = SUBSTRING(nome, LOCATE(' ', nome) + 1)
WHERE nome REGEXP '^[0-9]+(\\.[0-9]+)* ';

UPDATE obra_itens
SET nome = SUBSTRING(nome, LOCATE(' ', nome) + 1)
WHERE nome REGEXP '^[0-9]+[.] ';

CREATE TABLE obra_item_predecessores (
  item_id BIGINT UNSIGNED NOT NULL,
  predecessor_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',
  defasagem_dias SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (item_id, predecessor_id),
  KEY obra_item_predecessores_predecessor_foreign (predecessor_id),
  CONSTRAINT obra_item_predecessores_item_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE CASCADE,
  CONSTRAINT obra_item_predecessores_predecessor_foreign FOREIGN KEY (predecessor_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO obra_item_predecessores (item_id, predecessor_id, tipo, defasagem_dias, created_at)
SELECT item.id, predecessor.id, 'FS', 0, NOW()
FROM obra_etapas e
INNER JOIN obra_itens item ON item.legacy_step_id = e.id
INNER JOIN obra_itens predecessor ON predecessor.legacy_step_id = e.etapa_predecessora_id
WHERE e.etapa_predecessora_id IS NOT NULL;

ALTER TABLE obra_materiais ADD COLUMN item_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER obra_id;
UPDATE obra_materiais m INNER JOIN obra_itens i ON i.legacy_step_id = m.etapa_id SET m.item_id = i.id;
ALTER TABLE obra_materiais
  DROP FOREIGN KEY obra_materiais_etapa_id_foreign,
  DROP INDEX obra_materiais_etapa_foreign,
  DROP COLUMN etapa_id,
  ADD KEY obra_materiais_item_foreign (item_id),
  ADD CONSTRAINT obra_materiais_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL;

ALTER TABLE obra_servicos ADD COLUMN item_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER obra_id;
UPDATE obra_servicos s INNER JOIN obra_itens i ON i.legacy_step_id = s.etapa_id SET s.item_id = i.id;
ALTER TABLE obra_servicos
  DROP FOREIGN KEY obra_servicos_etapa_id_foreign,
  DROP INDEX obra_servicos_etapa_foreign,
  DROP COLUMN etapa_id,
  ADD KEY obra_servicos_item_foreign (item_id),
  ADD CONSTRAINT obra_servicos_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL;

ALTER TABLE obra_movimentos_financeiros ADD COLUMN item_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER obra_id;
UPDATE obra_movimentos_financeiros m INNER JOIN obra_itens i ON i.legacy_step_id = m.etapa_id SET m.item_id = i.id;
ALTER TABLE obra_movimentos_financeiros
  DROP FOREIGN KEY obra_movimentos_etapa_id_foreign,
  DROP INDEX obra_movimentos_etapa_foreign,
  DROP COLUMN etapa_id,
  ADD KEY obra_movimentos_item_foreign (item_id),
  ADD CONSTRAINT obra_movimentos_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL;

DROP TABLE obra_etapas;
DROP TABLE obra_fases;

ALTER TABLE obra_itens
  DROP COLUMN legacy_phase_id,
  DROP COLUMN legacy_step_id;
