CREATE TABLE IF NOT EXISTS obra_memoriais (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 0,
  tipo_obra ENUM('Residencial','Comercial','Industrial','Institucional','Reforma','Infraestrutura','Outro') NOT NULL DEFAULT 'Comercial',
  padrao_acabamento ENUM('Economico','Padrao','Alto','Especial') NOT NULL DEFAULT 'Padrao',
  sistema_estrutural ENUM('Concreto armado','Estrutura metalica','Alvenaria estrutural','Misto','Outro') NOT NULL DEFAULT 'Concreto armado',
  area_terreno DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_construida DECIMAL(12,2) NOT NULL DEFAULT 0,
  testada DECIMAL(10,2) NOT NULL DEFAULT 0,
  pavimentos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unidades SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  banheiros SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  cozinhas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  pe_direito DECIMAL(6,2) NOT NULL DEFAULT 2.80,
  area_externa DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_cobertura DECIMAL(12,2) NOT NULL DEFAULT 0,
  meses_obra SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  possui_subsolo TINYINT(1) NOT NULL DEFAULT 0,
  possui_elevador TINYINT(1) NOT NULL DEFAULT 0,
  possui_piscina TINYINT(1) NOT NULL DEFAULT 0,
  observacoes TEXT NULL,
  generated_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY obra_memoriais_obra_unique (obra_id),
  CONSTRAINT obra_memoriais_obra_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_memorial_vinculos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  memorial_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  regra_codigo VARCHAR(80) NOT NULL,
  origem ENUM('Gerado','Associado') NOT NULL DEFAULT 'Gerado',
  quantidade_calculada DECIMAL(15,4) NOT NULL DEFAULT 0,
  unidade VARCHAR(20) NOT NULL,
  formula VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY obra_memorial_vinculos_item_unique (item_id),
  UNIQUE KEY obra_memorial_vinculos_regra_unique (memorial_id, regra_codigo),
  CONSTRAINT obra_memorial_vinculos_memorial_foreign FOREIGN KEY (memorial_id) REFERENCES obra_memoriais(id) ON DELETE CASCADE,
  CONSTRAINT obra_memorial_vinculos_item_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_memorial_exclusoes (
  memorial_id BIGINT UNSIGNED NOT NULL,
  regra_codigo VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (memorial_id, regra_codigo),
  CONSTRAINT obra_memorial_exclusoes_memorial_foreign FOREIGN KEY (memorial_id) REFERENCES obra_memoriais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
