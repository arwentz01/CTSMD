<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';

final class CommunicationExperience
{
    private const ROUTES = ['/channels', '/channels/view', '/messages', '/messages/thread'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        $userId = (int)$user['id'];
        $_SESSION['communication_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $userId, $basePath);
        }

        $channels = self::channels($db);
        $conversations = self::conversations($db, $userId);
        $selectedChannel = null;
        $selectedConversation = null;

        if ($route === '/channels/view') {
            $channelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selectedChannel = self::channelById($db, (int)$channelId);
        }
        if ($route === '/messages/thread') {
            $conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selectedConversation = self::conversationById($db, $userId, (int)$conversationId);
        }

        self::page($route, $basePath, $user, $channels, $conversations, $selectedChannel, $selectedConversation);
    }

    private static function handlePost(PDO $db, int $userId, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['communication_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/messages');
        }

        $action = (string)($_POST['action'] ?? '');
        $conversationId = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT) ?: 0;
        if ($action !== 'send_message' || $conversationId < 1) {
            self::flash('error', 'That message action could not be completed.');
            self::redirect($basePath . '/messages');
        }

        $body = trim((string)($_POST['body'] ?? ''));
        try {
            self::sendMessage($db, $userId, (int)$conversationId, $body);
            self::flash('success', 'Message sent to everyone in this conversation.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/messages/thread?id=' . (int)$conversationId);
    }

    private static function sendMessage(PDO $db, int $userId, int $conversationId, string $body): void
    {
        if ($body === '') {
            throw new RuntimeException('Write a message before sending.');
        }
        if (mb_strlen($body) > 4000) {
            throw new RuntimeException('Messages are limited to 4,000 characters.');
        }

        $db->beginTransaction();
        try {
            $conversationStmt = $db->prepare('SELECT id, conversation_type FROM conversations WHERE id = :id FOR UPDATE');
            $conversationStmt->execute(['id' => $conversationId]);
            $conversation = $conversationStmt->fetch();
            if (!$conversation) {
                throw new RuntimeException('That conversation no longer exists.');
            }

            $membershipStmt = $db->prepare('SELECT participant_role FROM conversation_participants WHERE conversation_id = :conversation_id AND user_id = :user_id');
            $membershipStmt->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            $membership = $membershipStmt->fetch();
            if (!$membership) {
                throw new RuntimeException('You do not have access to that conversation.');
            }

            $participantsStmt = $db->prepare('SELECT cp.user_id, cp.participant_role, cp.guardian_required, u.active FROM conversation_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id = :conversation_id FOR UPDATE');
            $participantsStmt->execute(['conversation_id' => $conversationId]);
            $participants = $participantsStmt->fetchAll();
            self::assertConversationSafe((string)$conversation['conversation_type'], $participants);

            $insert = $db->prepare('INSERT INTO messages (conversation_id, sender_user_id, body, created_at) VALUES (:conversation_id, :sender_user_id, :body, CURRENT_TIMESTAMP)');
            $insert->execute([
                'conversation_id' => $conversationId,
                'sender_user_id' => $userId,
                'body' => $body,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('We could not send that message. Please try again.');
        }
    }

    private static function assertConversationSafe(string $type, array $participants): void
    {
        $active = array_values(array_filter($participants, static fn(array $p): bool => (bool)$p['active']));
        $roles = array_count_values(array_column($active, 'participant_role'));
        $studentCount = (int)($roles['student'] ?? 0);
        $adultCount = (int)($roles['adult'] ?? 0);
        $guardianCount = (int)($roles['guardian'] ?? 0);

        if ($studentCount > 0 && $type !== 'safeguarded') {
            throw new RuntimeException('This conversation cannot accept messages because a student is present without safeguarded conversation status.');
        }

        if ($studentCount > 0) {
            if ($guardianCount < 1) {
                throw new RuntimeException('Messaging is paused because the required guardian is not present.');
            }
            if ($adultCount < 1) {
                throw new RuntimeException('This safeguarded conversation is incomplete and cannot accept messages.');
            }
        }

        if ($type === 'safeguarded' && $studentCount < 1) {
            throw new RuntimeException('This safeguarded conversation no longer has a student participant and requires review.');
        }
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) {
            throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        }
        return $row;
    }

    private static function channels(PDO $db): array
    {
        $sql = "SELECT c.id, c.name, c.channel_type, c.description, p.title AS production_title, COUNT(cp.id) AS post_count, MAX(cp.created_at) AS latest_at
                FROM channels c
                LEFT JOIN productions p ON p.id = c.production_id
                LEFT JOIN channel_posts cp ON cp.channel_id = c.id
                WHERE c.archived_at IS NULL
                GROUP BY c.id, c.name, c.channel_type, c.description, p.title, c.sort_order
                ORDER BY c.sort_order, c.name";
        return $db->query($sql)->fetchAll();
    }

    private static function channelById(PDO $db, int $channelId): ?array
    {
        if ($channelId < 1) {
            return null;
        }
        $stmt = $db->prepare("SELECT c.id, c.name, c.channel_type, c.description, p.title AS production_title FROM channels c LEFT JOIN productions p ON p.id = c.production_id WHERE c.id = :id AND c.archived_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $channelId]);
        $channel = $stmt->fetch();
        if (!$channel) {
            return null;
        }

        $posts = $db->prepare("SELECT cp.id, cp.body, cp.pinned, cp.reactions_json, cp.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author, u.display_role AS author_role, u.initials FROM channel_posts cp JOIN users u ON u.id = cp.author_user_id WHERE cp.channel_id = :channel_id ORDER BY cp.pinned DESC, cp.created_at DESC");
        $posts->execute(['channel_id' => $channelId]);
        $channel['posts'] = $posts->fetchAll();
        return $channel;
    }

    private static function conversations(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT c.id, c.subject, c.conversation_type, MAX(m.created_at) AS latest_at, COUNT(DISTINCT m.id) AS message_count, COUNT(DISTINCT cp.user_id) AS participant_count
            FROM conversations c
            JOIN conversation_participants mine ON mine.conversation_id = c.id AND mine.user_id = :user_id
            JOIN conversation_participants cp ON cp.conversation_id = c.id
            LEFT JOIN messages m ON m.conversation_id = c.id AND m.hidden_at IS NULL
            GROUP BY c.id, c.subject, c.conversation_type
            ORDER BY latest_at DESC, c.id DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private static function conversationById(PDO $db, int $userId, int $conversationId): ?array
    {
        if ($conversationId < 1) {
            return null;
        }

        $stmt = $db->prepare("SELECT c.id, c.subject, c.conversation_type FROM conversations c JOIN conversation_participants mine ON mine.conversation_id = c.id AND mine.user_id = :user_id WHERE c.id = :id LIMIT 1");
        $stmt->execute(['user_id' => $userId, 'id' => $conversationId]);
        $conversation = $stmt->fetch();
        if (!$conversation) {
            return null;
        }

        $participants = $db->prepare("SELECT cp.user_id, cp.participant_role, cp.guardian_required, u.active, CONCAT(u.first_name, ' ', u.last_name) AS name, u.display_role AS role, u.initials FROM conversation_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id = :conversation_id ORDER BY FIELD(cp.participant_role,'student','guardian','adult'), u.last_name, u.first_name");
        $participants->execute(['conversation_id' => $conversationId]);
        $conversation['participants'] = $participants->fetchAll();

        $messages = $db->prepare("SELECT m.id, m.body, m.created_at, m.sender_user_id, CONCAT(u.first_name, ' ', u.last_name) AS sender, u.display_role AS sender_role, u.initials FROM messages m JOIN users u ON u.id = m.sender_user_id WHERE m.conversation_id = :conversation_id AND m.hidden_at IS NULL ORDER BY m.created_at ASC, m.id ASC");
        $messages->execute(['conversation_id' => $conversationId]);
        $conversation['messages'] = $messages->fetchAll();

        try {
            self::assertConversationSafe((string)$conversation['conversation_type'], $conversation['participants']);
            $conversation['send_allowed'] = true;
            $conversation['safety_issue'] = null;
        } catch (RuntimeException $e) {
            $conversation['send_allowed'] = false;
            $conversation['safety_issue'] = $e->getMessage();
        }

        return $conversation;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['communication_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    private static function page(string $route, string $basePath, array $user, array $channels, array $conversations, ?array $selectedChannel, ?array $selectedConversation): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['communication_flash'] ?? null;
        unset($_SESSION['communication_flash']);

        $title = match ($route) {
            '/channels' => 'Community',
            '/channels/view' => $selectedChannel['name'] ?? 'Channel',
            '/messages' => 'Messages',
            default => $selectedConversation['subject'] ?? 'Conversation',
        };
        $section = str_starts_with($route, '/channels') ? 'Community' : 'Messages';

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#a6192e">
    <title><?= $esc($title) ?> · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/communication-implementation.css') ?>">
</head>
<body class="app-body">
<div class="unified-shell">
    <?php AppNavigation::renderSidebar($route, $basePath, $user); ?>
    <main class="unified-main">
        <?php AppNavigation::renderHeader($section, $title, $basePath); ?>
        <div class="comm-page">
            <?php if ($flash): ?><div class="comm-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>

            <?php if ($route === '/channels'): ?>
                <section class="comm-hero"><small>CTSMD COMMUNITY</small><h2>Updates belong somewhere people can find them again.</h2><p>Channels are organized around the theatre and current production. Posting permissions will be implemented once the role model explicitly defines them.</p></section>
                <div class="comm-channel-grid">
                    <?php foreach ($channels as $channel): ?>
                    <a class="comm-channel-card" href="<?= $url('/channels/view?id=' . (int)$channel['id']) ?>"><span>#</span><div><small><?= $esc($channel['production_title'] ?: strtoupper($channel['channel_type'])) ?></small><h3><?= $esc($channel['name']) ?></h3><p><?= $esc($channel['description'] ?: 'Community updates and discussion.') ?></p><footer><b><?= (int)$channel['post_count'] ?> posts</b><em><?= $channel['latest_at'] ? $esc(date('M j · g:i A', strtotime($channel['latest_at']))) : 'No posts yet' ?></em></footer></div></a>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($route === '/channels/view'): ?>
                <?php if (!$selectedChannel): ?>
                    <section class="comm-empty"><b>Channel not found</b><p>This channel may have been archived.</p><a class="button" href="<?= $url('/channels') ?>">Back to Community</a></section>
                <?php else: ?>
                    <section class="comm-channel-head"><div><small><?= $esc($selectedChannel['production_title'] ?: strtoupper($selectedChannel['channel_type'])) ?></small><h2># <?= $esc($selectedChannel['name']) ?></h2><p><?= $esc($selectedChannel['description'] ?: 'Community updates and discussion.') ?></p></div><a href="<?= $url('/channels') ?>">All channels →</a></section>
                    <section class="comm-feed">
                        <?php if (!$selectedChannel['posts']): ?><div class="comm-empty"><b>No posts yet</b><p>This channel is ready for its first update once posting permissions are implemented.</p></div><?php endif; ?>
                        <?php foreach ($selectedChannel['posts'] as $post): $reactions = json_decode((string)($post['reactions_json'] ?? '{}'), true) ?: []; ?>
                        <article class="comm-post<?= $post['pinned'] ? ' pinned' : '' ?>"><header><i><?= $esc($post['initials']) ?></i><div><b><?= $esc($post['author']) ?></b><small><?= $esc($post['author_role']) ?> · <?= $esc(date('M j · g:i A', strtotime($post['created_at']))) ?></small></div><?php if ($post['pinned']): ?><span>PINNED</span><?php endif; ?></header><p><?= nl2br($esc($post['body'])) ?></p><?php if ($reactions): ?><footer><?php foreach ($reactions as $reaction => $count): ?><span><?= $esc(str_replace('_', ' ', $reaction)) ?> <?= (int)$count ?></span><?php endforeach; ?></footer><?php endif; ?></article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

            <?php elseif ($route === '/messages'): ?>
                <section class="comm-message-intro"><div><small>YOUR CONVERSATIONS</small><h2>Messages with the safety structure visible.</h2><p>New conversation creation comes later. For now, you can reply only inside conversations where your membership and safety rules can be verified server-side.</p></div></section>
                <div class="comm-conversation-list">
                    <?php if (!$conversations): ?><div class="comm-empty"><b>No conversations yet</b><p>When you are included in a CTSMD conversation, it will appear here.</p></div><?php endif; ?>
                    <?php foreach ($conversations as $conversation): ?>
                    <a class="comm-conversation" href="<?= $url('/messages/thread?id=' . (int)$conversation['id']) ?>"><div class="comm-conversation-icon"><?= $conversation['conversation_type'] === 'safeguarded' ? '●' : '✉' ?></div><div><small><?= $conversation['conversation_type'] === 'safeguarded' ? 'SAFEGUARDED' : 'DIRECT' ?></small><h3><?= $esc($conversation['subject'] ?: 'Conversation') ?></h3><p><?= (int)$conversation['participant_count'] ?> participants · <?= (int)$conversation['message_count'] ?> messages</p></div><div class="comm-conversation-meta"><span><?= $conversation['latest_at'] ? $esc(date('M j · g:i A', strtotime($conversation['latest_at']))) : 'No messages' ?></span><b>Open →</b></div></a>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <?php if (!$selectedConversation): ?>
                    <section class="comm-empty"><b>Conversation not found</b><p>You may no longer have access to this conversation.</p><a class="button" href="<?= $url('/messages') ?>">Back to Messages</a></section>
                <?php else: ?>
                    <section class="comm-thread-head"><div><small><?= $selectedConversation['conversation_type'] === 'safeguarded' ? 'SAFEGUARDED CONVERSATION' : 'DIRECT CONVERSATION' ?></small><h2><?= $esc($selectedConversation['subject'] ?: 'Conversation') ?></h2><p><?= count($selectedConversation['participants']) ?> participants</p></div><a href="<?= $url('/messages') ?>">All messages →</a></section>

                    <?php if ($selectedConversation['conversation_type'] === 'safeguarded'): ?>
                    <div class="comm-safety-banner"><div><b>Guardian visibility is structural</b><span>A guardian is part of this conversation because a student and adult are communicating. Sending is blocked automatically if that structure becomes invalid.</span></div><strong>PROTECTED</strong></div>
                    <?php endif; ?>

                    <div class="comm-thread-layout">
                        <section class="comm-thread">
                            <?php foreach ($selectedConversation['messages'] as $message): $mine = (int)$message['sender_user_id'] === (int)$user['id']; ?>
                            <article class="comm-bubble<?= $mine ? ' mine' : '' ?>"><header><i><?= $esc($message['initials']) ?></i><span><b><?= $esc($message['sender']) ?></b><small><?= $esc($message['sender_role']) ?></small></span></header><p><?= nl2br($esc($message['body'])) ?></p><time><?= $esc(date('M j · g:i A', strtotime($message['created_at']))) ?></time></article>
                            <?php endforeach; ?>
                            <?php if (!$selectedConversation['messages']): ?><div class="comm-empty"><b>No messages yet</b><p>You can start this existing conversation below.</p></div><?php endif; ?>
                        </section>

                        <aside class="comm-participants"><small>PARTICIPANTS</small><h3>Who can see this</h3><?php foreach ($selectedConversation['participants'] as $participant): ?><div><i><?= $esc($participant['initials']) ?></i><span><b><?= $esc($participant['name']) ?></b><small><?= $esc(ucfirst($participant['participant_role'])) ?> · <?= $esc($participant['role']) ?></small></span></div><?php endforeach; ?></aside>
                    </div>

                    <section class="comm-composer">
                        <?php if ($selectedConversation['send_allowed']): ?>
                        <form method="post" action="<?= $url('/messages/thread?id=' . (int)$selectedConversation['id']) ?>"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['communication_csrf']) ?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="conversation_id" value="<?= (int)$selectedConversation['id'] ?>"><label for="message-body">Reply to everyone in this conversation</label><textarea id="message-body" name="body" rows="3" maxlength="4000" required placeholder="Write a message…"></textarea><div><small><?= $selectedConversation['conversation_type'] === 'safeguarded' ? 'The guardian remains included automatically.' : 'Everyone listed above can see your reply.' ?></small><button class="button" type="submit">Send message</button></div></form>
                        <?php else: ?>
                        <div class="comm-send-blocked"><b>Sending is paused</b><p><?= $esc((string)$selectedConversation['safety_issue']) ?></p><span>An administrator must repair the participant structure before this conversation can continue.</span></div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script>
</body>
</html>
<?php
        exit;
    }
}
