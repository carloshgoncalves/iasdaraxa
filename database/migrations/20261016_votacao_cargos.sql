ALTER TABLE com_comissao_cargos
  ADD COLUMN votacao_status ENUM('aberta','encerrada') NOT NULL DEFAULT 'aberta' AFTER ordem,
  ADD COLUMN votacao_encerrada_at TIMESTAMP NULL DEFAULT NULL AFTER votacao_status;

CREATE TABLE IF NOT EXISTS com_votacao_pessoas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_cargo_id BIGINT UNSIGNED NOT NULL,
  pessoa_id BIGINT UNSIGNED NOT NULL,
  votos INT UNSIGNED NOT NULL DEFAULT 0,
  papel ENUM('votado','associado','candidato') NOT NULL DEFAULT 'candidato',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_votacao_pessoas_unique (comissao_cargo_id, pessoa_id),
  KEY com_votacao_pessoas_pessoa_index (pessoa_id),
  KEY com_votacao_pessoas_papel_index (comissao_cargo_id, papel),
  CONSTRAINT com_votacao_pessoas_cargo_fk FOREIGN KEY (comissao_cargo_id) REFERENCES com_comissao_cargos(id) ON DELETE CASCADE,
  CONSTRAINT com_votacao_pessoas_pessoa_fk FOREIGN KEY (pessoa_id) REFERENCES com_pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
