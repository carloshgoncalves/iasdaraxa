ALTER TABLE sinapi_catalogo
  ADD COLUMN competencia_preco CHAR(7) NULL DEFAULT NULL AFTER preco_mg;

UPDATE sinapi_catalogo
SET competencia_preco = competencia
WHERE preco_mg IS NOT NULL AND competencia_preco IS NULL;
