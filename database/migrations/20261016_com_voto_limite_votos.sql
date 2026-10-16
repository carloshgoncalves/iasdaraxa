ALTER TABLE com_voto
  ADD COLUMN limite_votos TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER limite_indicacoes;
