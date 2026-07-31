-- =============================================================================
-- Barba Flow — schema de UMA barbearia
-- =============================================================================
-- Escala: 1 banco MySQL por barbearia (ex.: barbaflow, bf_suprema, bf_goiania).
-- Assim a base de Goiânia nunca mistura com a de outra cidade.
--
-- Como usar no phpMyAdmin:
-- 1) Selecione o banco da loja (ex.: barbaflow)
-- 2) Aba SQL → cole este arquivo → Executar
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- Usuários: dono | barbeiro | cliente  (login e cadastro)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('dono','barbeiro','cliente') NOT NULL,
  avatar VARCHAR(255) NULL,
  birth_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email_role (email, role),
  KEY idx_usuarios_role (role),
  KEY idx_usuarios_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atalhos (visões) pedidos: dono / barbeiros / clientes
DROP VIEW IF EXISTS donos;
DROP VIEW IF EXISTS barbeiros;
DROP VIEW IF EXISTS clientes;

CREATE VIEW donos AS
  SELECT * FROM usuarios WHERE role = 'dono';

CREATE VIEW barbeiros AS
  SELECT * FROM usuarios WHERE role = 'barbeiro';

CREATE VIEW clientes AS
  SELECT * FROM usuarios WHERE role = 'cliente';

-- Configurações / marca da loja (1 linha)
CREATE TABLE IF NOT EXISTS configuracoes (
  id INT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  shop_name VARCHAR(120) NOT NULL DEFAULT 'Minha Barbearia',
  slug VARCHAR(80) NOT NULL DEFAULT 'minha-barbearia',
  phone VARCHAR(32) NULL,
  address VARCHAR(255) NULL,
  instagram VARCHAR(255) NULL,
  maps_url VARCHAR(500) NULL,
  logo_url VARCHAR(255) NULL,
  primary_color VARCHAR(20) NOT NULL DEFAULT '#11172f',
  accent_color VARCHAR(20) NOT NULL DEFAULT '#c9a227',
  open_time CHAR(5) NOT NULL DEFAULT '08:00',
  close_time CHAR(5) NOT NULL DEFAULT '20:00',
  lunch_start CHAR(5) NOT NULL DEFAULT '12:00',
  lunch_end CHAR(5) NOT NULL DEFAULT '13:00',
  slot_minutes INT NOT NULL DEFAULT 60,
  lunch_enabled TINYINT(1) NOT NULL DEFAULT 1,
  commission_rate DECIMAL(6,4) NOT NULL DEFAULT 0.4000,
  setup_completed TINYINT(1) NOT NULL DEFAULT 0,
  mp_public_key VARCHAR(255) NULL,
  mp_access_token VARCHAR(255) NULL,
  wa_phone_number_id VARCHAR(64) NULL,
  wa_access_token VARCHAR(255) NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serviços do catálogo
CREATE TABLE IF NOT EXISTS servicos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  duration_min INT NOT NULL DEFAULT 30,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  image_url VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produtos / estoque
CREATE TABLE IF NOT EXISTS produtos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  sku VARCHAR(40) NULL,
  qty INT NOT NULL DEFAULT 0,
  min_qty INT NOT NULL DEFAULT 0,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_produtos_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos_historico (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(120) NOT NULL,
  qty INT NOT NULL,
  delta INT NOT NULL,
  type VARCHAR(32) NOT NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ph_product (product_id),
  KEY idx_ph_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda
CREATE TABLE IF NOT EXISTS agendamentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NULL,
  client_name VARCHAR(120) NULL,
  client_phone VARCHAR(32) NULL,
  barber_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NULL,
  service_ids JSON NULL,
  products_json JSON NULL,
  date DATE NOT NULL,
  time CHAR(5) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'agendado',
  notes TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ag_barber_date (barber_id, date),
  KEY idx_ag_date (date),
  KEY idx_ag_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caixa
CREATE TABLE IF NOT EXISTS caixa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('entrada','saida') NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'servico',
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  appointment_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_caixa_apt (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Planos / assinaturas / pagamentos
CREATE TABLE IF NOT EXISTS planos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  `interval` VARCHAR(40) NOT NULL DEFAULT 'Mês',
  headline VARCHAR(255) NULL,
  description TEXT NULL,
  benefit_label VARCHAR(120) NULL,
  usage_limit INT NULL,
  images JSON NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assinaturas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobrancas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cartoes_cliente (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Marketing / fidelidade / metas / avisos
CREATE TABLE IF NOT EXISTS campanhas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fidelidade (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avisos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linha inicial de configuração (obrigatória)
INSERT INTO configuracoes (id, shop_name, slug, setup_completed)
VALUES (1, 'Minha Barbearia', 'minha-barbearia', 0)
ON DUPLICATE KEY UPDATE id = id;
