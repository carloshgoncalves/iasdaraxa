CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  username VARCHAR(80) NOT NULL,
  password VARCHAR(255) NOT NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY roles_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (user_id, role_id),
  KEY user_roles_role_id_foreign (role_id),
  CONSTRAINT user_roles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT user_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menus (
  id INT(11) UNSIGNED NOT NULL,
  id_menu_pai INT(11) UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(120) NOT NULL,
  descricao VARCHAR(255) NULL DEFAULT NULL,
  rota VARCHAR(255) NOT NULL DEFAULT '',
  icone VARCHAR(80) NULL DEFAULT NULL,
  ordem INT(11) NOT NULL DEFAULT 0,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  tipo ENUM('Separador','Tela') NOT NULL DEFAULT 'Tela',
  nivel VARCHAR(40) NULL DEFAULT NULL,
  chave_permissao VARCHAR(80) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY menus_id_menu_pai_index (id_menu_pai),
  KEY menus_status_ordem_index (status, ordem),
  CONSTRAINT menus_id_menu_pai_foreign FOREIGN KEY (id_menu_pai) REFERENCES menus(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  menu_id INT(11) UNSIGNED NOT NULL,
  type ENUM('visualizar','criar','editar','excluir') NOT NULL DEFAULT 'visualizar',
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY access_rules_role_menu_type_unique (role_id, menu_id, type),
  KEY access_rules_menu_id_foreign (menu_id),
  CONSTRAINT access_rules_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT access_rules_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_news (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  summary TEXT NULL,
  image_url VARCHAR(500) NULL DEFAULT NULL,
  source_url VARCHAR(500) NULL DEFAULT NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  published_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY public_news_status_published_index (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obras (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  local VARCHAR(160) NULL DEFAULT NULL,
  descricao TEXT NULL,
  status ENUM('Planejada','Em andamento','Pausada','Concluida','Cancelada') NOT NULL DEFAULT 'Planejada',
  prioridade ENUM('Baixa','Media','Alta','Critica') NOT NULL DEFAULT 'Media',
  data_inicio_prevista DATE NULL DEFAULT NULL,
  data_fim_prevista DATE NULL DEFAULT NULL,
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obras_status_index (status),
  KEY obras_created_by_foreign (created_by),
  CONSTRAINT obras_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_fornecedores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  contato VARCHAR(160) NULL DEFAULT NULL,
  telefone VARCHAR(40) NULL DEFAULT NULL,
  documento VARCHAR(40) NULL DEFAULT NULL,
  observacoes TEXT NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_fornecedores_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  documento VARCHAR(40) NULL DEFAULT NULL,
  contato VARCHAR(160) NULL DEFAULT NULL,
  telefone VARCHAR(40) NULL DEFAULT NULL,
  email VARCHAR(160) NULL DEFAULT NULL,
  observacoes TEXT NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY clientes_status_nome_index (status, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_contratos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL,
  titulo VARCHAR(160) NOT NULL,
  valor_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('Ativo','Pendente','Concluido','Cancelado') NOT NULL DEFAULT 'Pendente',
  data_inicio DATE NULL DEFAULT NULL,
  data_fim DATE NULL DEFAULT NULL,
  observacoes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_contratos_obra_id_status_index (obra_id, status),
  KEY obra_contratos_fornecedor_id_foreign (fornecedor_id),
  CONSTRAINT obra_contratos_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_contratos_fornecedor_id_foreign FOREIGN KEY (fornecedor_id) REFERENCES obra_fornecedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_pendencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  responsavel VARCHAR(160) NULL DEFAULT NULL,
  status ENUM('Aberta','Em andamento','Resolvida','Cancelada') NOT NULL DEFAULT 'Aberta',
  prazo DATE NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_pendencias_obra_status_index (obra_id, status),
  CONSTRAINT obra_pendencias_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_atividades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(140) NOT NULL,
  descricao TEXT NULL,
  cor VARCHAR(7) NOT NULL DEFAULT '#7a1f38',
  duracao_padrao_minutos SMALLINT UNSIGNED NULL DEFAULT NULL,
  exige_escala TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('Ativa','Inativa') NOT NULL DEFAULT 'Ativa',
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY agenda_atividades_nome_unique (nome),
  KEY agenda_atividades_status_index (status),
  KEY agenda_atividades_created_by_foreign (created_by),
  CONSTRAINT agenda_atividades_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  atividade_id BIGINT UNSIGNED NULL DEFAULT NULL,
  titulo VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  data_inicio DATE NOT NULL,
  hora_inicio TIME NULL DEFAULT NULL,
  data_fim DATE NULL DEFAULT NULL,
  hora_fim TIME NULL DEFAULT NULL,
  dia_todo TINYINT(1) NOT NULL DEFAULT 0,
  local VARCHAR(160) NULL DEFAULT NULL,
  responsavel VARCHAR(160) NULL DEFAULT NULL,
  visibilidade ENUM('Publica','Interna') NOT NULL DEFAULT 'Interna',
  status ENUM('Agendada','Confirmada','Cancelada','Concluida') NOT NULL DEFAULT 'Agendada',
  observacoes TEXT NULL,
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY agenda_eventos_data_status_index (data_inicio, status),
  KEY agenda_eventos_atividade_data_index (atividade_id, data_inicio),
  KEY agenda_eventos_created_by_foreign (created_by),
  CONSTRAINT agenda_eventos_atividade_id_foreign FOREIGN KEY (atividade_id) REFERENCES agenda_atividades(id) ON DELETE SET NULL,
  CONSTRAINT agenda_eventos_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  responsavel VARCHAR(160) NULL DEFAULT NULL,
  ordem INT(11) NOT NULL DEFAULT 0,
  status ENUM('Nao iniciada','Em andamento','Bloqueada','Pausada','Concluida','Cancelada') NOT NULL DEFAULT 'Nao iniciada',
  progresso TINYINT UNSIGNED NOT NULL DEFAULT 0,
  duracao_prevista_dias SMALLINT UNSIGNED NULL DEFAULT NULL,
  data_inicio_prevista DATE NULL DEFAULT NULL,
  data_fim_prevista DATE NULL DEFAULT NULL,
  data_inicio_real DATE NULL DEFAULT NULL,
  data_fim_real DATE NULL DEFAULT NULL,
  custo_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  custo_real DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  marco TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_itens_obra_parent_ordem_index (obra_id, parent_id, ordem),
  KEY obra_itens_parent_id_foreign (parent_id),
  CONSTRAINT obra_itens_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_itens_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_item_predecessores (
  item_id BIGINT UNSIGNED NOT NULL,
  predecessor_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',
  defasagem_dias SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (item_id, predecessor_id),
  KEY obra_item_predecessores_predecessor_foreign (predecessor_id),
  CONSTRAINT obra_item_predecessores_item_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE CASCADE,
  CONSTRAINT obra_item_predecessores_predecessor_foreign FOREIGN KEY (predecessor_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_memoriais (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 0,
  tipo_obra ENUM('Residencial','Comercial','Industrial','Institucional','Reforma','Infraestrutura','Outro') NOT NULL DEFAULT 'Comercial',
  padrao_acabamento ENUM('Economico','Padrao','Alto','Especial') NOT NULL DEFAULT 'Padrao',
  sistema_estrutural ENUM('Concreto armado','Estrutura metalica','Alvenaria estrutural','Misto','Outro') NOT NULL DEFAULT 'Concreto armado',
  area_terreno DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_construida DECIMAL(12,2) NOT NULL DEFAULT 0,
  testada DECIMAL(10,2) NOT NULL DEFAULT 0,
  pavimentos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unidades SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  banheiros SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  cozinhas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  pe_direito DECIMAL(6,2) NOT NULL DEFAULT 2.80,
  area_externa DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_cobertura DECIMAL(12,2) NOT NULL DEFAULT 0,
  meses_obra SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  possui_subsolo TINYINT(1) NOT NULL DEFAULT 0,
  possui_elevador TINYINT(1) NOT NULL DEFAULT 0,
  possui_piscina TINYINT(1) NOT NULL DEFAULT 0,
  comprimento_baldrame DECIMAL(12,2) NOT NULL DEFAULT 0,
  comprimento_muro_divisa DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantidade_brocas DECIMAL(12,2) NOT NULL DEFAULT 0,
  peso_aco_estimado DECIMAL(15,2) NOT NULL DEFAULT 0,
  volume_concreto_estimado DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_revestimento_interno DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_revestimento_externo DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_reboco DECIMAL(15,2) NOT NULL DEFAULT 0,
  quantidade_portas_madeira DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantidade_esquadrias_aluminio DECIMAL(12,2) NOT NULL DEFAULT 0,
  guarda_corpo_m DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantidade_bancadas DECIMAL(12,2) NOT NULL DEFAULT 0,
  soleiras_m DECIMAL(12,2) NOT NULL DEFAULT 0,
  peitoris_m DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_forro_gesso DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_forro_madeira DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_telhado_metalico DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_telhado_ceramico DECIMAL(15,2) NOT NULL DEFAULT 0,
  calhas_m DECIMAL(12,2) NOT NULL DEFAULT 0,
  rufos_m DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_pintura_interna DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_pintura_externa DECIMAL(15,2) NOT NULL DEFAULT 0,
  quantidade_bacias DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantidade_torneiras DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantidade_cubas DECIMAL(12,2) NOT NULL DEFAULT 0,
  area_deck DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_paisagismo DECIMAL(15,2) NOT NULL DEFAULT 0,
  area_piscina DECIMAL(15,2) NOT NULL DEFAULT 0,
  pontos_eletricos DECIMAL(12,2) NOT NULL DEFAULT 0,
  pontos_hidraulicos DECIMAL(12,2) NOT NULL DEFAULT 0,
  observacoes TEXT NULL,
  generated_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY obra_memoriais_obra_unique (obra_id),
  CONSTRAINT obra_memoriais_obra_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_memorial_vinculos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  memorial_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  regra_codigo VARCHAR(80) NOT NULL,
  origem ENUM('Gerado','Associado') NOT NULL DEFAULT 'Gerado',
  quantidade_calculada DECIMAL(15,4) NOT NULL DEFAULT 0,
  unidade VARCHAR(20) NOT NULL,
  formula VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY obra_memorial_vinculos_item_unique (item_id),
  UNIQUE KEY obra_memorial_vinculos_regra_unique (memorial_id, regra_codigo),
  CONSTRAINT obra_memorial_vinculos_memorial_foreign FOREIGN KEY (memorial_id) REFERENCES obra_memoriais(id) ON DELETE CASCADE,
  CONSTRAINT obra_memorial_vinculos_item_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_memorial_exclusoes (
  memorial_id BIGINT UNSIGNED NOT NULL,
  regra_codigo VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (memorial_id, regra_codigo),
  CONSTRAINT obra_memorial_exclusoes_memorial_foreign FOREIGN KEY (memorial_id) REFERENCES obra_memoriais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_item_composicao_analises (
  item_id BIGINT UNSIGNED NOT NULL,
  status ENUM('Composta','Existente','Revisao manual','Sem correspondencia') NOT NULL,
  confianca TINYINT UNSIGNED NOT NULL DEFAULT 0,
  justificativa VARCHAR(500) NOT NULL,
  analyzed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (item_id),
  CONSTRAINT obra_item_composicao_analises_item_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_modelos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  descricao VARCHAR(255) NULL DEFAULT NULL,
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY obra_modelos_nome_unique (nome),
  KEY obra_modelos_created_by_foreign (created_by),
  CONSTRAINT obra_modelos_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_modelo_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  modelo_id BIGINT UNSIGNED NOT NULL,
  parent_item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  responsavel VARCHAR(160) NULL DEFAULT NULL,
  ordem INT(11) NOT NULL DEFAULT 0,
  duracao_prevista_dias SMALLINT UNSIGNED NULL DEFAULT NULL,
  custo_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  marco TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_modelo_itens_modelo_parent_ordem_index (modelo_id, parent_item_id, ordem),
  KEY obra_modelo_itens_parent_foreign (parent_item_id),
  CONSTRAINT obra_modelo_itens_modelo_foreign FOREIGN KEY (modelo_id) REFERENCES obra_modelos(id) ON DELETE CASCADE,
  CONSTRAINT obra_modelo_itens_parent_foreign FOREIGN KEY (parent_item_id) REFERENCES obra_modelo_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  updated_by BIGINT UNSIGNED NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (setting_key),
  KEY app_settings_updated_by_foreign (updated_by),
  CONSTRAINT app_settings_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sinapi_catalogo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo ENUM('Material','Servico','Equipamento') NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  descricao VARCHAR(500) NOT NULL,
  unidade VARCHAR(30) NOT NULL,
  preco_mg DECIMAL(15,4) NULL DEFAULT NULL,
  competencia_preco CHAR(7) NULL DEFAULT NULL,
  grupo VARCHAR(160) NULL DEFAULT NULL,
  origem_preco VARCHAR(10) NULL DEFAULT NULL,
  competencia CHAR(7) NOT NULL,
  uf CHAR(2) NOT NULL DEFAULT 'MG',
  regime VARCHAR(40) NOT NULL DEFAULT 'Sem desoneracao',
  fonte_url VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY sinapi_catalogo_referencia_unique (tipo, codigo, competencia, uf, regime),
  KEY sinapi_catalogo_tipo_ativo_codigo_index (tipo, ativo, codigo),
  KEY sinapi_catalogo_tipo_ativo_descricao_index (tipo, ativo, descricao(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_materiais (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL,
  sinapi_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  especificacao TEXT NULL,
  unidade VARCHAR(30) NOT NULL DEFAULT 'un',
  quantidade_prevista DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  quantidade_comprada DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  valor_unitario_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  valor_unitario_real DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('Previsto','Solicitado','Comprado','Entregue','Cancelado') NOT NULL DEFAULT 'Previsto',
  data_necessidade DATE NULL DEFAULT NULL,
  observacoes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_materiais_obra_status_index (obra_id, status),
  KEY obra_materiais_item_foreign (item_id),
  KEY obra_materiais_fornecedor_foreign (fornecedor_id),
  KEY obra_materiais_sinapi_foreign (sinapi_id),
  CONSTRAINT obra_materiais_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_materiais_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL,
  CONSTRAINT obra_materiais_fornecedor_id_foreign FOREIGN KEY (fornecedor_id) REFERENCES obra_fornecedores(id) ON DELETE SET NULL,
  CONSTRAINT obra_materiais_sinapi_id_foreign FOREIGN KEY (sinapi_id) REFERENCES sinapi_catalogo(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_servicos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL,
  sinapi_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  escopo TEXT NULL,
  unidade VARCHAR(30) NOT NULL DEFAULT 'servico',
  quantidade DECIMAL(15,3) NOT NULL DEFAULT 1.000,
  valor_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  valor_contratado DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('Previsto','Cotando','Contratado','Em execucao','Medido','Concluido','Cancelado') NOT NULL DEFAULT 'Previsto',
  data_inicio DATE NULL DEFAULT NULL,
  data_fim DATE NULL DEFAULT NULL,
  observacoes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_servicos_obra_status_index (obra_id, status),
  KEY obra_servicos_item_foreign (item_id),
  KEY obra_servicos_fornecedor_foreign (fornecedor_id),
  KEY obra_servicos_sinapi_foreign (sinapi_id),
  CONSTRAINT obra_servicos_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_servicos_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL,
  CONSTRAINT obra_servicos_fornecedor_id_foreign FOREIGN KEY (fornecedor_id) REFERENCES obra_fornecedores(id) ON DELETE SET NULL,
  CONSTRAINT obra_servicos_sinapi_id_foreign FOREIGN KEY (sinapi_id) REFERENCES sinapi_catalogo(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_equipamentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL,
  sinapi_id BIGINT UNSIGNED NULL DEFAULT NULL,
  nome VARCHAR(180) NOT NULL,
  unidade VARCHAR(30) NOT NULL DEFAULT 'h',
  quantidade_prevista DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  quantidade_real DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  valor_unitario_previsto DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  valor_unitario_real DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('Previsto','Cotando','Contratado','Em uso','Devolvido','Cancelado') NOT NULL DEFAULT 'Previsto',
  data_inicio DATE NULL DEFAULT NULL,
  data_fim DATE NULL DEFAULT NULL,
  observacoes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_equipamentos_obra_status_index (obra_id, status),
  KEY obra_equipamentos_item_foreign (item_id),
  KEY obra_equipamentos_fornecedor_foreign (fornecedor_id),
  KEY obra_equipamentos_sinapi_foreign (sinapi_id),
  CONSTRAINT obra_equipamentos_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_equipamentos_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL,
  CONSTRAINT obra_equipamentos_fornecedor_id_foreign FOREIGN KEY (fornecedor_id) REFERENCES obra_fornecedores(id) ON DELETE SET NULL,
  CONSTRAINT obra_equipamentos_sinapi_id_foreign FOREIGN KEY (sinapi_id) REFERENCES sinapi_catalogo(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_movimentos_financeiros (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL DEFAULT NULL,
  fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL,
  tipo ENUM('Entrada','Saida') NOT NULL DEFAULT 'Saida',
  categoria ENUM('Material','Servico','Equipamento','Mao de obra','Taxa','Doacao','Outro') NOT NULL DEFAULT 'Outro',
  descricao VARCHAR(220) NOT NULL,
  valor DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  data_movimento DATE NOT NULL,
  vencimento DATE NULL DEFAULT NULL,
  status ENUM('Previsto','Comprometido','Pago','Recebido','Cancelado') NOT NULL DEFAULT 'Previsto',
  forma_pagamento VARCHAR(80) NULL DEFAULT NULL,
  documento VARCHAR(120) NULL DEFAULT NULL,
  observacoes TEXT NULL,
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_movimentos_obra_data_index (obra_id, data_movimento),
  KEY obra_movimentos_status_index (status),
  KEY obra_movimentos_item_foreign (item_id),
  KEY obra_movimentos_fornecedor_foreign (fornecedor_id),
  KEY obra_movimentos_created_by_foreign (created_by),
  CONSTRAINT obra_movimentos_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_movimentos_item_id_foreign FOREIGN KEY (item_id) REFERENCES obra_itens(id) ON DELETE SET NULL,
  CONSTRAINT obra_movimentos_fornecedor_id_foreign FOREIGN KEY (fornecedor_id) REFERENCES obra_fornecedores(id) ON DELETE SET NULL,
  CONSTRAINT obra_movimentos_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS obra_diarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obra_id BIGINT UNSIGNED NOT NULL,
  data_registro DATE NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  relato TEXT NOT NULL,
  clima VARCHAR(80) NULL DEFAULT NULL,
  equipe_presente VARCHAR(255) NULL DEFAULT NULL,
  impedimentos TEXT NULL,
  created_by BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY obra_diarios_obra_data_index (obra_id, data_registro),
  KEY obra_diarios_created_by_foreign (created_by),
  CONSTRAINT obra_diarios_obra_id_foreign FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
  CONSTRAINT obra_diarios_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_pessoas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  cpf CHAR(11) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_pessoas_cpf_unique (cpf),
  KEY com_pessoas_nome_index (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_comissoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(160) NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  situacao ENUM('aguardando','iniciada','finalizada') NOT NULL DEFAULT 'aguardando',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY com_comissoes_situacao_periodo_index (situacao, data_inicio, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_cargos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(180) NOT NULL,
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_cargos_nome_unique (nome),
  KEY com_cargos_status_nome_index (status, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS com_comissao_cargos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_id BIGINT UNSIGNED NOT NULL,
  cargo_id BIGINT UNSIGNED NOT NULL,
  ordem INT UNSIGNED NOT NULL DEFAULT 0,
  votacao_status ENUM('aberta','encerrada') NOT NULL DEFAULT 'aberta',
  votacao_encerrada_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_comissao_cargos_unique (comissao_id, cargo_id),
  KEY com_comissao_cargos_cargo_index (cargo_id),
  KEY com_comissao_cargos_ordem_index (comissao_id, ordem),
  CONSTRAINT com_comissao_cargos_comissao_fk FOREIGN KEY (comissao_id) REFERENCES com_comissoes(id) ON DELETE CASCADE,
  CONSTRAINT com_comissao_cargos_cargo_fk FOREIGN KEY (cargo_id) REFERENCES com_cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_votacao_pessoas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_cargo_id BIGINT UNSIGNED NOT NULL,
  pessoa_id BIGINT UNSIGNED NOT NULL,
  votos INT UNSIGNED NOT NULL DEFAULT 0,
  papel ENUM('votado','associado','candidato','secretario') NOT NULL DEFAULT 'candidato',
  origem ENUM('admin','indicacao') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_votacao_pessoas_unique (comissao_cargo_id, pessoa_id),
  KEY com_votacao_pessoas_pessoa_index (pessoa_id),
  KEY com_votacao_pessoas_papel_index (comissao_cargo_id, papel),
  CONSTRAINT com_votacao_pessoas_cargo_fk FOREIGN KEY (comissao_cargo_id) REFERENCES com_comissao_cargos(id) ON DELETE CASCADE,
  CONSTRAINT com_votacao_pessoas_pessoa_fk FOREIGN KEY (pessoa_id) REFERENCES com_pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_voto (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comissao_cargo_id BIGINT UNSIGNED NOT NULL,
  status ENUM('aguardando','indicacao','votando','encerrado') NOT NULL DEFAULT 'aguardando',
  limite_indicacoes TINYINT UNSIGNED NOT NULL DEFAULT 1,
  limite_votos TINYINT UNSIGNED NOT NULL DEFAULT 1,
  indicacoes_abertas_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_voto_comissao_cargo_unique (comissao_cargo_id),
  KEY com_voto_status_index (status),
  CONSTRAINT com_voto_comissao_cargo_fk FOREIGN KEY (comissao_cargo_id) REFERENCES com_comissao_cargos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS com_votacao_membros (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  voto_id BIGINT UNSIGNED NOT NULL,
  comissao_membro_id BIGINT UNSIGNED NOT NULL,
  confirmado_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_votacao_membros_voto_membro_unique (voto_id, comissao_membro_id),
  KEY com_votacao_membros_membro_index (comissao_membro_id),
  CONSTRAINT com_votacao_membros_voto_fk FOREIGN KEY (voto_id) REFERENCES com_voto(id) ON DELETE CASCADE,
  CONSTRAINT com_votacao_membros_membro_fk FOREIGN KEY (comissao_membro_id) REFERENCES com_comissao_membros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS com_votacao_escolhas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  votacao_membro_id BIGINT UNSIGNED NOT NULL,
  pessoa_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY com_votacao_escolhas_pessoa_unique (votacao_membro_id, pessoa_id),
  KEY com_votacao_escolhas_pessoa_index (pessoa_id),
  CONSTRAINT com_votacao_escolhas_membro_fk FOREIGN KEY (votacao_membro_id) REFERENCES com_votacao_membros(id) ON DELETE CASCADE,
  CONSTRAINT com_votacao_escolhas_pessoa_fk FOREIGN KEY (pessoa_id) REFERENCES com_pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
