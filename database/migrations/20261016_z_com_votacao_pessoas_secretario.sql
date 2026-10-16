ALTER TABLE com_votacao_pessoas
  MODIFY COLUMN papel ENUM('votado','associado','candidato','secretario') NOT NULL DEFAULT 'candidato';
