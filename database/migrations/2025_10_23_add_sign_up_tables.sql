-- migrations/2025_10_23_add_sign_up_tables.sql
-- Agrega contractor_staging y columnas necesarias a users

ALTER TABLE users
  ADD COLUMN email_verification_token VARCHAR(128) NULL,
  ADD COLUMN email_verification_expires_at DATETIME NULL,
  ADD COLUMN epa_photo_filename VARCHAR(255) NULL,
  ADD COLUMN epa_photo_mime VARCHAR(100) NULL,
  ADD COLUMN epa_photo_size INT NULL,
  ADD COLUMN epa_photo_checksum VARCHAR(128) NULL;

CREATE TABLE contractor_staging (
  staging_id INT AUTO_INCREMENT PRIMARY KEY,
  existing_contractor_id INT NULL,
  input_cac_license_number VARCHAR(100) NOT NULL,
  input_company_name VARCHAR(191) NULL,
  input_address VARCHAR(191) NULL,
  input_address_2 VARCHAR(191) NULL,
  input_city VARCHAR(100) NULL,
  input_state_code VARCHAR(10) NULL,
  input_zip_code VARCHAR(20) NULL,
  input_company_phone VARCHAR(50) NULL,
  input_company_email VARCHAR(191) NULL,
  input_company_website VARCHAR(191) NULL,
  input_raw_json JSON NULL,
  created_by_user_id INT NULL,
  status ENUM('pending','merged','discarded') NOT NULL DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  merged_at DATETIME NULL,
  merged_by_admin_id INT NULL,
  notes TEXT NULL,
  INDEX (existing_contractor_id),
  INDEX (input_cac_license_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Única constraint en contractors.cac_license_number si no la tienes:
ALTER TABLE contractors
  ADD UNIQUE INDEX uq_contractors_cac_license_number (cac_license_number);
