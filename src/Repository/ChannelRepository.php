<?php

declare(strict_types=1);

namespace App\Repository;

use InvalidArgumentException;
use PDO;

final class ChannelRepository
{
    private const TYPES = ['announcement', 'discussion', 'group', 'parent', 'staff', 'resource'];
    private const POSTING_POLICIES = ['admins', 'selected_roles', 'members'];

    public function __construct(private PDO $pdo, private AdminRepository $admin)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function channels(): array
    {
        $statement = $this->pdo->query(
            'SELECT c.id, c.name, c.slug, c.description, c.type, c.visibility, c.posting_policy, c.created_at,
                COUNT(cp.id) AS post_count
             FROM channels c
             LEFT JOIN channel_posts cp ON cp.channel_id = c.id AND cp.deleted_at IS NULL
             WHERE c.archived_at IS NULL
             GROUP BY c.id
             ORDER BY c.created_at DESC'
        );

        return $statement->fetchAll();
    }

    public function createChannel(string $name, string $description, string $type, string $postingPolicy, int $actorId): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Channel name is required.');
        }

        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Choose a valid channel type.');
        }

        if (!in_array($postingPolicy, self::POSTING_POLICIES, true)) {
            throw new InvalidArgumentException('Choose a valid posting policy.');
        }

        $organizationId = $this->admin->organizationId();
        $slug = $this->uniqueSlug($organizationId, $name);

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO channels (organization_id, name, slug, description, type, visibility, posting_policy, created_by_user_id)
                 VALUES (?, ?, ?, ?, ?, "organization", ?, ?)'
            );
            $statement->execute([$organizationId, $name, $slug, trim($description) ?: null, $type, $postingPolicy, $actorId]);
            $channelId = (int) $this->pdo->lastInsertId();
            $this->audit($organizationId, $actorId, 'channel.created', 'channel', $channelId);

            $this->pdo->commit();
            return $channelId;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    /** @return array<string, mixed>|null */
    public function channel(int $channelId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, slug, description, type, visibility, posting_policy, created_at
             FROM channels
             WHERE id = ? AND archived_at IS NULL
             LIMIT 1'
        );
        $statement->execute([$channelId]);
        $channel = $statement->fetch();

        return is_array($channel) ? $channel : null;
    }

    public function canPost(int $channelId, int $userId, bool $isAdmin): bool
    {
        $channel = $this->channel($channelId);
        if ($channel === null) {
            return false;
        }

        if ($isAdmin) {
            return true;
        }

        if ($channel['posting_policy'] === 'admins') {
            return false;
        }

        if ($channel['posting_policy'] === 'members') {
            return $this->activeMember($userId);
        }

        return $this->explicitPoster($channelId, $userId);
    }

    public function addMember(int $channelId, int $userId, bool $canPost, int $actorId): void
    {
        $channel = $this->channel($channelId);
        if ($channel === null || !$this->activeMember($userId)) {
            throw new InvalidArgumentException('Choose a valid channel and active member.');
        }

        $membershipId = $this->membershipId($userId);
        if ($membershipId === null) {
            throw new InvalidArgumentException('The selected user does not have an active membership.');
        }

        $organizationId = $this->admin->organizationId();
        $statement = $this->pdo->prepare(
            'INSERT INTO channel_memberships (channel_id, membership_id, can_post)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE can_post = VALUES(can_post)'
        );
        $statement->execute([$channelId, $membershipId, $canPost ? 1 : 0]);
        $this->audit($organizationId, $actorId, 'channel_member.upserted', 'channel', $channelId);
    }

    /** @return array<int, array<string, mixed>> */
    public function channelMembers(int $channelId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, cm.can_post
             FROM channel_memberships cm
             INNER JOIN organization_memberships om ON om.id = cm.membership_id
             INNER JOIN users u ON u.id = om.user_id
             WHERE cm.channel_id = ? AND om.status = "active" AND u.deleted_at IS NULL
             ORDER BY u.last_name, u.first_name'
        );
        $statement->execute([$channelId]);

        return $statement->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function posts(int $channelId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cp.id, cp.body, cp.is_pinned, cp.created_at, cp.deleted_at, u.first_name, u.last_name
             FROM channel_posts cp
             INNER JOIN users u ON u.id = cp.author_user_id
             WHERE cp.channel_id = ?
             ORDER BY cp.is_pinned DESC, cp.created_at DESC, cp.id DESC'
        );
        $statement->execute([$channelId]);

        return $statement->fetchAll();
    }

    public function createPost(int $channelId, int $authorId, string $body, bool $isPinned, bool $isAdmin): int
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Post body is required.');
        }

        $channel = $this->channel($channelId);
        if ($channel === null) {
            throw new InvalidArgumentException('Choose a valid channel.');
        }

        if (!$this->canPost($channelId, $authorId, $isAdmin)) {
            throw new InvalidArgumentException('You do not have permission to post in this channel.');
        }

        $organizationId = $this->admin->organizationId();
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO channel_posts (channel_id, author_user_id, body, is_pinned, pinned_by_user_id, pinned_at)
                 VALUES (?, ?, ?, ?, ?, IF(? = 1, CURRENT_TIMESTAMP, NULL))'
            );
            $statement->execute([$channelId, $authorId, $body, $isPinned ? 1 : 0, $isPinned ? $authorId : null, $isPinned ? 1 : 0]);
            $postId = (int) $this->pdo->lastInsertId();
            $this->audit($organizationId, $authorId, 'channel_post.created', 'channel_post', $postId);

            $this->pdo->commit();
            return $postId;
        } catch (\Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    private function uniqueSlug(int $organizationId, string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-')) ?: 'channel';
        $slug = $base;
        $counter = 2;

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM channels WHERE organization_id = ? AND slug = ?');
        while (true) {
            $statement->execute([$organizationId, $slug]);
            if ((int) $statement->fetchColumn() === 0) {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }
    }

    private function activeMember(int $userId): bool
    {
        return $this->membershipId($userId) !== null;
    }

    private function explicitPoster(int $channelId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM channel_memberships cm
             INNER JOIN organization_memberships om ON om.id = cm.membership_id
             WHERE cm.channel_id = ? AND om.user_id = ? AND om.status = "active" AND cm.can_post = 1'
        );
        $statement->execute([$channelId, $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function membershipId(int $userId): ?int
    {
        $organizationId = $this->admin->organizationId();
        $statement = $this->pdo->prepare(
            'SELECT id FROM organization_memberships WHERE organization_id = ? AND user_id = ? AND status = "active" LIMIT 1'
        );
        $statement->execute([$organizationId, $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function audit(int $organizationId, ?int $actorId, string $action, string $subjectType, int $subjectId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (organization_id, actor_user_id, action, subject_type, subject_id) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$organizationId, $actorId, $action, $subjectType, $subjectId]);
    }
}
