ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_approved_by_users_user_id_20251111a`
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;
