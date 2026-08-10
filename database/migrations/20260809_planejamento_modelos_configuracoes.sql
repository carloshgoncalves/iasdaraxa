CREATE TABLE IF NOT EXISTS obra_modelos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  descricao VARCHAR(255) NULL DEFAULT NULL,
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY obra_modelos_nome_unique (nome),
  KEY obra_modelos_created_by_foreign (created_by),
  CONSTRAINT obra_modelos_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_modelo_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  modelo_id BIGINT UNSIGNED NOT NULL,
  parent_item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  responsavel VARCHAR(160) NULL DEFAULT NULL,
  ordem INT(11) NOT NULL DEFAULT 0,
  duracao_prevista_dias SMALLINT UNSIGNED NULL DEFAULT NULL,
  custo_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  marco TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_modelo_itens_modelo_parent_ordem_index (modelo_id, parent_item_id, ordem),
  KEY obra_modelo_itens_parent_foreign (parent_item_id),
  CONSTRAINT obra_modelo_itens_modelo_foreign FOREIGN KEY (modelo_id) REFERENCES obra_modelos(id) ON DELETE CASCADE,
  CONSTRAINT obra_modelo_itens_parent_foreign FOREIGN KEY (parent_item_id) REFERENCES obra_modelo_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  updated_by BIGINT UNSIGNED NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (setting_key),
  KEY app_settings_updated_by_foreign (updated_by),
  CONSTRAINT app_settings_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES
('font_family', 'Arial', NOW()),
('layout_density', 'comfortable', NOW()),
('language', 'pt-BR', NOW()),
('brand_color', '#7a1f38', NOW()),
('sidebar_color', '#251b20', NOW()),
('sidebar_width', '250', NOW())
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
