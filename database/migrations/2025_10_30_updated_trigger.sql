DROP TRIGGER IF EXISTS trg_users_assign_type_bi;
DROP TRIGGER IF EXISTS trg_users_assign_type_bu;

DELIMITER $$

CREATE TRIGGER trg_users_assign_type_bi
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(16);

  -- Si el user_type es NULL o está en CON/TEC, determinamos el correcto según contractor
  IF NEW.user_type IS NULL OR NEW.user_type IN ('CON','TEC') THEN
    IF NEW.contractor_id IS NOT NULL THEN
      SELECT status INTO v_status FROM contractors WHERE contractor_id = NEW.contractor_id;

      IF v_status = 'ACTIVE' THEN
        SET NEW.user_type = 'CON';
      ELSE
        SET NEW.user_type = 'TEC';
      END IF;
    ELSE
      SET NEW.user_type = 'TEC';
    END IF;
  END IF;

  -- Nota: la generación del user_id se hace en trg_users_before_insert.
END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER trg_users_assign_type_bu
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(16);

  IF NEW.user_type IS NULL OR NEW.user_type IN ('CON','TEC') THEN
    IF NEW.contractor_id IS NOT NULL THEN
      SELECT status INTO v_status FROM contractors WHERE contractor_id = NEW.contractor_id;

      IF v_status = 'ACTIVE' THEN
        SET NEW.user_type = 'CON';
      ELSE
        SET NEW.user_type = 'TEC';
      END IF;
    ELSE
      SET NEW.user_type = 'TEC';
    END IF;
  END IF;

  -- Nota: no generamos user_id aquí; lo maneja trg_users_before_insert.
END$$

DELIMITER ;
