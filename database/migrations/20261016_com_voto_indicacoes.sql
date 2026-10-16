CREATE TABLE IF NOT EXISTS com_voto (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_cargo_id BIGINT UNSIGNED NOT NULL,
  status ENUM('indicacao','votando','encerrado') NOT NULL DEFAULT 'indicacao',
  indicacoes_abertas_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_voto_comissao_cargo_unique (comissao_cargo_id),
  KEY com_voto_status_index (status),
  CONSTRAINT com_voto_comissao_cargo_fk FOREIGN KEY (comissao_cargo_id) REFERENCES com_comissao_cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
