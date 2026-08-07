<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class CommunicationReadStateService
{
    public static function navigationCounts(PDO $db, array $user): array
    {
        return [
            'messages' => self::messageUnreadCount($db, (int)$user['id']),
            'community' => self::communityUnread($db, $user)['total'],
        ];
    }

    public static function messageUnreadCount(PDO $db, int $userId): int
    {
        $stmt = $db->prepare("SELECT COUNT(*)
            FROM conversation_participants mine
            JOIN messages m ON m.conversation_id = mine.conversation_id
                AND m.hidden_at IS NULL
                AND m.id > mine.last_read_message_id
                AND m.sender_user_id <> mine.user_id
            WHERE mine.user_id = :user");
        $stmt->execute(['user' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markConversationRead(PDO $db, int $userId, int $conversationId): void
    {
        $latest = $db->prepare("SELECT COALESCE(MAX(id),0) FROM messages WHERE conversation_id = :conversation AND hidden_at IS NULL");
        $latest->execute(['conversation' => $conversationId]);
        $messageId = (int)$latest->fetchColumn();
        $stmt = $db->prepare("UPDATE conversation_participants
            SET last_read_message_id = GREATEST(last_read_message_id, :message), last_read_at = CURRENT_TIMESTAMP
            WHERE conversation_id = :conversation AND user_id = :user");
        $stmt->execute(['message' => $messageId, 'conversation' => $conversationId, 'user' => $userId]);
    }

    public static function markConversationThroughMessage(PDO $db, int $userId, int $conversationId, int $messageId): void
    {
        $stmt = $db->prepare("UPDATE conversation_participants
            SET last_read_message_id = GREATEST(last_read_message_id, :message), last_read_at = CURRENT_TIMESTAMP
            WHERE conversation_id = :conversation AND user_id = :user");
        $stmt->execute(['message' => $messageId, 'conversation' => $conversationId, 'user' => $userId]);
    }

    public static function communityUnread(PDO $db, array $user): array
    {
        $channels = self::visibleChannels($db, $user);
        if (!$channels) return ['total' => 0, 'channels' => []];

        self::ensureChannelBaselines($db, (int)$user['id'], $channels);
        $counts = [];
        $total = 0;
        $stmt = $db->prepare("SELECT COUNT(*) FROM channel_posts cp
            JOIN channel_read_states crs ON crs.channel_id = cp.channel_id AND crs.user_id = :user
            WHERE cp.channel_id = :channel
              AND cp.moderation_status = 'published'
              AND cp.id > crs.last_read_post_id
              AND cp.author_user_id <> :author");
        foreach ($channels as $channel) {
            $stmt->execute(['user' => (int)$user['id'], 'channel' => (int)$channel['id'], 'author' => (int)$user['id']]);
            $count = (int)$stmt->fetchColumn();
            $counts[(int)$channel['id']] = $count;
            $total += $count;
        }
        return ['total' => $total, 'channels' => $counts];
    }

    public static function canAccessChannel(PDO $db, array $user, int $channelId): bool
    {
        if ($channelId < 1) return false;
        $stmt = $db->prepare("SELECT c.id,c.production_id,c.read_scope,c.post_scope,c.read_audiences_json,c.post_audiences_json,c.access_mode,p.is_active production_active
            FROM channels c LEFT JOIN productions p ON p.id=c.production_id
            WHERE c.id=:id AND c.archived_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $channelId]);
        $channel = $stmt->fetch();
        return $channel ? self::canRead($db, $user, $channel) : false;
    }

    public static function markChannelRead(PDO $db, int $userId, int $channelId): void
    {
        $stmt = $db->prepare("SELECT COALESCE(MAX(id),0) FROM channel_posts WHERE channel_id = :channel AND moderation_status = 'published'");
        $stmt->execute(['channel' => $channelId]);
        $latest = (int)$stmt->fetchColumn();
        $upsert = $db->prepare("INSERT INTO channel_read_states (channel_id,user_id,last_read_post_id,last_read_at)
            VALUES (:channel,:user,:post,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE last_read_post_id = GREATEST(last_read_post_id, VALUES(last_read_post_id)), last_read_at = CURRENT_TIMESTAMP");
        $upsert->execute(['channel' => $channelId, 'user' => $userId, 'post' => $latest]);
    }

    private static function ensureChannelBaselines(PDO $db, int $userId, array $channels): void
    {
        $epoch = (string)$db->query("SELECT started_at FROM communication_read_state_meta WHERE id = 1")->fetchColumn();
        if ($epoch === '') $epoch = '1970-01-01 00:00:00';
        $exists = $db->prepare('SELECT 1 FROM channel_read_states WHERE channel_id = :channel AND user_id = :user LIMIT 1');
        $baseline = $db->prepare("SELECT COALESCE(MAX(id),0) FROM channel_posts WHERE channel_id = :channel AND moderation_status = 'published' AND created_at < :epoch");
        $insert = $db->prepare("INSERT IGNORE INTO channel_read_states (channel_id,user_id,last_read_post_id,last_read_at) VALUES (:channel,:user,:post,:read_at)");
        foreach ($channels as $channel) {
            $channelId = (int)$channel['id'];
            $exists->execute(['channel' => $channelId, 'user' => $userId]);
            if ($exists->fetchColumn()) continue;
            $baseline->execute(['channel' => $channelId, 'epoch' => $epoch]);
            $postId = (int)$baseline->fetchColumn();
            $insert->execute(['channel' => $channelId, 'user' => $userId, 'post' => $postId, 'read_at' => $postId > 0 ? $epoch : null]);
        }
    }

    private static function visibleChannels(PDO $db, array $user): array
    {
        $rows = $db->query("SELECT c.id,c.production_id,c.read_scope,c.post_scope,c.read_audiences_json,c.post_audiences_json,c.access_mode,p.is_active production_active
            FROM channels c LEFT JOIN productions p ON p.id=c.production_id
            WHERE c.archived_at IS NULL")->fetchAll();
        return array_values(array_filter($rows, static fn(array $channel): bool => self::canRead($db, $user, $channel)));
    }

    private static function canRead(PDO $db, array $user, array $channel): bool
    {
        if (empty($channel['production_id']) && !Auth::isApprovedMember($user)) return false;
        $mode = (string)($channel['access_mode'] ?? 'audience');
        if ($mode === 'audience' && AccessPolicy::isStaff($user)) return true;
        $audienceOk = self::matchesAnyAudience($db, $user, $channel, self::audiences($channel, 'read'));
        $selectedOk = self::selectedAccess($db, (int)$channel['id'], (int)$user['id']);
        $teamOk = self::teamAccess($db, (int)$channel['id'], (int)$user['id'], $channel);
        return match ($mode) {
            'selected' => $selectedOk,
            'team' => $teamOk,
            'hybrid' => $audienceOk || $selectedOk || $teamOk,
            default => $audienceOk,
        };
    }

    private static function selectedAccess(PDO $db, int $channelId, int $userId): bool
    {
        $stmt = $db->prepare("SELECT can_read FROM channel_members WHERE channel_id=:channel AND user_id=:user AND status='active' LIMIT 1");
        $stmt->execute(['channel' => $channelId, 'user' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function teamAccess(PDO $db, int $channelId, int $userId, array $channel): bool
    {
        if (!empty($channel['production_id']) && !(bool)($channel['production_active'] ?? false)) return false;
        $stmt = $db->prepare("SELECT 1 FROM channel_teams ct
            JOIN teams t ON t.id=ct.team_id AND t.active=1
            JOIN team_members tm ON tm.team_id=t.id AND tm.status='active'
            WHERE ct.channel_id=:channel AND tm.user_id=:user AND ct.can_read=1 LIMIT 1");
        $stmt->execute(['channel' => $channelId, 'user' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function audiences(array $channel, string $mode): array
    {
        $json = (string)($channel[$mode . '_audiences_json'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && $decoded) return array_values(array_unique(array_map('strval', $decoded)));
        }
        return [(string)($channel[$mode . '_scope'] ?? 'staff')];
    }

    private static function matchesAnyAudience(PDO $db, array $user, array $channel, array $audiences): bool
    {
        $userId = (int)$user['id'];
        $productionId = (int)($channel['production_id'] ?? 0);
        $productionActive = $productionId > 0 && (bool)($channel['production_active'] ?? false);
        $isStudent = AccessPolicy::isStudent($user);
        $isAdult = !$isStudent;
        $audienceType = $productionActive ? ProductionContext::audienceType($db, $userId, $productionId) : null;
        $activeProductionMember = $productionActive && $audienceType !== null;
        foreach ($audiences as $audience) {
            $ok = match ($audience) {
                'all_members' => Auth::isApprovedMember($user),
                'adults' => Auth::isApprovedMember($user) && $isAdult,
                'students' => Auth::isApprovedMember($user) && $isStudent,
                'staff' => AccessPolicy::isStaff($user),
                'volunteers' => Auth::isApprovedMember($user) && self::activeVolunteer($db, $userId),
                'production_members' => $activeProductionMember,
                'production_adults' => $activeProductionMember && $isAdult,
                'production_students' => $activeProductionMember && $audienceType === 'student',
                'production_guardians' => $activeProductionMember && $audienceType === 'guardian',
                'production_staff' => $activeProductionMember && $audienceType === 'staff',
                default => false,
            };
            if ($ok) return true;
        }
        return false;
    }

    private static function activeVolunteer(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM volunteer_profiles WHERE user_id=:id AND active=1 LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }
}
