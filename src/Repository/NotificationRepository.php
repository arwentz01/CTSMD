<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class NotificationRepository
{
    public function __construct(private PDO $pdo, private AdminRepository $admin)
    {
    }

    /** @param array<string, mixed> $payload */
    public function create(int $userId, string $type, array $payload, string $channel = 'in_app'): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (organization_id, user_id, type, channel, payload) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $this->admin->organizationId(),
            $userId,
            $type,
            $channel,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function pending(): array
    {
        $statement = $this->pdo->query(
            'SELECT n.id, n.type, n.channel, n.status, n.created_at, u.first_name, u.last_name, u.email
             FROM notifications n
             INNER JOIN users u ON u.id = n.user_id
             WHERE n.status = "pending"
             ORDER BY n.created_at DESC
             LIMIT 25'
        );

        return $statement->fetchAll();
    }
}
