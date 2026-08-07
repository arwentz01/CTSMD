<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class SafeguardingExperience
{
    private const ROUTES = ['/safeguarding', '/safeguarding/review', '/safeguarding/audit'];

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
        if (!AccessPolicy::canManageSafeguarding($user)) {
            self::forbidden($basePath, $user);
        }

        $_SESSION['safeguarding_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, $basePath);
        }

        $credentials = self::credentialQueue($db);
        $conversationIssues = self::conversationIssues($db);
        $selectedCredential = null;
        if ($route === '/safeguarding/review') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selectedCredential = self::credentialById($db, (int)$id);
        }
        $audit = $route === '/safeguarding/audit' ? self::auditEvents($db) : [];

        self::page($route, $basePath, $user, $credentials, $conversationIssues, $selectedCredential, $audit);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['safeguarding_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/safeguarding');
        }

        $action = (string)($_POST['action'] ?? '');
        $credentialId = filter_input(INPUT_POST, 'credential_id', FILTER_VALIDATE_INT) ?: 0;
        if ($credentialId < 1 || !in_array($action, ['approve_credential', 'review_credential'], true)) {
            self::flash('error', 'That review action could not be completed.');
            self::redirect($basePath . '/safeguarding');
        }

        try {
            if ($action === 'approve_credential') {
                $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
                self::approveCredential($db, (int)$user['id'], (int)$credentialId, $expiresAt);
                self::flash('success', 'Credential approved and audit history recorded.');
            } else {
                self::returnCredentialToReview($db, (int)$user['id'], (int)$credentialId);
                self::flash('success', 'Credential left in review and audit history recorded.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/safeguarding/review?id=' . (int)$credentialId);
    }

    private static function approveCredential(PDO $db, int $actorId, int $credentialId, string $expiresAt): void
    {
        $db->beginTransaction();
        try {
            $credential = self::credentialForUpdate($db, $credentialId);
            if (!$credential) {
                throw new RuntimeException('That credential no longer exists.');
            }

            $normalizedExpiry = null;
            if ((bool)$credential['expires']) {
                if ($expiresAt === '') {
                    throw new RuntimeException('This requirement expires. Enter the verified expiration date before approving it.');
                }
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $expiresAt);
                if (!$date || $date->format('Y-m-d') !== $expiresAt) {
                    throw new RuntimeException('Enter a valid expiration date.');
                }
                $normalizedExpiry = $date->format('Y-m-d 23:59:59');
            }

            $before = (string)$credential['status'];
            $stmt = $db->prepare("UPDATE volunteer_credentials
                SET status = 'approved', completed_at = COALESCE(completed_at, CURRENT_TIMESTAMP), expires_at = :expires_at, verified_by_user_id = :actor
                WHERE id = :id");
            $stmt->bindValue(':expires_at', $normalizedExpiry, $normalizedExpiry === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':actor', $actorId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $credentialId, PDO::PARAM_INT);
            $stmt->execute();

            self::audit($db, $actorId, 'credential.approved', 'volunteer_credential', $credentialId,
                $credential['person_name'] . ' · ' . $credential['requirement_name'] . ' approved',
                ['from_status' => $before, 'to_status' => 'approved', 'expires_at' => $normalizedExpiry]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e instanceof RuntimeException ? $e : new RuntimeException('We could not complete that review.');
        }
    }

    private static function returnCredentialToReview(PDO $db, int $actorId, int $credentialId): void
    {
        $db->beginTransaction();
        try {
            $credential = self::credentialForUpdate($db, $credentialId);
            if (!$credential) {
                throw new RuntimeException('That credential no longer exists.');
            }
            $before = (string)$credential['status'];
            $stmt = $db->prepare("UPDATE volunteer_credentials SET status = 'review', verified_by_user_id = :actor WHERE id = :id");
            $stmt->execute(['actor' => $actorId, 'id' => $credentialId]);
            self::audit($db, $actorId, 'credential.review', 'volunteer_credential', $credentialId,
                $credential['person_name'] . ' · ' . $credential['requirement_name'] . ' requires review',
                ['from_status' => $before, 'to_status' => 'review']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e instanceof RuntimeException ? $e : new RuntimeException('We could not complete that review.');
        }
    }

    private static function audit(PDO $db, int $actorId, string $eventType, string $subjectType, int $subjectId, string $summary, array $metadata): void
    {
        $stmt = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
        $stmt->execute([
            'actor' => $actorId,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private static function credentialForUpdate(PDO $db, int $credentialId): ?array
    {
        $stmt = $db->prepare("SELECT vc.id, vc.status, vr.name AS requirement_name, vr.expires, CONCAT(u.first_name, ' ', u.last_name) AS person_name
            FROM volunteer_credentials vc
            JOIN volunteer_requirements vr ON vr.id = vc.requirement_id
            JOIN users u ON u.id = vc.user_id
            WHERE vc.id = :id FOR UPDATE");
        $stmt->execute(['id' => $credentialId]);
        return $stmt->fetch() ?: null;
    }

    private static function credentialQueue(PDO $db): array
    {
        return $db->query("SELECT vc.id, vc.status, vc.completed_at, vc.expires_at, vr.name AS requirement_name, vr.code, vr.expires,
            CONCAT(u.first_name, ' ', u.last_name) AS person_name, u.initials, u.display_role AS person_role
            FROM volunteer_credentials vc
            JOIN volunteer_requirements vr ON vr.id = vc.requirement_id
            JOIN users u ON u.id = vc.user_id
            WHERE vc.status IN ('pending','review','missing','expired')
               OR (vc.status = 'approved' AND vc.expires_at IS NOT NULL AND vc.expires_at < CURRENT_TIMESTAMP)
            ORDER BY FIELD(vc.status,'pending','review','expired','missing','approved'), u.last_name, vr.name")->fetchAll();
    }

    private static function credentialById(PDO $db, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $db->prepare("SELECT vc.id, vc.status, vc.completed_at, vc.expires_at, vc.verified_by_user_id, vr.name AS requirement_name, vr.category, vr.code, vr.expires,
            CONCAT(u.first_name, ' ', u.last_name) AS person_name, u.initials, u.display_role AS person_role,
            CONCAT(verifier.first_name, ' ', verifier.last_name) AS verifier_name
            FROM volunteer_credentials vc
            JOIN volunteer_requirements vr ON vr.id = vc.requirement_id
            JOIN users u ON u.id = vc.user_id
            LEFT JOIN users verifier ON verifier.id = vc.verified_by_user_id
            WHERE vc.id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private static function conversationIssues(PDO $db): array
    {
        $conversations = $db->query("SELECT c.id, c.subject, c.conversation_type,
            SUM(CASE WHEN cp.participant_role = 'student' AND u.active = 1 THEN 1 ELSE 0 END) AS students,
            SUM(CASE WHEN cp.participant_role = 'adult' AND u.active = 1 THEN 1 ELSE 0 END) AS adults,
            SUM(CASE WHEN cp.participant_role = 'guardian' AND u.active = 1 THEN 1 ELSE 0 END) AS guardians
            FROM conversations c
            JOIN conversation_participants cp ON cp.conversation_id = c.id
            JOIN users u ON u.id = cp.user_id
            GROUP BY c.id, c.subject, c.conversation_type
            ORDER BY c.id DESC")->fetchAll();

        $issues = [];
        foreach ($conversations as $row) {
            $students = (int)$row['students'];
            $adults = (int)$row['adults'];
            $guardians = (int)$row['guardians'];
            $reason = null;
            if ($students > 0 && $row['conversation_type'] !== 'safeguarded') {
                $reason = 'Student is present in a non-safeguarded conversation.';
            } elseif ($row['conversation_type'] === 'safeguarded' && $students < 1) {
                $reason = 'Safeguarded conversation has no active student.';
            } elseif ($students > 0 && $guardians < 1) {
                $reason = 'Required guardian is missing or inactive.';
            } elseif ($students > 0 && $adults < 1) {
                $reason = 'Safeguarded conversation has no active adult participant.';
            }
            if ($reason !== null) {
                $row['reason'] = $reason;
                $issues[] = $row;
            }
        }
        return $issues;
    }

    private static function auditEvents(PDO $db): array
    {
        return $db->query("SELECT ae.id, ae.event_type, ae.subject_type, ae.subject_id, ae.summary, ae.metadata_json, ae.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS actor_name, u.initials
            FROM audit_events ae
            LEFT JOIN users u ON u.id = ae.actor_user_id
            ORDER BY ae.created_at DESC, ae.id DESC LIMIT 100")->fetchAll();
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) {
            throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        }
        return $row;
    }

    private static function page(string $route, string $basePath, array $user, array $credentials, array $conversationIssues, ?array $selectedCredential, array $audit): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['safeguarding_flash'] ?? null;
        unset($_SESSION['safeguarding_flash']);
        $title = match ($route) {
            '/safeguarding/review' => $selectedCredential ? 'Credential review' : 'Review not found',
            '/safeguarding/audit' => 'Audit history',
            default => 'Safeguarding',
        };
        $subnav = [
            ['label' => 'Review queue', 'href' => '/safeguarding', 'active' => in_array($route, ['/safeguarding','/safeguarding/review'], true)],
            ['label' => 'Audit history', 'href' => '/safeguarding/audit', 'active' => $route === '/safeguarding/audit'],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/safeguarding-implementation.css') ?>"></head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Restricted operations', $title, $basePath, $subnav); ?><div class="safe-page"><?php if ($flash): ?><div class="safe-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
<?php if ($route === '/safeguarding'): ?>
<section class="safe-hero"><div><small>RESTRICTED WORKSPACE</small><h2>Review the exception, not the person.</h2><p>Credential exceptions and conversation-integrity problems are surfaced here. Decisions are recorded in an audit trail.</p></div><div class="safe-metrics"><span><b><?= count($credentials) ?></b> credential reviews</span><span><b><?= count($conversationIssues) ?></b> conversation issues</span></div></section>
<div class="safe-layout"><section class="safe-panel"><header><small>VOLUNTEER CREDENTIALS</small><h3>Needs review</h3></header><?php if (!$credentials): ?><div class="safe-empty"><b>Credential queue clear</b><p>No pending or exception credentials currently need a staff decision.</p></div><?php endif; ?><?php foreach ($credentials as $item): ?><a class="safe-review-row" href="<?= $url('/safeguarding/review?id=' . (int)$item['id']) ?>"><i><?= $esc($item['initials']) ?></i><div><b><?= $esc($item['person_name']) ?></b><span><?= $esc($item['requirement_name']) ?> · <?= $esc(ucfirst($item['status'])) ?></span></div><em>Review →</em></a><?php endforeach; ?></section>
<section class="safe-panel"><header><small>CONVERSATION INTEGRITY</small><h3>Structural safety checks</h3></header><?php if (!$conversationIssues): ?><div class="safe-empty good"><b>All conversation structures pass</b><p>No current conversation violates the enforced guardian/adult/student structure.</p></div><?php endif; ?><?php foreach ($conversationIssues as $issue): ?><article class="safe-conversation-issue"><b><?= $esc($issue['subject'] ?: 'Conversation #' . $issue['id']) ?></b><p><?= $esc($issue['reason']) ?></p><a href="<?= $url('/messages/thread?id=' . (int)$issue['id']) ?>">Inspect conversation →</a></article><?php endforeach; ?></section></div>
<?php elseif ($route === '/safeguarding/review'): ?>
<?php if (!$selectedCredential): ?><section class="safe-empty"><b>Review not found</b><p>This credential may have been removed.</p><a class="button" href="<?= $url('/safeguarding') ?>">Back to queue</a></section><?php else: ?>
<section class="safe-review-head"><div><small>CREDENTIAL REVIEW</small><h2><?= $esc($selectedCredential['person_name']) ?></h2><p><?= $esc($selectedCredential['person_role']) ?> · <?= $esc($selectedCredential['requirement_name']) ?></p></div><span class="safe-status"><?= $esc(ucfirst($selectedCredential['status'])) ?></span></section>
<div class="safe-review-layout"><section class="safe-panel"><header><small>EVIDENCE RECORD</small><h3>Current credential data</h3></header><dl class="safe-facts"><div><dt>Requirement</dt><dd><?= $esc($selectedCredential['requirement_name']) ?></dd></div><div><dt>Category</dt><dd><?= $esc(ucfirst(str_replace('_',' ', $selectedCredential['category']))) ?></dd></div><div><dt>Completed</dt><dd><?= $selectedCredential['completed_at'] ? $esc(date('M j, Y', strtotime($selectedCredential['completed_at']))) : 'Not recorded' ?></dd></div><div><dt>Expiration</dt><dd><?= $selectedCredential['expires_at'] ? $esc(date('M j, Y', strtotime($selectedCredential['expires_at']))) : ((bool)$selectedCredential['expires'] ? 'Required before approval' : 'Does not expire') ?></dd></div><div><dt>Last verifier</dt><dd><?= $esc($selectedCredential['verifier_name'] ?: 'Not verified') ?></dd></div></dl></section>
<section class="safe-panel decision"><header><small>DECISION</small><h3>Record a review outcome</h3></header><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['safeguarding_csrf']) ?>"><input type="hidden" name="credential_id" value="<?= (int)$selectedCredential['id'] ?>"><?php if ((bool)$selectedCredential['expires']): ?><label>Verified expiration date<input type="date" name="expires_at" value="<?= $selectedCredential['expires_at'] ? $esc(date('Y-m-d', strtotime($selectedCredential['expires_at']))) : '' ?>"></label><?php endif; ?><div class="safe-actions"><button class="button" type="submit" name="action" value="approve_credential">Approve credential</button><button class="button secondary" type="submit" name="action" value="review_credential">Keep in review</button></div><p>Every decision records the acting staff member, status transition, subject, and timestamp.</p></form></section></div>
<?php endif; ?>
<?php else: ?>
<section class="safe-audit-head"><div><small>IMMUTABLE HISTORY</small><h2>Audit trail</h2><p>Recent safeguarded staff decisions, newest first.</p></div></section><div class="safe-audit-list"><?php if (!$audit): ?><div class="safe-empty"><b>No audited actions yet</b><p>Credential decisions made from this workspace will appear here.</p></div><?php endif; ?><?php foreach ($audit as $event): ?><article><i><?= $esc($event['initials'] ?: '—') ?></i><div><b><?= $esc($event['summary']) ?></b><span><?= $esc($event['actor_name'] ?: 'System') ?> · <?= $esc(date('M j, Y · g:i A', strtotime($event['created_at']))) ?></span><small><?= $esc($event['event_type']) ?> · <?= $esc($event['subject_type']) ?> #<?= (int)$event['subject_id'] ?></small></div></article><?php endforeach; ?></div>
<?php endif; ?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        http_response_code(403);
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/safeguarding-implementation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/safeguarding', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Restricted operations', 'Restricted', $basePath); ?><div class="safe-page"><section class="safe-forbidden"><span>RESTRICTED</span><h2>Safeguarding review is staff-only.</h2><p>Your current role, <?= $esc((string)$user['role']) ?>, cannot access credential decisions or conversation-integrity review.</p><a class="button" href="<?= $url('/app') ?>">Return home</a></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['safeguarding_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
