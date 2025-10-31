USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Limpiar posibles entradas antiguas en user_id_sequence
--    Eliminamos filas con user_type_id que puedan corresponder a prefijos antiguos:
DELETE FROM user_id_sequence
 WHERE user_type_id IN ('T','C','TEC','CON');

-- 2) Crear la nueva secuencia 'USR' con prefijo 'U' si no existe
INSERT IGNORE INTO user_id_sequence (user_type_id, prefix, last_number)
VALUES ('USR','U',0);

-- 3) Reemplazar trigger que genera user_id en INSERT (trg_users_before_insert)
DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_before_insert $$
CREATE TRIGGER trg_users_before_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_seq_key CHAR(3);
  DECLARE v_prefix CHAR(1);
  DECLARE v_next BIGINT UNSIGNED;

  -- Mapear CON/TEC a la nueva secuencia 'USR'
  IF NEW.user_type IN ('CON','TEC') THEN
    SET v_seq_key = 'USR';
  ELSE
    SET v_seq_key = NEW.user_type;
  END IF;

  -- Obtener prefijo y bloquear fila para incrementar en conjunto
  SELECT prefix INTO v_prefix
  FROM user_id_sequence
  WHERE user_type_id = v_seq_key
  FOR UPDATE;

  IF v_prefix IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('No sequence entry found for user sequence key: ', v_seq_key);
  END IF;

  -- Incrementar secuencia de manera segura
  UPDATE user_id_sequence
    SET last_number = last_number + 1
  WHERE user_type_id = v_seq_key;

  SELECT last_number INTO v_next
  FROM user_id_sequence
  WHERE user_type_id = v_seq_key;

  -- Construir user_id: letra + 9 dígitos (ajusta LPAD ancho si quieres)
  SET NEW.user_id = CONCAT(v_prefix, LPAD(v_next, 9, '0'));

  -- Validación mínima del hash de contraseña (bcrypt típico >= 60)
  IF CHAR_LENGTH(NEW.password_hash) < 60 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'password_hash too short (must be a secure hash)';
  END IF;
END $$
DELIMITER ;

-- 4) Reemplazar trigger trg_users_assign_type_bu (BEFORE UPDATE) con la lógica solicitada
DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_assign_type_bu $$
CREATE TRIGGER trg_users_assign_type_bu
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  DECLARE v_is_active TINYINT(1);

  -- Si el user_type es NULL o está en CON/TEC, determinamos el correcto según contractor
  IF NEW.user_type IS NULL OR NEW.user_type IN ('CON','TEC') THEN
    IF NEW.contractor_id IS NOT NULL THEN
      SELECT is_active INTO v_is_active FROM contractors WHERE contractor_id = NEW.contractor_id;
      IF v_is_active = 1 THEN
        SET NEW.user_type = 'CON';
      ELSE
        SET NEW.user_type = 'TEC';
      END IF;
    ELSE
      SET NEW.user_type = 'TEC';
    END IF;
  END IF;

  -- Nota: no generamos aquí user_id; la generación se hace en trg_users_before_insert (INSERT).
  -- Si quieres forzar re-generación de user_id en ciertos updates, me lo indicas y lo añadimos.
END $$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;


USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_before_insert $$
CREATE TRIGGER trg_users_before_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_seq_key CHAR(3);
  DECLARE v_prefix CHAR(1);
  DECLARE v_next BIGINT UNSIGNED;
  DECLARE v_msg VARCHAR(255);

  -- Mapear CON/TEC a la nueva secuencia 'USR'
  IF NEW.user_type IN ('CON','TEC') THEN
    SET v_seq_key = 'USR';
  ELSE
    SET v_seq_key = NEW.user_type;
  END IF;

  -- Obtener prefijo y bloquear fila para incrementar en conjunto
  SELECT prefix INTO v_prefix
  FROM user_id_sequence
  WHERE user_type_id = v_seq_key
  FOR UPDATE;

  IF v_prefix IS NULL THEN
    SET v_msg = CONCAT('No sequence entry found for user sequence key: ', v_seq_key);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;

  -- Incrementar secuencia de manera segura
  UPDATE user_id_sequence
    SET last_number = last_number + 1
  WHERE user_type_id = v_seq_key;

  SELECT last_number INTO v_next
  FROM user_id_sequence
  WHERE user_type_id = v_seq_key;

  -- Construir user_id: letra + 9 dígitos
  SET NEW.user_id = CONCAT(v_prefix, LPAD(v_next, 9, '0'));

  -- Validación mínima del hash de contraseña (bcrypt típico >= 60)
  IF CHAR_LENGTH(NEW.password_hash) < 60 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'password_hash too short (must be a secure hash)';
  END IF;
END $$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_assign_type_bu $$
CREATE TRIGGER trg_users_assign_type_bu
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  DECLARE v_is_active TINYINT(1);

  -- Si el user_type es NULL o está en CON/TEC, determinamos el correcto según contractor
  IF NEW.user_type IS NULL OR NEW.user_type IN ('CON','TEC') THEN
    IF NEW.contractor_id IS NOT NULL THEN
      SELECT is_active INTO v_is_active FROM contractors WHERE contractor_id = NEW.contractor_id;
      IF v_is_active = 1 THEN
        SET NEW.user_type = 'CON';
      ELSE
        SET NEW.user_type = 'TEC';
      END IF;
    ELSE
      SET NEW.user_type = 'TEC';
    END IF;
  END IF;

  -- Nota: no generamos aquí user_id; la generación se hace en trg_users_before_insert (INSERT).
  -- Si quieres forzar re-generación de user_id en ciertos updates, me lo indicas y lo añadimos.
END $$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

USE rhyjjbm0_wpew_cntrctr_01;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_assign_type_bi $$
CREATE TRIGGER trg_users_assign_type_bi
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_is_active TINYINT(1);

  -- Si el user_type es NULL o está en CON/TEC, determinamos el correcto según contractor
  IF NEW.user_type IS NULL OR NEW.user_type IN ('CON','TEC') THEN
    IF NEW.contractor_id IS NOT NULL THEN
      SELECT is_active INTO v_is_active FROM contractors WHERE contractor_id = NEW.contractor_id;
      IF v_is_active = 1 THEN
        SET NEW.user_type = 'CON';
      ELSE
        SET NEW.user_type = 'TEC';
      END IF;
    ELSE
      SET NEW.user_type = 'TEC';
    END IF;
  END IF;

  -- Nota: la generación del user_id debe realizarse en el trigger BEFORE INSERT encargado de eso (trg_users_before_insert).
  -- Si quieres que al insertar aquí también se genere user_id en caso de que no exista, puedo añadir esa lógica.
END $$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

