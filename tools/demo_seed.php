<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Database\Connection;
use App\Repository\AdminRepository;
use App\Repository\ChannelRepository;
use App\Repository\ModerationRepository;
use App\Repository\NotificationRepository;
use App\Repository\SafeguardingRepository;
use App\Support\Environment;

$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = $basePath . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

Environment::load($basePath . '/.env');

$pdo = Connection::make(require $basePath . '/config/database.php');
$admin = new AdminRepository($pdo);
$auth = new Auth($pdo);
$channels = new ChannelRepository($pdo, $admin);
$notifications = new NotificationRepository($pdo, $admin);
$moderation = new ModerationRepository($pdo, $admin, $notifications);
$safeguarding = new SafeguardingRepository($pdo, $admin);

$pdo->exec(file_get_contents($basePath . '/database/migrations/001_foundation_schema.sql') ?: '');

$password = 'DemoPassword123!';
$ownerId = upsertUser($pdo, $admin, 'demo.owner@ctsmd.test', 'Jamie', 'Director', false, ['owner', 'administrator'], $password);
$parentId = upsertUser($pdo, $admin, 'demo.parent@ctsmd.test', 'Morgan', 'Parent', false, ['guardian', 'general_member'], $password);
$studentId = upsertUser($pdo, $admin, 'demo.student@ctsmd.test', 'Riley', 'Performer', true, ['student'], $password);
$instructorId = upsertUser($pdo, $admin, 'demo.instructor@ctsmd.test', 'Casey', 'Instructor', false, ['instructor', 'production_staff'], $password);

$safeguarding->linkGuardian($parentId, $studentId, 'Parent', $ownerId);

$announcementId = ensureChannel($pdo, $channels, 'Announcements', 'Official CTSMD updates and reminders.', 'announcement', 'admins', $ownerId);
$parentChannelId = ensureChannel($pdo, $channels, 'Parent Questions', 'A place for parent questions and practical production details.', 'parent', 'members', $ownerId);
$castChannelId = ensureChannel($pdo, $channels, 'Cast Updates', 'Current production updates for cast families.', 'group', 'selected_roles', $ownerId);

$channels->addMember($castChannelId, $parentId, true, $ownerId);
$channels->addMember($castChannelId, $studentId, false, $ownerId);
$channels->addMember($castChannelId, $instructorId, true, $ownerId);

ensurePost($pdo, $channels, $announcementId, $ownerId, 'Welcome to CTSMD Connect. This demo shows the safer communication foundation we can build on.', true);
ensurePost($pdo, $channels, $parentChannelId, $parentId, 'Will rehearsal pickup use the side entrance this week?', false);
ensurePost($pdo, $channels, $castChannelId, $instructorId, 'Please bring jazz shoes and scripts to the next rehearsal.', true);

$conversationId = ensureSafeguardedConversation($pdo, $safeguarding, $instructorId, $studentId, $ownerId);
ensureMessage($pdo, $safeguarding, $conversationId, $instructorId, 'Great work today. Please review measures 12-24 before rehearsal.', $ownerId);
ensureMessage($pdo, $safeguarding, $conversationId, $parentId, 'Thanks. We will make sure Riley has the notes ready.', $ownerId);

$postId = (int) $pdo->query('SELECT id FROM channel_posts WHERE channel_id = ' . $parentChannelId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
if ((int) $pdo->query('SELECT COUNT(*) FROM content_reports WHERE subject_type = "channel_post" AND subject_id = ' . $postId)->fetchColumn() === 0) {
    $moderation->report($parentId, 'channel_post', $postId, 'demo_review', 'Demo report showing the moderation queue and notification outbox.');
}

echo "Demo data ready.\n";
echo "Login: demo.owner@ctsmd.test / {$password}\n";

/**
 * @param list<string> $roles
 */
function upsertUser(PDO $pdo, AdminRepository $admin, string $email, string $first, string $last, bool $isStudent, array $roles, string $password): int
{
    $organizationId = $admin->organizationId();
    $statement = $pdo->prepare(
        'INSERT INTO users (email, password_hash, first_name, last_name, is_student, status, email_verified_at)
         VALUES (?, ?, ?, ?, ?, "active", CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), first_name = VALUES(first_name), last_name = VALUES(last_name),
            is_student = VALUES(is_student), status = "active", email_verified_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([$email, password_hash($password, PASSWORD_DEFAULT), $first, $last, $isStudent ? 1 : 0]);
    $userId = (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote($email) . ' LIMIT 1')->fetchColumn();

    $membership = $pdo->prepare(
        'INSERT INTO organization_memberships (organization_id, user_id, status, joined_at)
         VALUES (?, ?, "active", CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE status = "active", joined_at = COALESCE(joined_at, CURRENT_TIMESTAMP)'
    );
    $membership->execute([$organizationId, $userId]);
    $membershipId = (int) $pdo->query('SELECT id FROM organization_memberships WHERE organization_id = ' . $organizationId . ' AND user_id = ' . $userId)->fetchColumn();

    foreach ($roles as $role) {
        $roleId = (int) $pdo->query('SELECT id FROM roles WHERE code = ' . $pdo->quote($role) . ' LIMIT 1')->fetchColumn();
        $pdo->prepare('INSERT IGNORE INTO membership_roles (membership_id, role_id, assigned_by_user_id) VALUES (?, ?, ?)')->execute([$membershipId, $roleId, $userId]);
    }

    return $userId;
}

function ensureChannel(PDO $pdo, ChannelRepository $channels, string $name, string $description, string $type, string $policy, int $actorId): int
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));
    $id = $pdo->query('SELECT id FROM channels WHERE slug = ' . $pdo->quote($slug) . ' LIMIT 1')->fetchColumn();

    return $id === false ? $channels->createChannel($name, $description, $type, $policy, $actorId) : (int) $id;
}

function ensurePost(PDO $pdo, ChannelRepository $channels, int $channelId, int $authorId, string $body, bool $pinned): void
{
    $exists = (int) $pdo->query('SELECT COUNT(*) FROM channel_posts WHERE channel_id = ' . $channelId . ' AND body = ' . $pdo->quote($body))->fetchColumn();
    if ($exists === 0) {
        $channels->createPost($channelId, $authorId, $body, $pinned, true);
    }
}

function ensureSafeguardedConversation(PDO $pdo, SafeguardingRepository $safeguarding, int $adultId, int $studentId, int $actorId): int
{
    $statement = $pdo->prepare(
        'SELECT c.id
         FROM conversations c
         INNER JOIN conversation_participants adult ON adult.conversation_id = c.id AND adult.user_id = ?
         INNER JOIN conversation_participants student ON student.conversation_id = c.id AND student.user_id = ?
         WHERE c.type = "safeguarded"
         LIMIT 1'
    );
    $statement->execute([$adultId, $studentId]);
    $id = $statement->fetchColumn();

    return $id === false ? $safeguarding->createSafeguardedConversation($adultId, $studentId, $actorId) : (int) $id;
}

function ensureMessage(PDO $pdo, SafeguardingRepository $safeguarding, int $conversationId, int $senderId, string $body, int $fallbackSenderId): void
{
    $exists = (int) $pdo->query('SELECT COUNT(*) FROM messages WHERE conversation_id = ' . $conversationId . ' AND body = ' . $pdo->quote($body))->fetchColumn();
    if ($exists === 0) {
        try {
            $safeguarding->postMessage($conversationId, $senderId, $body);
        } catch (Throwable) {
            $safeguarding->postMessage($conversationId, $fallbackSenderId, $body);
        }
    }
}
