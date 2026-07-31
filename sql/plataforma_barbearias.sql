-- =============================================================================
-- Barba Flow — cadastro de barbearias (escala / multi-loja no mesmo servidor)
-- =============================================================================
-- Rode UMA vez em um banco "maestro" (pode ser o próprio barbaflow no começo).
-- Cada loja nova = criar outro banco MySQL + importar barbearia_schema.sql nele.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS barbearias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  db_host VARCHAR(120) NOT NULL DEFAULT 'localhost',
  db_name VARCHAR(64) NOT NULL,
  db_user VARCHAR(64) NOT NULL,
  db_pass VARCHAR(255) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_barbearias_slug (slug),
  UNIQUE KEY uq_barbearias_db (db_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemplo da loja atual (ajuste senha/usuário do painel da hospedagem):
-- INSERT INTO barbearias (nome, slug, db_name, db_user, db_pass)
-- VALUES ('SUPREMA', 'suprema', 'barbaflow', 'barbaflow', 'SUA_SENHA')
-- ON DUPLICATE KEY UPDATE nome = VALUES(nome);
