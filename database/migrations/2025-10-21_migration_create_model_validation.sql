-- =====================================================================
-- Migration: Create model_validation table (outdoor/indoor model pairing)
-- Target DB: rhyjjbm0_wpew_cntrctr_01 (MySQL 5.7)
-- Date: 2025-10-21
-- Notes:
--   * Composite primary key: (outdoor_unir, indoor_unit)
--   * Both columns reference models(model_id)
-- =====================================================================

USE rhyjjbm0_wpew_cntrctr_01;

DROP TABLE IF EXISTS model_validation;
CREATE TABLE model_validation (
  outdoor_unit BIGINT UNSIGNED NOT NULL,
  indoor_unit  BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (outdoor_unir, indoor_unit),
  CONSTRAINT fk_model_validation_outdoor FOREIGN KEY (outdoor_unit) REFERENCES models(model_id),
  CONSTRAINT fk_model_validation_indoor  FOREIGN KEY (indoor_unit)  REFERENCES models(model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful indexes for single-column lookups (optional)
CREATE INDEX idx_mv_outdoor ON model_validation (outdoor_unir);
CREATE INDEX idx_mv_indoor  ON model_validation (indoor_unit);

-- =====================================================================
-- END
-- =====================================================================
