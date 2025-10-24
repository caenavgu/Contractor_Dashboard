-- migrations/2025_10_21_move_epa_fields_to_user_details.sql
-- 1) Añadir columnas a user_details (después de epa_photo_url)
-- 2) Eliminar columnas de users (epa_*)
-- 3) Reordenar email_verification_token/expires AFTER email_verified_at

-- ---------- 1) Añadir columnas a user_details ----------
ALTER TABLE user_details
  ADD COLUMN IF NOT EXISTS epa_photo_filename VARCHAR(255) NULL AFTER epa_photo_url,
  ADD COLUMN IF NOT EXISTS epa_photo_mime VARCHAR(100) NULL AFTER epa_photo_filename,
  ADD COLUMN IF NOT EXISTS epa_photo_size INT NULL AFTER epa_photo_mime,
  ADD COLUMN IF NOT EXISTS epa_photo_checksum VARCHAR(128) NULL AFTER epa_photo_size;

-- Nota: MySQL 5.7 no soporta "ADD COLUMN IF NOT EXISTS" en todas las builds.
-- Si tu servidor devuelve error por "IF NOT EXISTS", elimina "IF NOT EXISTS" y ejecuta.
-- Alternativa segura previa ejecución: revisar existencia con SELECT en information_schema.

-- ---------- 2) Eliminar columnas de users (epa_*) ----------
ALTER TABLE users
  DROP COLUMN IF EXISTS epa_photo_filename,
  DROP COLUMN IF EXISTS epa_photo_mime,
  DROP COLUMN IF EXISTS epa_photo_size,
  DROP COLUMN IF EXISTS epa_photo_checksum;

-- ---------- 3) Reordenar email_verification_token/expires AFTER email_verified_at ----------
-- Asegúrate de que las columnas existan; si no existen, crear/modificar según corresponda.
-- Estas sentencias mueven la columna para quedar inmediatamente después de email_verified_at.
ALTER TABLE users
  MODIFY COLUMN email_verification_token VARCHAR(128) NULL AFTER email_verified_at,
  MODIFY COLUMN email_verification_expires_at DATETIME NULL AFTER email_verification_token;
