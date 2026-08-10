ALTER TABLE users
  DROP INDEX users_email_unique,
  CHANGE COLUMN email username VARCHAR(80) NOT NULL,
  ADD UNIQUE KEY users_username_unique (username);

UPDATE users
SET name = 'Administrador',
    username = 'administrador',
    password = '$2y$10$Mz8.ftrICZonEjDW03aPNuuNLOC6EDFvXV5FZsLZ5inGi5nAQX5Bq',
    status = 'Ativo',
    updated_at = NOW()
WHERE username = 'admin@iasdaraxa.local';
