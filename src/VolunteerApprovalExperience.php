<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class VolunteerApprovalExperience
{
    private const ROUTES = ['/volunteer/approvals', '/admin/volunteer-approvals', '/admin/volunteer-approvals/review'];

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
        $_SESSION['volunteer_approval_csrf'] ??= bin2hex(random_bytes(24));

        $staffRoute = str_starts_with($route, '/admin/');
        if ($staffRoute && !AccessPolicy::isStaff($user)) {
            self::forbidden($basePath, $user);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $route, $basePath, $user);
        }

        if ($route === '/volunteer/approvals') {
            self::volunteerPage($db, $basePath, $user);
        }

        $requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        self::staffPage($db, $route, $basePath, $user, (int)$requestId);
    }

    private static function handlePost(PDO $db, string $route, string $basePath, array $user): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['volunteer_approval_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . ($route === '/volunteer/approvals' ? '/volunteer/approvals' : '/admin/volunteer-approvals'));
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($route === '/volunteer/approvals') {
                if ($action === 'request') {
                    $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT) ?: 0;
                    self::requestApproval($db, $user, (int)$shiftId, trim((string)($_POST['request_note'] ?? '')));
                    self::flash('success', 'Approval request submitted. The shift is not reserved until staff approves it.');
                } elseif ($action === 'withdraw') {
                    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT) ?: 0;
                    self::withdrawRequest($db, $user, (int)$requestId);
                    self::flash('success', 'Approval request withdrawn.');
                }
                self::redirect($basePath . '/volunteer/approvals');
            }

            if (!AccessPolicy::isStaff($user)) {
                self::forbidden($basePath, $user);
            }

            $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT) ?: 0;
            $decisionNote = trim((string)($_POST['decision_note'] ?? ''));
            if ($action === 'approve') {
                self::decide($db, $user, (int)$requestId, true, $decisionNote);
                self::flash('success', 'Volunteer approved and added to the shift.');
            } elseif ($action === 'decline') {
                self::decide($db, $user, (int)$requestId, false, $decisionNote);
                self::flash('success', 'Approval request declined. The volunteer was notified.');
            }
            self::redirect($basePath . '/admin/volunteer-approvals');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            $fallback = $route === '/volunteer/approvals' ? '/volunteer/approvals' : '/admin/volunteer-approvals';
            self::redirect($basePath . $fallback);
        }
    }

    private static function requestApproval(PDO $db, array $user, int $shiftId, string $note): void
    {
        if ($shiftId < 1) {
            throw new RuntimeException('That volunteer shift could not be found.');
        }
        if (mb_strlen($note) > 500) {
            throw new RuntimeException('Keep the request note under 500 characters.');
        }

        $db->beginTransaction();
        try {
            $profile = $db->prepare('SELECT active FROM volunteer_profiles WHERE user_id = :user_id FOR UPDATE');
            $profile->execute(['user_id' => (int)$user['id']]);
            if (!(bool)$profile->fetchColumn()) {
                throw new RuntimeException('Your account does not have an active volunteer profile.');
            }

            $shiftStmt = $db->prepare('SELECT id, title, approval_required, required_slots FROM volunteer_shifts WHERE id = :id FOR UPDATE');
            $shiftStmt->execute(['id' => $shiftId]);
            $shift = $shiftStmt->fetch();
            if (!$shift) {
                throw new RuntimeException('That volunteer shift no longer exists.');
            }
            if (!(bool)$shift['approval_required']) {
                throw new RuntimeException('That shift does not require approval; use the normal signup action instead.');
            }

            $missing = self::missingRequirements($db, (int)$user['id'], $shiftId);
            if ($missing) {
                throw new RuntimeException('You cannot request this role yet. Complete: ' . implode(', ', $missing) . '.');
            }

            $confirmed = $db->prepare("SELECT COUNT(*) FROM volunteer_shift_signups WHERE shift_id = :shift_id AND user_id = :user_id AND status IN ('signed_up','checked_in','completed')");
            $confirmed->execute(['shift_id' => $shiftId, 'user_id' => (int)$user['id']]);
            if ((int)$confirmed->fetchColumn() > 0) {
                throw new RuntimeException('You are already confirmed for this shift.');
            }

            $existing = $db->prepare('SELECT id, status FROM volunteer_shift_approval_requests WHERE shift_id = :shift_id AND user_id = :user_id FOR UPDATE');
            $existing->execute(['shift_id' => $shiftId, 'user_id' => (int)$user['id']]);
            $request = $existing->fetch();
            if ($request && $request['status'] === 'pending') {
                throw new RuntimeException('You already have a pending request for this shift.');
            }

            if ($request) {
                $save = $db->prepare("UPDATE volunteer_shift_approval_requests SET status = 'pending', request_note = :note, decision_note = NULL, reviewed_by_user_id = NULL, reviewed_at = NULL, requested_at = CURRENT_TIMESTAMP WHERE id = :id");
                $save->execute(['note' => $note !== '' ? $note : null, 'id' => (int)$request['id']]);
                $requestId = (int)$request['id'];
            } else {
                $save = $db->prepare("INSERT INTO volunteer_shift_approval_requests (shift_id, user_id, status, request_note) VALUES (:shift_id, :user_id, 'pending', :note)");
                $save->execute(['shift_id' => $shiftId, 'user_id' => (int)$user['id'], 'note' => $note !== '' ? $note : null]);
                $requestId = (int)$db->lastInsertId();
            }

            self::audit($db, (int)$user['id'], 'volunteer.approval_requested', 'volunteer_shift_approval_request', $requestId, 'Requested approval for volunteer shift.', [
                'shift_id' => $shiftId,
                'shift_title' => $shift['title'],
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The approval request could not be submitted.');
        }
    }

    private static function withdrawRequest(PDO $db, array $user, int $requestId): void
    {
        if ($requestId < 1) throw new RuntimeException('That request could not be found.');
        $stmt = $db->prepare("UPDATE volunteer_shift_approval_requests SET status = 'withdrawn', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id AND status = 'pending'");
        $stmt->execute(['id' => $requestId, 'user_id' => (int)$user['id']]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('That request is no longer pending or does not belong to you.');
        self::audit($db, (int)$user['id'], 'volunteer.approval_withdrawn', 'volunteer_shift_approval_request', $requestId, 'Withdrew volunteer shift approval request.', []);
    }

    private static function decide(PDO $db, array $reviewer, int $requestId, bool $approve, string $note): void
    {
        if ($requestId < 1) throw new RuntimeException('That approval request could not be found.');
        if (mb_strlen($note) > 500) throw new RuntimeException('Keep the decision note under 500 characters.');

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT r.id, r.shift_id, r.user_id, r.status, vs.title, vs.required_slots, vs.approval_required, CONCAT(u.first_name, ' ', u.last_name) AS volunteer_name FROM volunteer_shift_approval_requests r JOIN volunteer_shifts vs ON vs.id = r.shift_id JOIN users u ON u.id = r.user_id WHERE r.id = :id FOR UPDATE");
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch();
            if (!$request || $request['status'] !== 'pending') {
                throw new RuntimeException('That request is no longer pending.');
            }

            if ($approve) {
                if (!(bool)$request['approval_required']) {
                    throw new RuntimeException('This shift no longer requires approval. Review the shift before continuing.');
                }
                $missing = self::missingRequirements($db, (int)$request['user_id'], (int)$request['shift_id']);
                if ($missing) {
                    throw new RuntimeException('Approval blocked because the volunteer is no longer eligible: ' . implode(', ', $missing) . '.');
                }
                $count = $db->prepare("SELECT COUNT(*) FROM volunteer_shift_signups WHERE shift_id = :shift_id AND status IN ('signed_up','checked_in','completed')");
                $count->execute(['shift_id' => (int)$request['shift_id']]);
                if ((int)$count->fetchColumn() >= (int)$request['required_slots']) {
                    throw new RuntimeException('This shift is now full. Decline the request or expand capacity first.');
                }

                $existing = $db->prepare('SELECT id, status FROM volunteer_shift_signups WHERE shift_id = :shift_id AND user_id = :user_id FOR UPDATE');
                $existing->execute(['shift_id' => (int)$request['shift_id'], 'user_id' => (int)$request['user_id']]);
                $signup = $existing->fetch();
                if ($signup) {
                    $saveSignup = $db->prepare("UPDATE volunteer_shift_signups SET status = 'signed_up', created_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $saveSignup->execute(['id' => (int)$signup['id']]);
                } else {
                    $saveSignup = $db->prepare("INSERT INTO volunteer_shift_signups (shift_id, user_id, status) VALUES (:shift_id, :user_id, 'signed_up')");
                    $saveSignup->execute(['shift_id' => (int)$request['shift_id'], 'user_id' => (int)$request['user_id']]);
                }
            }

            $status = $approve ? 'approved' : 'declined';
            $update = $db->prepare('UPDATE volunteer_shift_approval_requests SET status = :status, decision_note = :note, reviewed_by_user_id = :reviewer, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id');
            $update->execute([
                'status' => $status,
                'note' => $note !== '' ? $note : null,
                'reviewer' => (int)$reviewer['id'],
                'id' => $requestId,
            ]);

            $title = $approve ? 'Volunteer shift approved' : 'Volunteer shift request declined';
            $body = $approve
                ? 'You are confirmed for ' . $request['title'] . '. It is now part of your volunteer commitments.'
                : 'Your request for ' . $request['title'] . ' was not approved.';
            if ($note !== '') $body .= ' Staff note: ' . $note;

            $notification = $db->prepare("INSERT INTO app_notifications (recipient_user_id, source_type, source_id, title, body, action_path) VALUES (:recipient, 'volunteer_shift_approval', :source_id, :title, :body, :action_path) ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body), action_path = VALUES(action_path), read_at = NULL, created_at = CURRENT_TIMESTAMP");
            $notification->execute([
                'recipient' => (int)$request['user_id'],
                'source_id' => $requestId,
                'title' => $title,
                'body' => $body,
                'action_path' => '/volunteer/shift?id=' . (int)$request['shift_id'],
            ]);

            self::audit($db, (int)$reviewer['id'], $approve ? 'volunteer.approval_approved' : 'volunteer.approval_declined', 'volunteer_shift_approval_request', $requestId, ($approve ? 'Approved ' : 'Declined ') . $request['volunteer_name'] . ' for ' . $request['title'] . '.', [
                'shift_id' => (int)$request['shift_id'],
                'volunteer_user_id' => (int)$request['user_id'],
                'decision_note' => $note !== '' ? $note : null,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The approval decision could not be saved.');
        }
    }

    private static function missingRequirements(PDO $db, int $userId, int $shiftId): array
    {
        $stmt = $db->prepare("SELECT vr.name FROM volunteer_shift_requirements vsr JOIN volunteer_requirements vr ON vr.id = vsr.requirement_id LEFT JOIN volunteer_credentials vc ON vc.requirement_id = vr.id AND vc.user_id = :user_id WHERE vsr.shift_id = :shift_id AND (vc.id IS NULL OR vc.status <> 'approved' OR (vc.expires_at IS NOT NULL AND vc.expires_at < NOW())) ORDER BY vr.id");
        $stmt->execute(['user_id' => $userId, 'shift_id' => $shiftId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function volunteerRows(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT vs.id AS shift_id, vs.title, vs.category, vs.starts_at, vs.ends_at, vs.location, vs.required_slots, COALESCE(cap.active_signups,0) AS active_signups, r.id AS request_id, r.status AS request_status, r.request_note, r.decision_note, r.requested_at, r.reviewed_at FROM volunteer_shifts vs LEFT JOIN (SELECT shift_id, COUNT(*) AS active_signups FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) cap ON cap.shift_id = vs.id LEFT JOIN volunteer_shift_approval_requests r ON r.shift_id = vs.id AND r.user_id = :user_id WHERE vs.approval_required = 1 ORDER BY vs.starts_at ASC");
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['missing'] = self::missingRequirements($db, $userId, (int)$row['shift_id']);
            $row['eligible_to_request'] = !$row['missing'] && (int)$row['active_signups'] < (int)$row['required_slots'];
            $row['open_slots'] = max((int)$row['required_slots'] - (int)$row['active_signups'], 0);
        }
        unset($row);
        return $rows;
    }

    private static function staffQueue(PDO $db): array
    {
        return $db->query("SELECT r.id, r.status, r.request_note, r.decision_note, r.requested_at, r.reviewed_at, vs.id AS shift_id, vs.title, vs.starts_at, vs.location, CONCAT(u.first_name, ' ', u.last_name) AS volunteer_name, u.initials, CONCAT(rv.first_name, ' ', rv.last_name) AS reviewer_name FROM volunteer_shift_approval_requests r JOIN volunteer_shifts vs ON vs.id = r.shift_id JOIN users u ON u.id = r.user_id LEFT JOIN users rv ON rv.id = r.reviewed_by_user_id ORDER BY FIELD(r.status,'pending','approved','declined','withdrawn'), r.requested_at ASC")->fetchAll();
    }

    private static function requestDetail(PDO $db, int $requestId): ?array
    {
        if ($requestId < 1) return null;
        $stmt = $db->prepare("SELECT r.*, vs.title, vs.category, vs.starts_at, vs.ends_at, vs.location, vs.required_slots, COALESCE(cap.active_signups,0) AS active_signups, CONCAT(u.first_name, ' ', u.last_name) AS volunteer_name, u.initials, u.display_role AS volunteer_role FROM volunteer_shift_approval_requests r JOIN volunteer_shifts vs ON vs.id = r.shift_id JOIN users u ON u.id = r.user_id LEFT JOIN (SELECT shift_id, COUNT(*) AS active_signups FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) cap ON cap.shift_id = vs.id WHERE r.id = :id LIMIT 1");
        $stmt->execute(['id' => $requestId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['missing'] = self::missingRequirements($db, (int)$row['user_id'], (int)$row['shift_id']);
        return $row;
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        return $row;
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

    private static function volunteerPage(PDO $db, string $basePath, array $user): never
    {
        $rows = self::volunteerRows($db, (int)$user['id']);
        $flash = self::takeFlash();
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $subnav = [
            ['label' => 'Readiness', 'href' => '/volunteer-readiness', 'active' => false],
            ['label' => 'Opportunities', 'href' => '/volunteer-shifts', 'active' => false],
            ['label' => 'Approval requests', 'href' => '/volunteer/approvals', 'active' => true],
        ];
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Volunteer approvals · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/volunteer-approval.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/volunteer/approvals', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Volunteer', 'Approval requests', $basePath, $subnav); ?><div class="approval-page">
        <?php if ($flash): ?><div class="approval-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <section class="approval-hero"><div><small>GATED VOLUNTEER ROLES</small><h2>Request access when a role needs a human decision.</h2><p>Your required credentials must already be current. A request does not hold a slot; capacity and eligibility are checked again when staff makes the decision.</p></div></section>
        <div class="approval-list"><?php if (!$rows): ?><section class="approval-empty"><b>No approval-gated shifts</b><p>There are no volunteer opportunities that currently require coordinator approval.</p></section><?php else: foreach ($rows as $row): ?><article class="approval-card"><div class="approval-date"><b><?= $esc(date('M', strtotime($row['starts_at']))) ?></b><span><?= $esc(date('j', strtotime($row['starts_at']))) ?></span></div><div class="approval-copy"><small><?= $esc(strtoupper($row['category'])) ?></small><h3><?= $esc($row['title']) ?></h3><p><?= $esc(date('g:i A', strtotime($row['starts_at']))) ?>–<?= $esc(date('g:i A', strtotime($row['ends_at']))) ?> · <?= $esc($row['location']) ?> · <?= (int)$row['open_slots'] ?> open</p><?php if ($row['missing']): ?><div class="approval-warning">Complete <?= $esc(implode(' + ', $row['missing'])) ?> before requesting this role.</div><?php elseif ($row['request_status']): ?><div class="approval-status <?= $esc($row['request_status']) ?>"><b><?= $esc(ucfirst($row['request_status'])) ?></b><?php if ($row['decision_note']): ?><span><?= $esc($row['decision_note']) ?></span><?php endif; ?></div><?php else: ?><div class="approval-ready">Requirements current · eligible to request</div><?php endif; ?></div><div class="approval-action"><?php if ($row['request_status'] === 'pending'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['volunteer_approval_csrf']) ?>"><input type="hidden" name="action" value="withdraw"><input type="hidden" name="request_id" value="<?= (int)$row['request_id'] ?>"><button type="submit" class="approval-link">Withdraw request</button></form><?php elseif ($row['eligible_to_request'] && $row['request_status'] !== 'approved'): ?><form method="post" class="approval-request-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['volunteer_approval_csrf']) ?>"><input type="hidden" name="action" value="request"><input type="hidden" name="shift_id" value="<?= (int)$row['shift_id'] ?>"><label>Optional note<textarea name="request_note" maxlength="500" placeholder="Anything the coordinator should know?"></textarea></label><button class="button" type="submit">Request approval</button></form><?php endif; ?></div></article><?php endforeach; endif; ?></div>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function staffPage(PDO $db, string $route, string $basePath, array $user, int $requestId): never
    {
        $queue = self::staffQueue($db);
        $selected = $route === '/admin/volunteer-approvals/review' ? self::requestDetail($db, $requestId) : null;
        $flash = self::takeFlash();
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $pendingCount = count(array_filter($queue, static fn(array $r): bool => $r['status'] === 'pending'));
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Volunteer operations · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/volunteer-approval.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/volunteer-approvals', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations', 'Volunteer approvals', $basePath); ?><div class="approval-page">
        <?php if ($flash): ?><div class="approval-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if ($route === '/admin/volunteer-approvals'): ?><section class="approval-admin-head"><div><small>VOLUNTEER OPERATIONS</small><h2>Approval queue</h2><p>Review gated roles without bypassing credential requirements or shift capacity.</p></div><strong><?= $pendingCount ?><span>pending</span></strong></section><div class="approval-queue"><?php if (!$queue): ?><section class="approval-empty"><b>No approval requests yet</b><p>Requests for gated volunteer roles will appear here.</p></section><?php else: foreach ($queue as $row): ?><a href="<?= $url('/admin/volunteer-approvals/review?id=' . (int)$row['id']) ?>" class="approval-queue-row"><i><?= $esc($row['initials']) ?></i><div><small><?= $esc(strtoupper($row['status'])) ?> · <?= $esc(date('M j · g:i A', strtotime($row['requested_at']))) ?></small><h3><?= $esc($row['volunteer_name']) ?></h3><p><?= $esc($row['title']) ?> · <?= $esc(date('M j · g:i A', strtotime($row['starts_at']))) ?></p></div><span>Review →</span></a><?php endforeach; endif; ?></div><?php else: ?>
        <?php if (!$selected): ?><section class="approval-empty"><b>Request not found</b><a class="button" href="<?= $url('/admin/volunteer-approvals') ?>">Back to queue</a></section><?php else: ?><section class="approval-review-head"><div><small><?= $esc(strtoupper($selected['status'])) ?></small><h2><?= $esc($selected['volunteer_name']) ?></h2><p><?= $esc($selected['volunteer_role']) ?> · requesting <?= $esc($selected['title']) ?></p></div><a href="<?= $url('/admin/volunteer-approvals') ?>">← Queue</a></section><div class="approval-review-grid"><section class="approval-panel"><small>REQUEST CONTEXT</small><h3><?= $esc($selected['title']) ?></h3><dl><div><dt>When</dt><dd><?= $esc(date('l, M j · g:i A', strtotime($selected['starts_at']))) ?></dd></div><div><dt>Location</dt><dd><?= $esc($selected['location']) ?></dd></div><div><dt>Capacity</dt><dd><?= (int)$selected['active_signups'] ?> confirmed of <?= (int)$selected['required_slots'] ?></dd></div><div><dt>Requested</dt><dd><?= $esc(date('M j · g:i A', strtotime($selected['requested_at']))) ?></dd></div></dl><?php if ($selected['request_note']): ?><blockquote><?= $esc($selected['request_note']) ?></blockquote><?php endif; ?></section><section class="approval-panel"><small>ELIGIBILITY RECHECK</small><?php if ($selected['missing']): ?><div class="approval-warning"><b>Approval blocked</b><p>Missing or expired: <?= $esc(implode(', ', $selected['missing'])) ?></p></div><?php else: ?><div class="approval-ready"><b>Credential requirements current</b><p>Capacity will be checked again inside the approval transaction.</p></div><?php endif; ?><?php if ($selected['status'] === 'pending'): ?><form method="post" class="approval-decision"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['volunteer_approval_csrf']) ?>"><input type="hidden" name="request_id" value="<?= (int)$selected['id'] ?>"><label>Decision note<textarea name="decision_note" maxlength="500" placeholder="Optional context for the volunteer"></textarea></label><div><button class="button" type="submit" name="action" value="approve"<?= $selected['missing'] ? ' disabled' : '' ?>>Approve & confirm shift</button><button class="approval-decline" type="submit" name="action" value="decline">Decline</button></div></form><?php else: ?><div class="approval-status <?= $esc($selected['status']) ?>"><b><?= $esc(ucfirst($selected['status'])) ?></b><?php if ($selected['decision_note']): ?><span><?= $esc($selected['decision_note']) ?></span><?php endif; ?></div><?php endif; ?></section></div><?php endif; ?><?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/volunteer-approval.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/volunteer-readiness', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations', 'Restricted', $basePath); ?><div class="approval-page"><section class="approval-empty"><b>Staff only</b><p>Your current role cannot review volunteer approval requests.</p><a class="button" href="<?= $url('/volunteer/approvals') ?>">My volunteer requests</a></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['volunteer_approval_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function takeFlash(): ?array
    {
        $flash = $_SESSION['volunteer_approval_flash'] ?? null;
        unset($_SESSION['volunteer_approval_flash']);
        return $flash;
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
