CREATE TABLE IF NOT EXISTS com_comissao_cargos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_id BIGINT UNSIGNED NOT NULL,
  cargo_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_comissao_cargos_unique (comissao_id, cargo_id),
  KEY com_comissao_cargos_cargo_index (cargo_id),
  CONSTRAINT com_comissao_cargos_comissao_fk FOREIGN KEY (comissao_id) REFERENCES com_comissoes(id) ON DELETE CASCADE,
  CONSTRAINT com_comissao_cargos_cargo_fk FOREIGN KEY (cargo_id) REFERENCES com_cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
