CREATE TABLE IF NOT EXISTS com_indicacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  voto_id BIGINT UNSIGNED NOT NULL,
  comissao_membro_id BIGINT UNSIGNED NOT NULL,
  pessoa_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_indicacoes_membro_pessoa_unique (voto_id, comissao_membro_id, pessoa_id),
  KEY com_indicacoes_pessoa_index (pessoa_id),
  KEY com_indicacoes_membro_index (comissao_membro_id),
  CONSTRAINT com_indicacoes_voto_fk FOREIGN KEY (voto_id) REFERENCES com_voto(id) ON DELETE CASCADE,
  CONSTRAINT com_indicacoes_membro_fk FOREIGN KEY (comissao_membro_id) REFERENCES com_comissao_membros(id) ON DELETE CASCADE,
  CONSTRAINT com_indicacoes_pessoa_fk FOREIGN KEY (pessoa_id) REFERENCES com_pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_indicacao_conclusoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  voto_id BIGINT UNSIGNED NOT NULL,
  comissao_membro_id BIGINT UNSIGNED NOT NULL,
  encerrada_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_indicacao_conclusoes_membro_unique (voto_id, comissao_membro_id),
  KEY com_indicacao_conclusoes_membro_index (comissao_membro_id),
  CONSTRAINT com_indicacao_conclusoes_voto_fk FOREIGN KEY (voto_id) REFERENCES com_voto(id) ON DELETE CASCADE,
  CONSTRAINT com_indicacao_conclusoes_membro_fk FOREIGN KEY (comissao_membro_id) REFERENCES com_comissao_membros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
