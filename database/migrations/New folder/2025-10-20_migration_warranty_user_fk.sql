-- =====================================================================
-- Migration: warranty_records -> replace contractor columns with user_id FK
-- Target DB: rhyjjbm0_wpew_cntrctr_01
-- Date: 2025-10-20
-- Note: Table warranty_records is empty (no data migration required).
-- Steps:
--   1) Drop FK to contractors (if present) and remove columns contractor_id / contractor_user_id
--   2) Add user_id (NOT NULL) and FK to users(user_id)
-- =====================================================================

USE rhyjjbm0_wpew_cntrctr_01;

SET FOREIGN_KEY_CHECKS = 0;

-- 1) Best-effort: drop FK to contractors if it exists.
--    If this statement fails due to unknown constraint name, comment it and re-run.
-- ALTER TABLE warranty_records DROP FOREIGN KEY fk_warranty_contractor;

-- 2) Drop columns contractor_id and contractor_user_id
ALTER TABLE warranty_records
  DROP COLUMN contractor_id,
  DROP COLUMN contractor_user_id;

-- 3) Add user_id and FK to users(user_id)
ALTER TABLE warranty_records
  ADD COLUMN user_id VARCHAR(16) NOT NULL AFTER status;

ALTER TABLE warranty_records
  ADD CONSTRAINT fk_warranty_user
    FOREIGN KEY (user_id) REFERENCES users(user_id);

-- Optional: index to speed lists by user/date
CREATE INDEX idx_warranty_user_created ON warranty_records (user_id, created_at);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- END
-- =====================================================================
