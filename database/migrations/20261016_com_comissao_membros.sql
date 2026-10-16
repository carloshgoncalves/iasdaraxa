CREATE TABLE IF NOT EXISTS com_comissao_membros (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_id BIGINT UNSIGNED NOT NULL,
  pessoa_id BIGINT UNSIGNED NOT NULL,
  papel ENUM('presidente','secretario','membro') NOT NULL DEFAULT 'membro',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_comissao_membros_unique (comissao_id, pessoa_id),
  KEY com_comissao_membros_pessoa_index (pessoa_id),
  KEY com_comissao_membros_papel_index (comissao_id, papel),
  CONSTRAINT com_comissao_membros_comissao_fk FOREIGN KEY (comissao_id) REFERENCES com_comissoes(id) ON DELETE CASCADE,
  CONSTRAINT com_comissao_membros_pessoa_fk FOREIGN KEY (pessoa_id) REFERENCES com_pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
