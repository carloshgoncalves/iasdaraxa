CREATE TABLE sinapi_catalogo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo ENUM('Material','Servico','Equipamento') NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  descricao VARCHAR(500) NOT NULL,
  unidade VARCHAR(30) NOT NULL,
  preco_mg DECIMAL(15,4) NULL DEFAULT NULL,
  grupo VARCHAR(160) NULL DEFAULT NULL,
  origem_preco VARCHAR(10) NULL DEFAULT NULL,
  competencia CHAR(7) NOT NULL,
  uf CHAR(2) NOT NULL DEFAULT 'MG',
  regime VARCHAR(40) NOT NULL DEFAULT 'Sem desoneracao',
  fonte_url VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY sinapi_catalogo_referencia_unique (tipo, codigo, competencia, uf, regime),
  KEY sinapi_catalogo_tipo_ativo_codigo_index (tipo, ativo, codigo),
  KEY sinapi_catalogo_tipo_ativo_descricao_index (tipo, ativo, descricao(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE obra_materiais
  ADD COLUMN sinapi_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER fornecedor_id,
  ADD KEY obra_materiais_sinapi_foreign (sinapi_id),
  ADD CONSTRAINT obra_materiais_sinapi_id_foreign FOREIGN KEY (sinapi_id) REFERENCES sinapi_catalogo(id) ON DELETE SET NULL;

ALTER TABLE obra_servicos
  ADD COLUMN sinapi_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER fornecedor_id,
  ADD KEY obra_servicos_sinapi_foreign (sinapi_id),
  ADD CONSTRAINT obra_servicos_sinapi_id_foreign FOREIGN KEY (sinapi_id) REFERENCES sinapi_catalogo(id) ON DELETE SET NULL;

CREATE TABLE obra_equipamentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL,
  sinapi_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  unidade VARCHAR(30) NOT NULL DEFAULT 'h',
  quantidade_prevista DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  quantidade_real DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  valor_unitario_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  valor_unitario_real DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('Previsto','Cotando','Contratado','Em uso','Devolvido','Cancelado') NOT NULL DEFAULT 'Previsto',
  data_inicio DATE NULL DEFAULT NULL,
  data_fim DATE NULL DEFAULT NULL,
  observacoes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_equipamentos_obra_status_index (obra_id, status),
  KEY obra_equipamentos_item_foreign (item_id),
  KEY obra_equipamentos_fornecedor_foreign (fornecedor_id),
  KEY obra_equipamentos_sinapi_foreign (sinapi_id),
  CONSTRAINT obra_equipamentos_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_equipamentos_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL,
  CONSTRAINT obra_equipamentos_fornecedor_id_foreign FOREIGN KEY (fornecedor_id) REFERENCES obra_fornecedores(id) ON DELETE SET NULL,
  CONSTRAINT obra_equipamentos_sinapi_id_foreign FOREIGN KEY (sinapi_id) REFERENCES sinapi_catalogo(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE obra_movimentos_financeiros
  MODIFY COLUMN categoria ENUM('Material','Servico','Equipamento','Mao de obra','Taxa','Doacao','Outro') NOT NULL DEFAULT 'Outro';
