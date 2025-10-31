USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) status enum (reemplaza is_active)
ALTER TABLE users
  DROP COLUMN is_active,
  ADD COLUMN status ENUM('PENDING','ACTIVE','INACTIVE','REJECTED') NOT NULL DEFAULT 'PENDING' AFTER contractor_id;

-- 2) verificación de email (nuevos campos)
ALTER TABLE users
  ADD COLUMN email_verification_token CHAR(64) NULL AFTER status,
  ADD COLUMN email_verification_expires_at DATETIME NULL AFTER email_verification_token;

-- 3) aprobación/rechazo (nuevos campos)
ALTER TABLE users
  ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
  ADD COLUMN rejected_by VARCHAR(16) NULL AFTER approved_at,
  ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by,
  ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER rejected_at;

-- 3.1) FK para rejected_by → users(user_id)
ALTER TABLE users
  ADD CONSTRAINT fk_users_rejected_by
    FOREIGN KEY (rejected_by) REFERENCES users(user_id);

-- 4) Reordenar columnas al orden solicitado
ALTER TABLE users
  MODIFY COLUMN user_id VARCHAR(16) NOT NULL FIRST,
  MODIFY COLUMN email VARCHAR(320) NOT NULL AFTER user_id,
  MODIFY COLUMN username VARCHAR(191) NOT NULL AFTER email,
  MODIFY COLUMN password_hash VARCHAR(255) NOT NULL AFTER username,
  MODIFY COLUMN user_type CHAR(3) NOT NULL AFTER password_hash,
  MODIFY COLUMN contractor_id BIGINT UNSIGNED NULL AFTER user_type,
  MODIFY COLUMN status ENUM('PENDING','ACTIVE','INACTIVE','REJECTED') NOT NULL DEFAULT 'PENDING' AFTER contractor_id,
  MODIFY COLUMN email_verification_token CHAR(64) NULL AFTER status,
  MODIFY COLUMN email_verification_expires_at DATETIME NULL AFTER email_verification_token,
  MODIFY COLUMN email_verified_at DATETIME NULL AFTER email_verification_expires_at,
  MODIFY COLUMN approved_by VARCHAR(16) NULL AFTER email_verified_at,
  MODIFY COLUMN approved_at DATETIME NULL AFTER approved_by,
  MODIFY COLUMN rejected_by VARCHAR(16) NULL AFTER approved_at,
  MODIFY COLUMN rejected_at DATETIME NULL AFTER rejected_by,
  MODIFY COLUMN rejection_reason VARCHAR(255) NULL AFTER rejected_at,
  MODIFY COLUMN reset_password_token CHAR(64) NULL AFTER rejection_reason,
  MODIFY COLUMN reset_password_expires_at DATETIME NULL AFTER reset_password_token,
  MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER reset_password_expires_at,
  MODIFY COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

SET FOREIGN_KEY_CHECKS = 1;

-- 5) Trigger: copiar email → username si viene vacío/NULL
DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_copy_email_to_username_bi $$
CREATE TRIGGER trg_users_copy_email_to_username_bi
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  IF NEW.username IS NULL OR CHAR_LENGTH(TRIM(NEW.username)) = 0 THEN
    SET NEW.username = NEW.email;
  END IF;
END $$
DELIMITER ;

-- ------------------------------------------------------------------------------
USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Quitar campos que ya no se usan y reemplazar is_active por status
ALTER TABLE contractors
  DROP COLUMN is_active,
  DROP COLUMN approval_token,
  DROP COLUMN approval_token_expires_at,
  ADD COLUMN status ENUM('ACTIVE','INACTIVE','PENDING','REJECTED') NOT NULL DEFAULT 'PENDING' AFTER zip_code;

-- 2) Agregar campos de aprobación, rechazo y merge
ALTER TABLE contractors
  ADD COLUMN approved_by VARCHAR(16) NULL AFTER status,
  ADD COLUMN rejected_by VARCHAR(16) NULL AFTER approved_by,
  ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by,
  ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER rejected_at,
  ADD COLUMN merge_by VARCHAR(16) NULL AFTER rejection_reason,
  ADD COLUMN merge_at DATETIME NULL AFTER merge_by;

-- 3) Agregar FKs a users(user_id) para approved_by, rejected_by, merge_by
--    (si existen constraints con esos nombres, se pueden dropear antes)
ALTER TABLE contractors
  ADD CONSTRAINT fk_contractors_approved_by
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
  ADD CONSTRAINT fk_contractors_rejected_by
    FOREIGN KEY (rejected_by) REFERENCES users(user_id),
  ADD CONSTRAINT fk_contractors_merge_by
    FOREIGN KEY (merge_by) REFERENCES users(user_id);

-- 4) Reordenar columnas al orden solicitado
ALTER TABLE contractors
  MODIFY COLUMN contractor_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  MODIFY COLUMN cac_license_number VARCHAR(100) NOT NULL AFTER contractor_id,
  MODIFY COLUMN company_name VARCHAR(191) NOT NULL AFTER cac_license_number,
  MODIFY COLUMN company_phone VARCHAR(30) NULL AFTER company_name,
  MODIFY COLUMN company_email VARCHAR(320) NULL AFTER company_phone,
  MODIFY COLUMN company_website VARCHAR(191) NULL AFTER company_email,
  MODIFY COLUMN address VARCHAR(191) NOT NULL AFTER company_website,
  MODIFY COLUMN address_2 VARCHAR(191) NULL AFTER address,
  MODIFY COLUMN city VARCHAR(100) NOT NULL AFTER address_2,
  MODIFY COLUMN state_code CHAR(2) NOT NULL AFTER city,
  MODIFY COLUMN zip_code VARCHAR(20) NOT NULL AFTER state_code,
  MODIFY COLUMN status ENUM('ACTIVE','INACTIVE','PENDING','REJECTED') NOT NULL DEFAULT 'PENDING' AFTER zip_code,
  MODIFY COLUMN approved_by VARCHAR(16) NULL AFTER status,
  MODIFY COLUMN approved_at DATETIME NULL AFTER approved_by,
  MODIFY COLUMN rejected_by VARCHAR(16) NULL AFTER approved_at,
  MODIFY COLUMN rejected_at DATETIME NULL AFTER rejected_by,
  MODIFY COLUMN rejection_reason VARCHAR(255) NULL AFTER rejected_at,
  MODIFY COLUMN merge_by VARCHAR(16) NULL AFTER rejection_reason,
  MODIFY COLUMN merge_at DATETIME NULL AFTER merge_by,
  MODIFY COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER merge_at,
  MODIFY COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

SET FOREIGN_KEY_CHECKS = 1;

