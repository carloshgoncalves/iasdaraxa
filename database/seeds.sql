INSERT INTO users (name, username, password, status, created_at, updated_at)
VALUES ('Administrador', 'administrador', '$2y$10$Mz8.ftrICZonEjDW03aPNuuNLOC6EDFvXV5FZsLZ5inGi5nAQX5Bq', 'Ativo', NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), status = VALUES(status), updated_at = NOW();

INSERT INTO roles (name, slug, status, created_at, updated_at)
VALUES ('Administrador', 'admin', 'Ativo', NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status), updated_at = NOW();

SET @admin_user_id := (SELECT id FROM users WHERE username = 'administrador' LIMIT 1);
SET @admin_role_id := (SELECT id FROM roles WHERE slug = 'admin' LIMIT 1);

INSERT IGNORE INTO user_roles (user_id, role_id, created_at)
VALUES (@admin_user_id, @admin_role_id, NOW());

INSERT INTO menus (id, id_menu_pai, nome, descricao, rota, icone, ordem, status, tipo, nivel, chave_permissao, created_at, updated_at) VALUES
(1000, NULL, 'Administrativo', 'Painel administrativo da IASD Central', '', 'layout-dashboard', 10, 'Ativo', 'Separador', 'module', 'menu:1000', NOW(), NOW()),
(1010, 1000, 'Dashboard', 'Resumo geral', '/dashboard', 'gauge', 10, 'Ativo', 'Tela', 'menu', 'menu:1010', NOW(), NOW()),
(1020, 1000, 'Obras', 'Controle de obras e reformas', '/obras', 'hard-hat', 20, 'Ativo', 'Tela', 'menu', 'menu:1020', NOW(), NOW()),
(1030, 1000, 'Escalas', 'Escalas e equipes', '/escalas', 'calendar-days', 30, 'Ativo', 'Tela', 'menu', 'menu:1030', NOW(), NOW()),
(1040, 1000, 'Tarefas', 'Tarefas em aberto', '/tarefas', 'list-checks', 40, 'Ativo', 'Tela', 'menu', 'menu:1040', NOW(), NOW()),
(1050, 1000, 'Agenda geral', 'Agenda geral da igreja', '/agenda', 'calendar-clock', 50, 'Ativo', 'Tela', 'menu', 'menu:1050', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  id_menu_pai = VALUES(id_menu_pai),
  nome = VALUES(nome),
  descricao = VALUES(descricao),
  rota = VALUES(rota),
  icone = VALUES(icone),
  ordem = VALUES(ordem),
  status = VALUES(status),
  tipo = VALUES(tipo),
  nivel = VALUES(nivel),
  chave_permissao = VALUES(chave_permissao),
  updated_at = NOW();

INSERT INTO access_rules (role_id, menu_id, type, status, created_at, updated_at)
SELECT @admin_role_id, m.id, 'visualizar', 'Ativo', NOW(), NOW()
FROM menus m
ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW();

INSERT INTO obras (nome, local, descricao, status, prioridade, data_inicio_prevista, data_fim_prevista, created_by, created_at, updated_at)
SELECT 'Reforma inicial', 'Templo', 'Registro inicial para validar o modulo de Obras.', 'Planejada', 'Alta', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), @admin_user_id, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM obras WHERE nome = 'Reforma inicial' AND local = 'Templo' LIMIT 1
);

INSERT INTO agenda_atividades (nome, descricao, cor, duracao_padrao_minutos, exige_escala, status, created_by, created_at, updated_at) VALUES
('Culto Divino', 'Culto principal de sabado pela manha.', '#7a1f38', 90, 1, 'Ativa', @admin_user_id, NOW(), NOW()),
('Escola Sabatina', 'Estudo da licao em classes.', '#2f6d54', 75, 1, 'Ativa', @admin_user_id, NOW(), NOW()),
('Culto Jovem', 'Programa jovem da igreja.', '#b88a32', 75, 1, 'Ativa', @admin_user_id, NOW(), NOW()),
('Reuniao administrativa', 'Reunioes internas de lideranca.', '#475467', 60, 0, 'Ativa', @admin_user_id, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  descricao = VALUES(descricao),
  cor = VALUES(cor),
  duracao_padrao_minutos = VALUES(duracao_padrao_minutos),
  exige_escala = VALUES(exige_escala),
  status = VALUES(status),
  updated_at = NOW();

SET @culto_divino_id := (SELECT id FROM agenda_atividades WHERE nome = 'Culto Divino' LIMIT 1);
SET @escola_sabatina_id := (SELECT id FROM agenda_atividades WHERE nome = 'Escola Sabatina' LIMIT 1);

INSERT INTO agenda_eventos (atividade_id, titulo, descricao, data_inicio, hora_inicio, data_fim, hora_fim, dia_todo, local, responsavel, visibilidade, status, created_by, created_at, updated_at)
SELECT @escola_sabatina_id, 'Escola Sabatina', 'Programacao regular de sabado.', DATE_ADD(CURDATE(), INTERVAL ((7 - DAYOFWEEK(CURDATE()) + 7) % 7) DAY), '09:00:00', NULL, '10:15:00', 0, 'Templo', NULL, 'Publica', 'Confirmada', @admin_user_id, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM agenda_eventos WHERE titulo = 'Escola Sabatina' AND data_inicio >= CURDATE() LIMIT 1
);

INSERT INTO agenda_eventos (atividade_id, titulo, descricao, data_inicio, hora_inicio, data_fim, hora_fim, dia_todo, local, responsavel, visibilidade, status, created_by, created_at, updated_at)
SELECT @culto_divino_id, 'Culto Divino', 'Programacao regular de sabado.', DATE_ADD(CURDATE(), INTERVAL ((7 - DAYOFWEEK(CURDATE()) + 7) % 7) DAY), '10:30:00', NULL, '12:00:00', 0, 'Templo', NULL, 'Publica', 'Confirmada', @admin_user_id, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM agenda_eventos WHERE titulo = 'Culto Divino' AND data_inicio >= CURDATE() LIMIT 1
);
