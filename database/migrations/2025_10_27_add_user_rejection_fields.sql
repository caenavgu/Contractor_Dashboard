-- migrations/2025_10_27_add_user_rejection_fields.sql
ALTER TABLE users
  ADD COLUMN rejected_at DATETIME NULL AFTER email_verified_at,
  ADD COLUMN rejection_reason TEXT NULL AFTER rejected_at;
