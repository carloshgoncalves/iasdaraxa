CREATE TABLE IF NOT EXISTS clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  documento VARCHAR(40) NULL DEFAULT NULL,
  contato VARCHAR(160) NULL DEFAULT NULL,
  telefone VARCHAR(40) NULL DEFAULT NULL,
  email VARCHAR(160) NULL DEFAULT NULL,
  observacoes TEXT NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY clientes_status_nome_index (status, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES
('theme_mode', 'light', NOW()),
('sinapi_auto_update', '1', NOW()),
('sinapi_last_check', '', NOW()),
('sinapi_last_status', 'Nunca verificado', NOW()),
('sinapi_last_error', '', NOW())
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
