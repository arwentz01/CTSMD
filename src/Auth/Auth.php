<?php

declare(strict_types=1);

namespace App\Auth;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, email, first_name, last_name, is_student, status FROM users WHERE id = ? AND status = "active" AND deleted_at IS NULL'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function attempt(string $email, string $password): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT id, password_hash, status FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute([strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!is_array($user) || $user['status'] !== 'active' || !is_string($user['password_hash'])) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        $update = $this->pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([(int) $user['id']]);

        return true;
    }

    public function hasAnyRole(int $userId, array $roleCodes): bool
    {
        if ($roleCodes === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM organization_memberships om
             INNER JOIN membership_roles mr ON mr.membership_id = om.id
             INNER JOIN roles r ON r.id = mr.role_id
             WHERE om.user_id = ? AND om.status = "active" AND r.code IN (' . $placeholders . ')'
        );
        $statement->execute([$userId, ...$roleCodes]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<string> */
    public function roleCodes(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.code
             FROM organization_memberships om
             INNER JOIN membership_roles mr ON mr.membership_id = om.id
             INNER JOIN roles r ON r.id = mr.role_id
             WHERE om.user_id = ? AND om.status = "active"'
        );
        $statement->execute([$userId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
