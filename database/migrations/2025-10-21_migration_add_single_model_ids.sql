-- =====================================================================
-- Migration: Add outdoor_model_single_id & indoor_model_single_id to warranty_records
-- Target DB: rhyjjbm0_wpew_cntrctr_01 (MySQL 5.7)
-- Date: 2025-10-21
-- =====================================================================

USE rhyjjbm0_wpew_cntrctr_01;

-- 1) Add columns (same type/order as requested)
ALTER TABLE warranty_records
  ADD COLUMN outdoor_model_single_id BIGINT UNSIGNED NULL AFTER outdoor_model_id,
  ADD COLUMN indoor_model_single_id  BIGINT UNSIGNED NULL AFTER indoor_model_id;

-- 2) Helpful indexes
CREATE INDEX idx_warranty_outdoor_model_single ON warranty_records (outdoor_model_single_id);
CREATE INDEX idx_warranty_indoor_model_single  ON warranty_records (indoor_model_single_id);

-- 3) Foreign keys to models(model_id)
ALTER TABLE warranty_records
  ADD CONSTRAINT fk_warranty_outdoor_model_single
    FOREIGN KEY (outdoor_model_single_id) REFERENCES models(model_id),
  ADD CONSTRAINT fk_warranty_indoor_model_single
    FOREIGN KEY (indoor_model_single_id)  REFERENCES models(model_id);

-- =====================================================================
-- END
-- =====================================================================
