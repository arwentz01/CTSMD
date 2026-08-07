<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/CommunicationReadStateService.php';

final class CommunicationExperience
{
    private const ROUTES = ['/channels', '/channels/view', '/messages', '/messages/new', '/messages/thread'];

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
            self::handlePost($db, $user, $basePath);
        }

        $channels = self::channels($db);
        $conversations = self::conversations($db, $userId);
        $recipients = $route === '/messages/new' ? self::availableRecipients($db, $user) : [];
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

        self::page($route, $basePath, $user, $channels, $conversations, $recipients, $selectedChannel, $selectedConversation);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['communication_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/messages');
        }

        $action = (string)($_POST['action'] ?? '');
        $userId = (int)$user['id'];

        if ($action === 'create_conversation') {
            $recipientId = filter_input(INPUT_POST, 'recipient_id', FILTER_VALIDATE_INT) ?: 0;
            $subject = trim((string)($_POST['subject'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            try {
                $conversationId = self::createConversation($db, $user, (int)$recipientId, $subject, $body);
                self::flash('success', 'Conversation created with the required safety participants included.');
                self::redirect($basePath . '/messages/thread?id=' . $conversationId);
            } catch (RuntimeException $e) {
                self::flash('error', $e->getMessage());
                self::redirect($basePath . '/messages/new');
            }
        }

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

    private static function createConversation(PDO $db, array $initiator, int $recipientId, string $subject, string $body): int
    {
        if ($recipientId < 1 || $recipientId === (int)$initiator['id']) {
            throw new RuntimeException('Choose another active CTSMD member.');
        }
        if ($subject === '' || mb_strlen($subject) > 190) {
            throw new RuntimeException('Add a subject up to 190 characters.');
        }
        if ($body === '' || mb_strlen($body) > 4000) {
            throw new RuntimeException('Add an opening message up to 4,000 characters.');
        }

        $recipientStmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, active FROM users WHERE id = :id LIMIT 1");
        $recipientStmt->execute(['id' => $recipientId]);
        $recipient = $recipientStmt->fetch();
        if (!$recipient || !(bool)$recipient['active']) {
            throw new RuntimeException('That recipient is not available.');
        }

        $initiatorStudent = self::isStudentRole((string)$initiator['role']);
        $recipientStudent = self::isStudentRole((string)$recipient['role']);
        if ($initiatorStudent && $recipientStudent) {
            throw new RuntimeException('Student-to-student direct messaging is not available.');
        }

        $type = 'direct';
        $participants = [];

        if ($initiatorStudent || $recipientStudent) {
            $student = $initiatorStudent ? $initiator : $recipient;
            $adult = $initiatorStudent ? $recipient : $initiator;

            if (!self::isStaffRole((string)$adult['role'])) {
                throw new RuntimeException('New student conversations must involve CTSMD staff. Family-to-student direct threads are not created here.');
            }

            $guardian = self::primaryGuardian($db, (int)$student['id']);
            if (!$guardian) {
                throw new RuntimeException('This student does not have an active guardian relationship. Messaging cannot begin until staff repairs that relationship.');
            }
            if ((int)$guardian['id'] === (int)$adult['id']) {
                throw new RuntimeException('A separate active guardian is required before this student conversation can begin.');
            }

            $type = 'safeguarded';
            $participants = [
                ['id' => (int)$adult['id'], 'role' => 'adult', 'guardian_required' => 0],
                ['id' => (int)$student['id'], 'role' => 'student', 'guardian_required' => 0],
                ['id' => (int)$guardian['id'], 'role' => 'guardian', 'guardian_required' => 1],
            ];
        } else {
            $participants = [
                ['id' => (int)$initiator['id'], 'role' => 'adult', 'guardian_required' => 0],
                ['id' => (int)$recipient['id'], 'role' => 'adult', 'guardian_required' => 0],
            ];
        }

        self::assertConversationSafe($type, array_map(static fn(array $p): array => $p + ['active' => 1], $participants));

        $db->beginTransaction();
        try {
            $insertConversation = $db->prepare('INSERT INTO conversations (subject, conversation_type, created_at) VALUES (:subject, :type, CURRENT_TIMESTAMP)');
            $insertConversation->execute(['subject' => $subject, 'type' => $type]);
            $conversationId = (int)$db->lastInsertId();

            $insertParticipant = $db->prepare('INSERT INTO conversation_participants (conversation_id, user_id, participant_role, guardian_required) VALUES (:conversation_id, :user_id, :participant_role, :guardian_required)');
            foreach ($participants as $participant) {
                $insertParticipant->execute([
                    'conversation_id' => $conversationId,
                    'user_id' => $participant['id'],
                    'participant_role' => $participant['role'],
                    'guardian_required' => $participant['guardian_required'],
                ]);
            }

            $insertMessage = $db->prepare('INSERT INTO messages (conversation_id, sender_user_id, body, created_at) VALUES (:conversation_id, :sender_user_id, :body, CURRENT_TIMESTAMP)');
            $insertMessage->execute(['conversation_id' => $conversationId, 'sender_user_id' => (int)$initiator['id'], 'body' => $body]);

            $db->commit();
            return $conversationId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('We could not create that conversation. Please try again.');
        }
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
            if (!$membershipStmt->fetch()) {
                throw new RuntimeException('You do not have access to that conversation.');
            }

            $participantsStmt = $db->prepare('SELECT cp.user_id, cp.participant_role, cp.guardian_required, u.active FROM conversation_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.conversation_id = :conversation_id FOR UPDATE');
            $participantsStmt->execute(['conversation_id' => $conversationId]);
            self::assertConversationSafe((string)$conversation['conversation_type'], $participantsStmt->fetchAll());

            $insert = $db->prepare('INSERT INTO messages (conversation_id, sender_user_id, body, created_at) VALUES (:conversation_id, :sender_user_id, :body, CURRENT_TIMESTAMP)');
            $insert->execute(['conversation_id' => $conversationId, 'sender_user_id' => $userId, 'body' => $body]);
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
        if ($studentCount > 1) {
            throw new RuntimeException('Student-to-student messaging is not available.');
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

    private static function isStudentRole(string $role): bool
    {
        return str_contains(strtolower($role), 'student');
    }

    private static function isStaffRole(string $role): bool
    {
        $role = strtolower($role);
        return str_contains($role, 'staff') || str_contains($role, 'manager') || str_contains($role, 'admin') || str_contains($role, 'director');
    }

    private static function primaryGuardian(PDO $db, int $studentId): ?array
    {
        $stmt = $db->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.display_role AS role, fr.relationship_type, fr.is_primary
            FROM family_relationships fr
            JOIN users u ON u.id = fr.guardian_user_id
            WHERE fr.student_user_id = :student_id AND fr.status = 'active' AND u.active = 1
            ORDER BY fr.is_primary DESC, fr.id ASC LIMIT 1");
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetch() ?: null;
    }

    private static function availableRecipients(PDO $db, array $user): array
    {
        $stmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE active = 1 AND id <> :user_id ORDER BY last_name, first_name");
        $stmt->execute(['user_id' => (int)$user['id']]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['student'] = self::isStudentRole((string)$row['role']);
            $row['selectable'] = !$row['student'] || self::isStaffRole((string)$user['role']);
        }
        unset($row);
        return $rows;
    }

    private static function channels(PDO $db): array
    {
        $sql = "SELECT c.id, c.name, c.channel_type, c.description, p.title AS production_title, COUNT(cp.id) AS post_count, MAX(cp.created_at) AS latest_at
                FROM channels c LEFT JOIN productions p ON p.id = c.production_id LEFT JOIN channel_posts cp ON cp.channel_id = c.id
                WHERE c.archived_at IS NULL GROUP BY c.id, c.name, c.channel_type, c.description, p.title, c.sort_order ORDER BY c.sort_order, c.name";
        return $db->query($sql)->fetchAll();
    }

    private static function channelById(PDO $db, int $channelId): ?array
    {
        if ($channelId < 1) return null;
        $stmt = $db->prepare("SELECT c.id, c.name, c.channel_type, c.description, p.title AS production_title FROM channels c LEFT JOIN productions p ON p.id = c.production_id WHERE c.id = :id AND c.archived_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $channelId]);
        $channel = $stmt->fetch();
        if (!$channel) return null;
        $posts = $db->prepare("SELECT cp.id, cp.body, cp.pinned, cp.reactions_json, cp.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author, u.display_role AS author_role, u.initials FROM channel_posts cp JOIN users u ON u.id = cp.author_user_id WHERE cp.channel_id = :channel_id ORDER BY cp.pinned DESC, cp.created_at DESC");
        $posts->execute(['channel_id' => $channelId]);
        $channel['posts'] = $posts->fetchAll();
        return $channel;
    }

    private static function conversations(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT c.id, c.subject, c.conversation_type, MAX(m.created_at) AS latest_at, COUNT(DISTINCT m.id) AS message_count, COUNT(DISTINCT cp.user_id) AS participant_count,
            (SELECT COUNT(*) FROM messages um WHERE um.conversation_id = c.id AND um.hidden_at IS NULL AND um.id > mine.last_read_message_id AND um.sender_user_id <> mine.user_id) AS unread_count
            FROM conversations c JOIN conversation_participants mine ON mine.conversation_id = c.id AND mine.user_id = :user_id
            JOIN conversation_participants cp ON cp.conversation_id = c.id LEFT JOIN messages m ON m.conversation_id = c.id AND m.hidden_at IS NULL
            GROUP BY c.id, c.subject, c.conversation_type, mine.last_read_message_id, mine.user_id ORDER BY latest_at DESC, c.id DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private static function conversationById(PDO $db, int $userId, int $conversationId): ?array
    {
        if ($conversationId < 1) return null;
        $stmt = $db->prepare("SELECT c.id, c.subject, c.conversation_type FROM conversations c JOIN conversation_participants mine ON mine.conversation_id = c.id AND mine.user_id = :user_id WHERE c.id = :id LIMIT 1");
        $stmt->execute(['user_id' => $userId, 'id' => $conversationId]);
        $conversation = $stmt->fetch();
        if (!$conversation) return null;

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

    private static function page(string $route, string $basePath, array $user, array $channels, array $conversations, array $recipients, ?array $selectedChannel, ?array $selectedConversation): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['communication_flash'] ?? null;
        unset($_SESSION['communication_flash']);
        $title = match ($route) {
            '/channels' => 'Community', '/channels/view' => $selectedChannel['name'] ?? 'Channel', '/messages' => 'Messages', '/messages/new' => 'New conversation', default => $selectedConversation['subject'] ?? 'Conversation',
        };
        $section = str_starts_with($route, '/channels') ? 'Community' : 'Messages';

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/communication-implementation.css') ?>"></head><body class="app-body"><div class="unified-shell">
        <?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader($section, $title, $basePath); ?><div class="comm-page">
        <?php if ($flash): ?><div class="comm-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>

        <?php if ($route === '/channels'): ?>
            <section class="comm-hero"><small>CTSMD COMMUNITY</small><h2>Updates belong somewhere people can find them again.</h2><p>Channels are organized around the theatre and current production. Posting permissions will be implemented once the role model explicitly defines them.</p></section><div class="comm-channel-grid"><?php foreach ($channels as $channel): ?><a class="comm-channel-card" href="<?= $url('/channels/view?id=' . (int)$channel['id']) ?>"><span>#</span><div><small><?= $esc($channel['production_title'] ?: strtoupper($channel['channel_type'])) ?></small><h3><?= $esc($channel['name']) ?></h3><p><?= $esc($channel['description'] ?: 'Community updates and discussion.') ?></p><footer><b><?= (int)$channel['post_count'] ?> posts</b><em><?= $channel['latest_at'] ? $esc(date('M j · g:i A', strtotime($channel['latest_at']))) : 'No posts yet' ?></em></footer></div></a><?php endforeach; ?></div>
        <?php elseif ($route === '/channels/view'): ?>
            <?php if (!$selectedChannel): ?><section class="comm-empty"><b>Channel not found</b><p>This channel may have been archived.</p><a class="button" href="<?= $url('/channels') ?>">Back to Community</a></section><?php else: ?><section class="comm-channel-head"><div><small><?= $esc($selectedChannel['production_title'] ?: strtoupper($selectedChannel['channel_type'])) ?></small><h2># <?= $esc($selectedChannel['name']) ?></h2><p><?= $esc($selectedChannel['description'] ?: 'Community updates and discussion.') ?></p></div><a href="<?= $url('/channels') ?>">All channels →</a></section><section class="comm-feed"><?php if (!$selectedChannel['posts']): ?><div class="comm-empty"><b>No posts yet</b><p>This channel is ready for its first update once posting permissions are implemented.</p></div><?php endif; ?><?php foreach ($selectedChannel['posts'] as $post): $reactions = json_decode((string)($post['reactions_json'] ?? '{}'), true) ?: []; ?><article class="comm-post<?= $post['pinned'] ? ' pinned' : '' ?>"><header><i><?= $esc($post['initials']) ?></i><div><b><?= $esc($post['author']) ?></b><small><?= $esc($post['author_role']) ?> · <?= $esc(date('M j · g:i A', strtotime($post['created_at']))) ?></small></div><?php if ($post['pinned']): ?><span>PINNED</span><?php endif; ?></header><p><?= nl2br($esc($post['body'])) ?></p><?php if ($reactions): ?><footer><?php foreach ($reactions as $reaction => $count): ?><span><?= $esc(str_replace('_', ' ', $reaction)) ?> <?= (int)$count ?></span><?php endforeach; ?></footer><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?>
        <?php elseif ($route === '/messages'): ?>
            <section class="comm-message-intro"><div><small>YOUR CONVERSATIONS</small><h2>Messages with the safety structure visible.</h2><p>Every conversation follows your account across productions. Unread messages stay visible here regardless of which Working Production staff has selected.</p></div><a class="button" href="<?= $url('/messages/new') ?>">New conversation</a></section><div class="comm-conversation-list"><?php if (!$conversations): ?><div class="comm-empty"><b>No conversations yet</b><p>Start one with another CTSMD member.</p></div><?php endif; ?><?php foreach ($conversations as $conversation): $unread=(int)($conversation['unread_count']??0); ?><a class="comm-conversation<?= $unread>0?' unread':'' ?>" href="<?= $url('/messages/thread?id=' . (int)$conversation['id']) ?>"><div class="comm-conversation-icon"><?= $conversation['conversation_type'] === 'safeguarded' ? '●' : '✉' ?></div><div><small><?= $conversation['conversation_type'] === 'safeguarded' ? 'SAFEGUARDED' : 'DIRECT' ?></small><h3><?= $esc($conversation['subject'] ?: 'Conversation') ?></h3><p><?= (int)$conversation['participant_count'] ?> participants · <?= (int)$conversation['message_count'] ?> messages</p></div><div class="comm-conversation-meta"><span><?= $conversation['latest_at'] ? $esc(date('M j · g:i A', strtotime($conversation['latest_at']))) : 'No messages' ?></span><?php if($unread>0):?><strong class="comm-unread"><?=$unread?> new</strong><?php else:?><b>Open →</b><?php endif;?></div></a><?php endforeach; ?></div>
        <?php elseif ($route === '/messages/new'): ?>
            <section class="comm-channel-head"><div><small>NEW CONVERSATION</small><h2>Choose who you need to reach.</h2><p>Safety rules are resolved by the server. You cannot remove required guardians or downgrade a safeguarded thread to direct.</p></div><a href="<?= $url('/messages') ?>">Cancel →</a></section>
            <form class="comm-new-form" method="post" action="<?= $url('/messages/new') ?>"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['communication_csrf']) ?>"><input type="hidden" name="action" value="create_conversation"><label>Recipient<select name="recipient_id" required><option value="">Choose a member</option><?php foreach ($recipients as $recipient): ?><option value="<?= (int)$recipient['id'] ?>"<?= !$recipient['selectable'] ? ' disabled' : '' ?>><?= $esc($recipient['name']) ?> — <?= $esc($recipient['role']) ?><?= $recipient['student'] && !$recipient['selectable'] ? ' (staff contact required)' : '' ?></option><?php endforeach; ?></select></label><label>Subject<input name="subject" maxlength="190" required placeholder="What is this about?"></label><label>Opening message<textarea name="body" rows="6" maxlength="4000" required placeholder="Write your message…"></textarea></label><div class="comm-safety-banner"><div><b>Safety is automatic</b><span>Adult-to-adult conversations are direct. Staff-to-student conversations become safeguarded and include the student’s active primary guardian automatically. Student-to-student messaging remains disabled.</span></div><strong>SERVER ENFORCED</strong></div><div class="comm-new-actions"><a href="<?= $url('/messages') ?>">Cancel</a><button class="button" type="submit">Create conversation</button></div></form>
        <?php else: ?>
            <?php if (!$selectedConversation): ?><section class="comm-empty"><b>Conversation not found</b><p>You may no longer have access to this conversation.</p><a class="button" href="<?= $url('/messages') ?>">Back to Messages</a></section><?php else: ?><section class="comm-thread-head"><div><small><?= $selectedConversation['conversation_type'] === 'safeguarded' ? 'SAFEGUARDED CONVERSATION' : 'DIRECT CONVERSATION' ?></small><h2><?= $esc($selectedConversation['subject'] ?: 'Conversation') ?></h2><p><?= count($selectedConversation['participants']) ?> participants</p></div><a href="<?= $url('/messages') ?>">All messages →</a></section><?php if ($selectedConversation['conversation_type'] === 'safeguarded'): ?><div class="comm-safety-banner"><div><b>Guardian visibility is structural</b><span>A guardian is part of this conversation because a student and adult are communicating. Sending is blocked automatically if that structure becomes invalid.</span></div><strong>PROTECTED</strong></div><?php endif; ?><div class="comm-thread-layout"><section class="comm-thread"><?php foreach ($selectedConversation['messages'] as $message): $mine = (int)$message['sender_user_id'] === (int)$user['id']; ?><article class="comm-bubble<?= $mine ? ' mine' : '' ?>"><header><i><?= $esc($message['initials']) ?></i><span><b><?= $esc($message['sender']) ?></b><small><?= $esc($message['sender_role']) ?></small></span></header><p><?= nl2br($esc($message['body'])) ?></p><time><?= $esc(date('M j · g:i A', strtotime($message['created_at']))) ?></time></article><?php endforeach; ?><?php if (!$selectedConversation['messages']): ?><div class="comm-empty"><b>No messages yet</b></div><?php endif; ?></section><aside class="comm-participants"><small>PARTICIPANTS</small><h3>Who can see this</h3><?php foreach ($selectedConversation['participants'] as $participant): ?><div><i><?= $esc($participant['initials']) ?></i><span><b><?= $esc($participant['name']) ?></b><small><?= $esc(ucfirst($participant['participant_role'])) ?> · <?= $esc($participant['role']) ?></small></span></div><?php endforeach; ?></aside></div><section class="comm-composer"><?php if ($selectedConversation['send_allowed']): ?><form method="post" action="<?= $url('/messages/thread?id=' . (int)$selectedConversation['id']) ?>"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['communication_csrf']) ?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="conversation_id" value="<?= (int)$selectedConversation['id'] ?>"><label for="message-body">Reply to everyone in this conversation</label><textarea id="message-body" name="body" rows="3" maxlength="4000" required placeholder="Write a message…"></textarea><div><small><?= $selectedConversation['conversation_type'] === 'safeguarded' ? 'The guardian remains included automatically.' : 'Everyone listed above can see your reply.' ?></small><button class="button" type="submit">Send message</button></div></form><?php else: ?><div class="comm-send-blocked"><b>Sending is paused</b><p><?= $esc((string)$selectedConversation['safety_issue']) ?></p><span>An administrator must repair the participant structure before this conversation can continue.</span></div><?php endif; ?></section><?php endif; ?>
        <?php endif; ?></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
}
