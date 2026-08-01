<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;
use PDO;

final class AdminRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function hasUsers(): bool
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    public function organizationId(): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM organizations WHERE slug = ? LIMIT 1');
        $statement->execute(['ctsmd']);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO organizations (name, slug) VALUES (?, ?)');
        $insert->execute(['Children\'s Theatre of Southern Maryland', 'ctsmd']);

        return (int) $this->pdo->lastInsertId();
    }

    public function createOwner(string $email, string $firstName, string $lastName, string $password): int
    {
        $organizationId = $this->organizationId();

        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare(
                'INSERT INTO users (email, password_hash, first_name, last_name, status, email_verified_at) VALUES (?, ?, ?, ?, "active", CURRENT_TIMESTAMP)'
            );
            $user->execute([
                strtolower(trim($email)),
                password_hash($password, PASSWORD_DEFAULT),
                trim($firstName),
                trim($lastName),
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            $membership = $this->pdo->prepare(
                'INSERT INTO organization_memberships (organization_id, user_id, status, joined_at) VALUES (?, ?, "active", CURRENT_TIMESTAMP)'
            );
            $membership->execute([$organizationId, $userId]);
            $membershipId = (int) $this->pdo->lastInsertId();

            $this->assignRole($membershipId, 'owner', $userId);
            $this->assignRole($membershipId, 'administrator', $userId);
            $this->audit($organizationId, $userId, 'owner.created', 'user', $userId);

            $this->pdo->commit();
            return $userId;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function users(): array
    {
        $statement = $this->pdo->query(
            'SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.is_student,
                GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") AS roles
             FROM users u
             LEFT JOIN organization_memberships om ON om.user_id = u.id
             LEFT JOIN membership_roles mr ON mr.membership_id = om.id
             LEFT JOIN roles r ON r.id = mr.role_id
             WHERE u.deleted_at IS NULL
             GROUP BY u.id
             ORDER BY u.created_at DESC'
        );

        return $statement->fetchAll();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'members' => (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn(),
            'students' => (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_student = 1 AND deleted_at IS NULL')->fetchColumn(),
            'channels' => (int) $this->pdo->query('SELECT COUNT(*) FROM channels WHERE archived_at IS NULL')->fetchColumn(),
            'alerts' => (int) $this->pdo->query('SELECT COUNT(*) FROM content_reports WHERE status IN ("open", "reviewing")')->fetchColumn(),
        ];
    }

    /** @param list<string> $roleCodes */
    public function invite(string $email, string $firstName, string $lastName, bool $isStudent, array $roleCodes, int $actorId): string
    {
        $organizationId = $this->organizationId();
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare(
                'INSERT INTO users (email, first_name, last_name, is_student, status)
                 VALUES (?, ?, ?, ?, "invited")
                 ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), is_student = VALUES(is_student), updated_at = CURRENT_TIMESTAMP'
            );
            $user->execute([strtolower(trim($email)), trim($firstName), trim($lastName), $isStudent ? 1 : 0]);

            $userId = (int) $this->pdo->query('SELECT id FROM users WHERE email = ' . $this->pdo->quote(strtolower(trim($email))) . ' LIMIT 1')->fetchColumn();

            $membership = $this->pdo->prepare(
                'INSERT INTO organization_memberships (organization_id, user_id, status)
                 VALUES (?, ?, "invited")
                 ON DUPLICATE KEY UPDATE status = IF(status = "active", status, "invited"), updated_at = CURRENT_TIMESTAMP'
            );
            $membership->execute([$organizationId, $userId]);

            $membershipId = (int) $this->pdo->query(
                'SELECT id FROM organization_memberships WHERE organization_id = ' . $organizationId . ' AND user_id = ' . $userId . ' LIMIT 1'
            )->fetchColumn();

            foreach ($roleCodes as $roleCode) {
                $this->assignRole($membershipId, $roleCode, $actorId);
            }

            $invitation = $this->pdo->prepare(
                'INSERT INTO invitations (organization_id, email, token_hash, invited_by_user_id, expires_at) VALUES (?, ?, ?, ?, ?)'
            );
            $invitation->execute([$organizationId, strtolower(trim($email)), hash('sha256', $token), $actorId, $expiresAt]);
            $this->audit($organizationId, $actorId, 'invitation.created', 'user', $userId);

            $this->pdo->commit();
            return $token;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    public function acceptInvitation(string $token, string $password): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM invitations WHERE token_hash = ? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP LIMIT 1'
        );
        $statement->execute([hash('sha256', $token)]);
        $invitation = $statement->fetch();

        if (!is_array($invitation)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare(
                'UPDATE users SET password_hash = ?, status = "active", email_verified_at = CURRENT_TIMESTAMP WHERE email = ?'
            );
            $user->execute([password_hash($password, PASSWORD_DEFAULT), $invitation['email']]);

            $userId = (int) $this->pdo->query('SELECT id FROM users WHERE email = ' . $this->pdo->quote((string) $invitation['email']) . ' LIMIT 1')->fetchColumn();
            $membership = $this->pdo->prepare(
                'UPDATE organization_memberships SET status = "active", joined_at = CURRENT_TIMESTAMP WHERE organization_id = ? AND user_id = ?'
            );
            $membership->execute([(int) $invitation['organization_id'], $userId]);

            $accepted = $this->pdo->prepare('UPDATE invitations SET accepted_at = CURRENT_TIMESTAMP WHERE id = ?');
            $accepted->execute([(int) $invitation['id']]);
            $this->audit((int) $invitation['organization_id'], $userId, 'invitation.accepted', 'user', $userId);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    private function assignRole(int $membershipId, string $roleCode, int $actorId): void
    {
        $role = $this->pdo->prepare('SELECT id FROM roles WHERE code = ? LIMIT 1');
        $role->execute([$roleCode]);
        $roleId = $role->fetchColumn();

        if ($roleId === false) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT IGNORE INTO membership_roles (membership_id, role_id, assigned_by_user_id) VALUES (?, ?, ?)'
        );
        $statement->execute([$membershipId, (int) $roleId, $actorId]);
    }

    private function audit(int $organizationId, ?int $actorId, string $action, string $subjectType, int $subjectId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (organization_id, actor_user_id, action, subject_type, subject_id) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$organizationId, $actorId, $action, $subjectType, $subjectId]);
    }
}
