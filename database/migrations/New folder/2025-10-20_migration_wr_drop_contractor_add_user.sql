-- =====================================================================
-- Migration: warranty_records -> drop contractor columns; add user_id FK
-- Target DB: rhyjjbm0_wpew_cntrctr_01 (MySQL 5.7-compatible, safe checks)
-- This script is idempotent: it checks existence before dropping/adding.
-- =====================================================================

USE rhyjjbm0_wpew_cntrctr_01;

DELIMITER $$
DROP PROCEDURE IF EXISTS wr_fk_to_users $$
CREATE PROCEDURE wr_fk_to_users()
BEGIN
  DECLARE v_cnt INT;
  DECLARE v_fk VARCHAR(128);

  -- 1) Drop any FK that references contractor columns (if any)
  SELECT CONSTRAINT_NAME
    INTO v_fk
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'warranty_records'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND COLUMN_NAME IN ('contractor_id','contractor_user_id')
  LIMIT 1;

  IF v_fk IS NOT NULL THEN
    SET @sql := CONCAT('ALTER TABLE warranty_records DROP FOREIGN KEY ', v_fk);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  -- 2) Drop contractor_id if it exists
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'warranty_records'
    AND COLUMN_NAME = 'contractor_id';
  IF v_cnt > 0 THEN
    SET @sql := 'ALTER TABLE warranty_records DROP COLUMN contractor_id';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  -- 3) Drop contractor_user_id if it exists
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'warranty_records'
    AND COLUMN_NAME = 'contractor_user_id';
  IF v_cnt > 0 THEN
    SET @sql := 'ALTER TABLE warranty_records DROP COLUMN contractor_user_id';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  -- 4) Add user_id (if not present)
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'warranty_records'
    AND COLUMN_NAME = 'user_id';
  IF v_cnt = 0 THEN
    SET @sql := "ALTER TABLE warranty_records ADD COLUMN user_id VARCHAR(16) NOT NULL AFTER status";
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  -- 5) Add FK to users(user_id) (drop if duplicate name)
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_warranty_user';

  IF v_cnt > 0 THEN
    SET @sql := 'ALTER TABLE warranty_records DROP FOREIGN KEY fk_warranty_user';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  SET @sql := 'ALTER TABLE warranty_records ADD CONSTRAINT fk_warranty_user FOREIGN KEY (user_id) REFERENCES users(user_id)';
  PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

  -- 6) Create index for user/listing if not present
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'warranty_records'
    AND INDEX_NAME = 'idx_warranty_user_created';
  IF v_cnt = 0 THEN
    SET @sql := 'CREATE INDEX idx_warranty_user_created ON warranty_records (user_id, created_at)';
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END $$
DELIMITER ;

CALL wr_fk_to_users();
DROP PROCEDURE wr_fk_to_users;

-- =====================================================================
-- END
-- =====================================================================
