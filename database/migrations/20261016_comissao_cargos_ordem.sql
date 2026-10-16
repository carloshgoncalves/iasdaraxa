ALTER TABLE com_comissao_cargos
  ADD COLUMN ordem INT UNSIGNED NOT NULL DEFAULT 0 AFTER cargo_id;

UPDATE com_comissao_cargos
SET ordem = id
WHERE ordem = 0;

ALTER TABLE com_comissao_cargos
  ADD KEY com_comissao_cargos_ordem_index (comissao_id, ordem);
