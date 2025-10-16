-- =====================================================================
-- Database & defaults
-- =====================================================================
CREATE DATABASE IF NOT EXISTS rhyjjbm0_wpew_cntrctr_01
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE rhyjjbm0_wpew_cntrctr_01;

-- For timestamp consistency
SET time_zone = '+00:00';

-- =====================================================================
-- Utility tables
-- =====================================================================

-- 1) User Types (roles)
DROP TABLE IF EXISTS user_types;
CREATE TABLE user_types (
  user_type_id CHAR(3) PRIMARY KEY,         -- ADM | SOP | CON
  user_type_name VARCHAR(50) NOT NULL,      -- ADMINISTRATOR | CUSTOMER SUPPORT | CONTRACTOR
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_types (user_type_id, user_type_name) VALUES
  ('ADM','ADMINISTRATOR'),
  ('SOP','CUSTOMER SUPPORT'),
  ('CON','CONTRACTOR');

-- 2) Sequences for alphanumeric user_id with prefix per role
DROP TABLE IF EXISTS user_id_sequence;
CREATE TABLE user_id_sequence (
  user_type_id CHAR(3) PRIMARY KEY,         -- ADM | SOP | CON
  prefix CHAR(1) NOT NULL,                  -- A | S | C
  last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_seq_user_type FOREIGN KEY (user_type_id) REFERENCES user_types(user_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_id_sequence (user_type_id, prefix, last_number) VALUES
  ('ADM','A',0),
  ('SOP','S',0),
  ('CON','C',0);

-- 3) USA States (names in Spanish as requested, stored uppercase)
DROP TABLE IF EXISTS usa_states;
CREATE TABLE usa_states (
  state_code CHAR(2) PRIMARY KEY,
  state_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO usa_states (state_code, state_name) VALUES
('AK','ALASKA'),('AZ','ARIZONA'),('AR','ARKANSAS'),('CA','CALIFORNIA'),('CO','COLORADO'),
('CT','CONNECTICUT'),('DE','DELAWARE'),('FL','FLORIDA'),('GA','GEORGIA'),('ID','IDAHO'),
('IL','ILLINOIS'),('IN','INDIANA'),('IA','IOWA'),('KS','KANSAS'),('KY','KENTUCKY'),
('LA','LUISIANA'),('ME','MAINE'),('MD','MARYLAND'),('MA','MASSACHUSETTS'),('MI','MICHIGAN'),
('MN','MINNESOTA'),('MS','MISISIPI'),('MO','MISURI'),('MT','MONTANA'),('NE','NEBRASKA'),
('NV','NEVADA'),('NH','NUEVO HAMPSHIRE'),('NJ','NUEVO JERSEY'),('NM','NUEVO MÉXICO'),
('NY','NUEVA YORK'),('NC','CAROLINA DEL NORTE'),('ND','DAKOTA DEL NORTE'),('OH','OHIO'),
('OK','OKLAHOMA'),('OR','OREGÓN'),('PA','PENSILVANIA'),('RI','RHODE ISLAND'),
('SC','CAROLINA DEL SUR'),('SD','DAKOTA DEL SUR'),('TN','TENNESSEE'),('TX','TEXAS'),
('UT','UTAH'),('VT','VERMONT'),('VA','VIRGINIA'),('WA','WASHINGTON'),
('WV','VIRGINIA OCCIDENTAL'),('WI','WISCONSIN'),('WY','WYOMING');

-- =====================================================================
-- Core: Users, Details, Sessions, Contractors
-- =====================================================================

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  user_id VARCHAR(16) PRIMARY KEY,                     -- generated: A000000001 / S000000001 / C000000001
  email VARCHAR(320) NOT NULL,
  username VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,                 -- hash (bcrypt/argon)
  user_type CHAR(3) NOT NULL,                          -- FK → user_types
  is_active TINYINT(1) NOT NULL DEFAULT 0,             -- TRUE/FALSE
  approved_by VARCHAR(16) NULL,                        -- FK → users.user_id (who approved, if applies)
  email_verified_at DATETIME NULL,
  reset_password_token CHAR(64) NULL,
  reset_password_expires_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_type (user_type),
  CONSTRAINT fk_users_type FOREIGN KEY (user_type) REFERENCES user_types(user_type_id),
  CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS user_details;
CREATE TABLE user_details (
  user_id VARCHAR(16) PRIMARY KEY,                     -- 1:1 with users
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  phone_number VARCHAR(30) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS sessions;
CREATE TABLE sessions (
  session_id CHAR(128) PRIMARY KEY,
  user_id VARCHAR(16) NOT NULL,
  session_token CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NOT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  KEY idx_sessions_user_seen (user_id, last_seen_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS contractors;
CREATE TABLE contractors (
  contractor_id VARCHAR(16) PRIMARY KEY,               -- PK = FK → users.user_id (1:1)
  company_name VARCHAR(191) NOT NULL,
  cac_license_number VARCHAR(100) NOT NULL,
  address VARCHAR(191) NOT NULL,
  address_2 VARCHAR(191) NULL,
  city VARCHAR(100) NOT NULL,
  state_code CHAR(2) NOT NULL,
  zip_code VARCHAR(20) NOT NULL,
  approved_at DATETIME NULL,
  approval_token CHAR(64) NULL,
  approval_token_expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_contractor_license (cac_license_number),
  KEY idx_contractor_active (is_active),
  CONSTRAINT fk_contractor_user FOREIGN KEY (contractor_id) REFERENCES users(user_id),
  CONSTRAINT fk_contractor_state FOREIGN KEY (state_code) REFERENCES usa_states(state_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Products & Models
-- =====================================================================

DROP TABLE IF EXISTS product_types;
CREATE TABLE product_types (
  product_type_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_type_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_type_name (product_type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS models;
CREATE TABLE models (
  model_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_type_id SMALLINT UNSIGNED NOT NULL,
  model_number VARCHAR(100) NOT NULL,         -- previously "serie"
  brand VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_model_product_type FOREIGN KEY (product_type_id) REFERENCES product_types(product_type_id),
  UNIQUE KEY uq_brand_model (brand, model_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Serial Pool (serial validation)
-- =====================================================================

DROP TABLE IF EXISTS serial_number_validation;
CREATE TABLE serial_number_validation (
  item_model BIGINT UNSIGNED NOT NULL,                 -- FK → models.model_id
  item_serial_number VARCHAR(100) NOT NULL,
  manufacturing_date DATE NULL,                        -- corrected name
  source ENUM('AUTOMATIC UPLOAD','MANUAL UPLOAD') NOT NULL DEFAULT 'AUTOMATIC UPLOAD',
  who_added VARCHAR(16) NULL,                          -- FK → users.user_id
  sn_proof_url VARCHAR(255) NULL,
  status ENUM('ACTIVATE','DESACTIVATE') NOT NULL DEFAULT 'ACTIVATE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_model, item_serial_number),
  KEY idx_snv_status_source (status, source),
  CONSTRAINT fk_snv_model FOREIGN KEY (item_model) REFERENCES models(model_id),
  CONSTRAINT fk_snv_who_added FOREIGN KEY (who_added) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Warranties
-- =====================================================================

DROP TABLE IF EXISTS warranty_records;
CREATE TABLE warranty_records (
  warranty_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Owner (end customer)
  owner_first_name VARCHAR(100) NOT NULL,
  owner_last_name  VARCHAR(100) NOT NULL,
  owner_email VARCHAR(320) NULL,
  owner_phone_number VARCHAR(30) NULL,

  -- Billing address
  billing_address  VARCHAR(191) NOT NULL,
  billing_address_2 VARCHAR(191) NULL,
  billing_city     VARCHAR(100) NOT NULL,
  billing_state_code CHAR(2) NOT NULL,
  billing_zip_code VARCHAR(20) NOT NULL,

  -- Installation address
  installation_address  VARCHAR(191) NOT NULL,
  installation_address_2 VARCHAR(191) NULL,
  installation_city     VARCHAR(100) NOT NULL,
  installation_state_code CHAR(2) NOT NULL,
  installation_zip_code VARCHAR(20) NOT NULL,

  -- Equipment (fixed 2-piece design)
  outdoor_model_id BIGINT UNSIGNED NULL,              -- FK → models
  outdoor_serial_number VARCHAR(100) NULL,
  indoor_model_id BIGINT UNSIGNED NULL,               -- FK → models
  indoor_serial_number VARCHAR(100) NULL,

  purchased_date DATE NOT NULL,
  invoice_number VARCHAR(100) NULL,

  proof_purchase_url VARCHAR(255) NULL,
  certificate_url VARCHAR(255) NULL,

  status ENUM('ACTIVATE','VOID','EXPIRED') NOT NULL DEFAULT 'ACTIVATE',

  contractor_id VARCHAR(16) NOT NULL,                 -- FK → contractors
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Practical indexes & constraints
  KEY idx_warranty_contractor_created (contractor_id, created_at),
  KEY idx_warranty_owner_last_name (owner_last_name),
  KEY idx_warranty_purchased_date (purchased_date),
  KEY idx_warranty_outdoor_model (outdoor_model_id),
  KEY idx_warranty_indoor_model (indoor_model_id),

  -- Avoid duplicates per contractor (note: UNIQUE ignores NULL; complement in app if many NULLs)
  UNIQUE KEY uq_outdoor_combo (contractor_id, outdoor_model_id, outdoor_serial_number),
  UNIQUE KEY uq_indoor_combo  (contractor_id, indoor_model_id, indoor_serial_number),

  CONSTRAINT fk_warranty_b_state FOREIGN KEY (billing_state_code) REFERENCES usa_states(state_code),
  CONSTRAINT fk_warranty_i_state FOREIGN KEY (installation_state_code) REFERENCES usa_states(state_code),
  CONSTRAINT fk_warranty_outdoor_model FOREIGN KEY (outdoor_model_id) REFERENCES models(model_id),
  CONSTRAINT fk_warranty_indoor_model  FOREIGN KEY (indoor_model_id)  REFERENCES models(model_id),
  CONSTRAINT fk_warranty_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(contractor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Audit Logs (plural)
-- =====================================================================

DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id VARCHAR(16) NULL,                      -- FK → users
  entity_type VARCHAR(50) NOT NULL,                    -- 'user' | 'contractor' | 'warranty' | 'serial' | ...
  entity_id BIGINT UNSIGNED NULL,                      -- or VARCHAR if pointing to users
  action VARCHAR(50) NOT NULL,                         -- 'approve' | 'upload_proof' | 'generate_certificate' | ...
  data_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity_type, entity_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Triggers
-- =====================================================================

DELIMITER $$

-- Generate users.user_id with prefix per user_type
DROP TRIGGER IF EXISTS trg_users_before_insert $$
CREATE TRIGGER trg_users_before_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_prefix CHAR(1);
  DECLARE v_next BIGINT UNSIGNED;

  -- Prefix by type
  SELECT prefix INTO v_prefix
  FROM user_id_sequence
  WHERE user_type_id = NEW.user_type
  FOR UPDATE;

  IF v_prefix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user_type for user_id generation';
  END IF;

  -- Increment sequence atomically
  UPDATE user_id_sequence
    SET last_number = last_number + 1
  WHERE user_type_id = NEW.user_type;

  SELECT last_number INTO v_next
  FROM user_id_sequence
  WHERE user_type_id = NEW.user_type;

  -- Build ID: Letter + 9 digits left-padded with zeros
  SET NEW.user_id = CONCAT(v_prefix, LPAD(v_next, 9, '0'));

  -- Minimal hash validation (bcrypt usually >= 60 chars)
  IF CHAR_LENGTH(NEW.password_hash) < 60 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'password_hash too short (must be a secure hash)';
  END IF;

  -- Do NOT uppercase username or email
END $$

-- No uppercase on update for username/email either (so no BEFORE UPDATE trigger on users)

-- Uppercase for contractors (business fields only; not tokens/dates)
DROP TRIGGER IF EXISTS trg_contractors_bi $$
CREATE TRIGGER trg_contractors_bi
BEFORE INSERT ON contractors
FOR EACH ROW
BEGIN
  SET NEW.company_name = UPPER(NEW.company_name);
  SET NEW.cac_license_number = UPPER(NEW.cac_license_number);
  SET NEW.address = UPPER(NEW.address);
  SET NEW.address_2 = UPPER(NEW.address_2);
  SET NEW.city = UPPER(NEW.city);
  SET NEW.state_code = UPPER(NEW.state_code);
  SET NEW.zip_code = UPPER(NEW.zip_code);
END $$

DROP TRIGGER IF EXISTS trg_contractors_bu $$
CREATE TRIGGER trg_contractors_bu
BEFORE UPDATE ON contractors
FOR EACH ROW
BEGIN
  SET NEW.company_name = UPPER(NEW.company_name);
  SET NEW.cac_license_number = UPPER(NEW.cac_license_number);
  SET NEW.address = UPPER(NEW.address);
  SET NEW.address_2 = UPPER(NEW.address_2);
  SET NEW.city = UPPER(NEW.city);
  SET NEW.state_code = UPPER(NEW.state_code);
  SET NEW.zip_code = UPPER(NEW.zip_code);
END $$

-- Uppercase for user_details (names)
DROP TRIGGER IF EXISTS trg_user_details_bi $$
CREATE TRIGGER trg_user_details_bi
BEFORE INSERT ON user_details
FOR EACH ROW
BEGIN
  SET NEW.first_name = UPPER(NEW.first_name);
  SET NEW.last_name  = UPPER(NEW.last_name);
END $$

DROP TRIGGER IF EXISTS trg_user_details_bu $$
CREATE TRIGGER trg_user_details_bu
BEFORE UPDATE ON user_details
FOR EACH ROW
BEGIN
  SET NEW.first_name = UPPER(NEW.first_name);
  SET NEW.last_name  = UPPER(NEW.last_name);
END $$

-- Uppercase for warranty_records (except emails and URLs)
DROP TRIGGER IF EXISTS trg_warranty_bi $$
CREATE TRIGGER trg_warranty_bi
BEFORE INSERT ON warranty_records
FOR EACH ROW
BEGIN
  SET NEW.owner_first_name = UPPER(NEW.owner_first_name);
  SET NEW.owner_last_name  = UPPER(NEW.owner_last_name);
  -- owner_email stays as-is
  SET NEW.billing_address  = UPPER(NEW.billing_address);
  SET NEW.billing_address_2= UPPER(NEW.billing_address_2);
  SET NEW.billing_city     = UPPER(NEW.billing_city);
  SET NEW.billing_state_code = UPPER(NEW.billing_state_code);
  SET NEW.billing_zip_code = UPPER(NEW.billing_zip_code);

  SET NEW.installation_address  = UPPER(NEW.installation_address);
  SET NEW.installation_address_2= UPPER(NEW.installation_address_2);
  SET NEW.installation_city     = UPPER(NEW.installation_city);
  SET NEW.installation_state_code = UPPER(NEW.installation_state_code);
  SET NEW.installation_zip_code = UPPER(NEW.installation_zip_code);

  SET NEW.invoice_number = UPPER(NEW.invoice_number);
  SET NEW.status = UPPER(NEW.status);
END $$

DROP TRIGGER IF EXISTS trg_warranty_bu $$
CREATE TRIGGER trg_warranty_bu
BEFORE UPDATE ON warranty_records
FOR EACH ROW
BEGIN
  SET NEW.owner_first_name = UPPER(NEW.owner_first_name);
  SET NEW.owner_last_name  = UPPER(NEW.owner_last_name);
  SET NEW.billing_address  = UPPER(NEW.billing_address);
  SET NEW.billing_address_2= UPPER(NEW.billing_address_2);
  SET NEW.billing_city     = UPPER(NEW.billing_city);
  SET NEW.billing_state_code = UPPER(NEW.billing_state_code);
  SET NEW.billing_zip_code = UPPER(NEW.billing_zip_code);

  SET NEW.installation_address  = UPPER(NEW.installation_address);
  SET NEW.installation_address_2= UPPER(NEW.installation_address_2);
  SET NEW.installation_city     = UPPER(NEW.installation_city);
  SET NEW.installation_state_code = UPPER(NEW.installation_state_code);
  SET NEW.installation_zip_code = UPPER(NEW.installation_zip_code);

  SET NEW.invoice_number = UPPER(NEW.invoice_number);
  SET NEW.status = UPPER(NEW.status);
END $$

DELIMITER ;

-- =====================================================================
-- Seeds: first ADMIN user (Carlos Avila)
-- =====================================================================

-- Bcrypt hash for "Password123!!"
-- $2b$12$xBh5XUc2lxm158gDgeb9Ue6AwLXiHtUEwF.56/WFK3xC/P4QvaGX.
SET @admin_email := 'carlos.avila@everwellparts.com';
SET @admin_username := 'carlos.avila@everwellparts.com';
SET @admin_hash := '$2b$12$xBh5XUc2lxm158gDgeb9Ue6AwLXiHtUEwF.56/WFK3xC/P4QvaGX.';

INSERT INTO users (email, username, password_hash, user_type, is_active, approved_by, email_verified_at)
VALUES (@admin_email, @admin_username, @admin_hash, 'ADM', 1, NULL, NOW());

SET @admin_user_id := (SELECT user_id FROM users WHERE email=@admin_email);

INSERT INTO user_details (user_id, first_name, last_name, phone_number)
VALUES (@admin_user_id, 'CARLOS', 'AVILA', '7863283345');

-- Approve self as first admin (optional)
UPDATE users
SET approved_by = @admin_user_id
WHERE user_id = @admin_user_id;

-- Audit log record
INSERT INTO audit_logs (actor_user_id, entity_type, entity_id, action, data_json, created_at)
VALUES (@admin_user_id, 'user', 0, 'create_admin', JSON_OBJECT('email', @admin_email), NOW());
