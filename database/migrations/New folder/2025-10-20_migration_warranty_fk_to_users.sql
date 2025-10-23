-- =====================================================================
-- Migration: Change warranty_records FK to users + note on password policy
-- Target DB: rhyjjbm0_wpew_cntrctr_01
-- Date: 2025-10-20
-- Summary:
--   * warranty_records.contractor_id will now reference users(user_id)
--   * Safely migrates existing data by mapping each contractor to one user (role CON)
--   * Keeps data integrity with a temporary column and mapping table
--   * NOTE: Password minimum changed to 8 characters (application-level validation).
--           MySQL 5.7 cannot validate plaintext password length in DB; we keep hash checks.
-- =====================================================================

USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

-- 0) Create a mapping contractor (BIGINT) -> one contractor user (VARCHAR user_id)
DROP TABLE IF EXISTS _tmp_contractor_to_user;
CREATE TABLE _tmp_contractor_to_user (
  contractor_id BIGINT UNSIGNED NOT NULL,
  user_id VARCHAR(16) NOT NULL,
  PRIMARY KEY (contractor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Strategy: pick the earliest created user of type CON per contractor_id
INSERT INTO _tmp_contractor_to_user (contractor_id, user_id)
SELECT u.contractor_id, u.user_id
FROM users u
JOIN (
  SELECT contractor_id, MIN(created_at) AS first_created
  FROM users
  WHERE user_type = 'CON' AND contractor_id IS NOT NULL
  GROUP BY contractor_id
) x
  ON x.contractor_id = u.contractor_id AND x.first_created = u.created_at
WHERE u.user_type = 'CON' AND u.contractor_id IS NOT NULL;

-- 1) Prepare new column to hold the user_id we will reference
ALTER TABLE warranty_records
  ADD COLUMN contractor_user_id VARCHAR(16) NULL AFTER contractor_id;

-- 2) Fill new column using the mapping (some rows may not map if no CON user exists yet)
UPDATE warranty_records w
LEFT JOIN _tmp_contractor_to_user m
  ON w.contractor_id = m.contractor_id
SET w.contractor_user_id = m.user_id;

-- 3) Drop old FK to contractors and replace the column
--    3.1) Drop FK (adjust the name if your FK differs)
ALTER TABLE warranty_records
  DROP FOREIGN KEY fk_warranty_contractor;

--    3.2) Drop old contractor_id column
ALTER TABLE warranty_records
  DROP COLUMN contractor_id;

--    3.3) Rename new column to contractor_id
ALTER TABLE warranty_records
  CHANGE COLUMN contractor_user_id contractor_id VARCHAR(16) NULL;

-- 4) Create FK to users(user_id)
ALTER TABLE warranty_records
  ADD CONSTRAINT fk_warranty_contractor
    FOREIGN KEY (contractor_id) REFERENCES users(user_id);

-- 5) (Optional) After manual verification that all rows have a valid user, enforce NOT NULL
-- ALTER TABLE warranty_records
--   MODIFY COLUMN contractor_id VARCHAR(16) NOT NULL;

-- 6) Cleanup
DROP TABLE IF EXISTS _tmp_contractor_to_user;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Password policy note (informational)
-- =====================================================================
-- The minimum password length is now 8 characters.
-- This must be enforced in the APPLICATION layer before hashing.
-- The DB trigger currently ensures password_hash has a secure length (>= 60 chars for bcrypt).
-- If desired, you can adjust trigger comments but DB cannot validate plaintext length in MySQL 5.7.
-- =====================================================================
