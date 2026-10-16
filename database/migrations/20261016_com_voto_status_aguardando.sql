ALTER TABLE com_voto
  MODIFY COLUMN status ENUM('aguardando','indicacao','votando','encerrado') NOT NULL DEFAULT 'aguardando';
