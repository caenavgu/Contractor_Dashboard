<?php
// app/Repositories/user_repository.php
// -------------------------------------------------------------
// Acceso a datos de usuarios
// -------------------------------------------------------------
declare(strict_types=1);

class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function find_by_email(string $email): ?array
    {
        $sql = "SELECT user_id, email, username, password_hash, is_active, email_verified_at 
                FROM users WHERE email = :email LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':email' => $email]);
        $row = $st->fetch();
        return $row ?: null;
    }
}
