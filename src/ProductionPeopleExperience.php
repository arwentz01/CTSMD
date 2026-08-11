<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/IdentityRolePolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/TheatreHistoryService.php';

final class ProductionPeopleExperience
{
    private const ROUTES = ['/production/people'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
        if (!AccessPolicy::canManageProduction($user)) {
            self::forbidden($basePath, $user);
        }

        $_SESSION['production_people_csrf'] ??= bin2hex(random_bytes(24));
        $production = ProductionContext::selected($db, $user);
        if (!$production) {
            self::page($basePath, $user, null, [], [], []);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, (int)$production['id'], $basePath);
        }

        $members = self::members($db, (int)$production['id']);
        $candidates = self::candidates($db, (int)$production['id']);
        $coverage = self::guardianCoverage($db, (int)$production['id']);
        self::page($basePath, $user, $production, $members, $candidates, $coverage);
    }

    private static function handlePost(PDO $db, array $user, int $productionId, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['production_people_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/production/people');
        }

        $selected = ProductionContext::selected($db, $user);
        if (!$selected || (int)$selected['id'] !== $productionId) {
            self::flash('error', 'The working production changed before this roster action was saved. Review the production selector and try again.');
            self::redirect($basePath . '/production/people');
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add') {
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
                $audienceType = (string)($_POST['audience_type'] ?? '');
                $participationRole = trim((string)($_POST['participation_role'] ?? ''));
                self::addMember($db, $user, $productionId, (int)$userId, $audienceType, $participationRole);
                self::flash('success', 'Production membership updated.');
            } elseif ($action === 'remove') {
                $membershipId = filter_input(INPUT_POST, 'membership_id', FILTER_VALIDATE_INT) ?: 0;
                self::deactivateMember($db, $user, $productionId, (int)$membershipId);
                self::flash('success', 'Production membership removed.');
            } else {
                throw new RuntimeException('Choose a valid membership action.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/production/people');
    }

    private static function addMember(PDO $db, array $actor, int $productionId, int $userId, string $audienceType, string $participationRole): void
    {
        if ($userId < 1 || !in_array($audienceType, ['student', 'guardian', 'staff'], true)) {
            throw new RuntimeException('Choose a valid person and production role.');
        }
        if ($participationRole === '' || mb_strlen($participationRole) > 120) {
            throw new RuntimeException('Enter a participation role no longer than 120 characters.');
        }

        $db->beginTransaction();
        try {
            $personStmt = $db->prepare("SELECT id, active, account_status FROM users WHERE id = :id FOR UPDATE");
            $personStmt->execute(['id' => $userId]);
            $person = $personStmt->fetch();
            if (!$person || !(bool)$person['active'] || $person['account_status'] === 'disabled') {
                throw new RuntimeException('That person is not available for an active production roster.');
            }
            IdentityRolePolicy::assertProductionAudience($db, $userId, $audienceType);

            if ($audienceType === 'guardian') {
                $guardianCheck = $db->prepare("SELECT COUNT(*) FROM family_relationships fr JOIN production_memberships student_pm ON student_pm.user_id = fr.student_user_id AND student_pm.production_id = :production_id AND student_pm.audience_type = 'student' AND student_pm.status = 'active' JOIN users student ON student.id=student_pm.user_id AND student.active=1 AND student.account_status<>'disabled' WHERE fr.guardian_user_id = :guardian_id AND fr.status = 'active'");
                $guardianCheck->execute(['production_id' => $productionId, 'guardian_id' => $userId]);
                if ((int)$guardianCheck->fetchColumn() < 1) {
                    throw new RuntimeException('A guardian can only be added when they have an active family relationship to a live student already in this production.');
                }
            }

            self::upsertMembership($db, $productionId, $userId, $audienceType, $participationRole);
            $addedGuardians = [];

            if ($audienceType === 'student') {
                $guardianStmt = $db->prepare("SELECT fr.guardian_user_id, fr.relationship_type, CONCAT(u.first_name, ' ', u.last_name) AS name FROM family_relationships fr JOIN users u ON u.id = fr.guardian_user_id AND u.active = 1 AND u.account_status<>'disabled' WHERE fr.student_user_id = :student_id AND fr.status = 'active' ORDER BY fr.is_primary DESC, fr.id ASC");
                $guardianStmt->execute(['student_id' => $userId]);
                $guardians = $guardianStmt->fetchAll();
                if (!$guardians) {
                    throw new RuntimeException('This student has no available active guardian relationship. Add or restore a guardian in People before adding them to a production.');
                }
                foreach ($guardians as $guardian) {
                    $role = ucfirst((string)$guardian['relationship_type']);
                    self::upsertMembership($db, $productionId, (int)$guardian['guardian_user_id'], 'guardian', $role);
                    $addedGuardians[] = (int)$guardian['guardian_user_id'];
                }
                TheatreHistoryService::syncStudentCredit($db, $productionId, $userId, (int)$actor['id']);
            }

            self::audit($db, (int)$actor['id'], 'production.membership_added', 'production', $productionId, 'Added or reactivated a production membership.', [
                'user_id' => $userId,
                'audience_type' => $audienceType,
                'participation_role' => $participationRole,
                'auto_guardian_user_ids' => $addedGuardians,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The production membership could not be saved.');
        }
    }

    private static function upsertMembership(PDO $db, int $productionId, int $userId, string $audienceType, string $participationRole): void
    {
        $stmt = $db->prepare("INSERT INTO production_memberships (production_id, user_id, audience_type, participation_role, status) VALUES (:production_id, :user_id, :audience_type, :participation_role, 'active') ON DUPLICATE KEY UPDATE participation_role = VALUES(participation_role), status = 'active', updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([
            'production_id' => $productionId,
            'user_id' => $userId,
            'audience_type' => $audienceType,
            'participation_role' => $participationRole,
        ]);
    }

    private static function deactivateMember(PDO $db, array $actor, int $productionId, int $membershipId): void
    {
        if ($membershipId < 1) {
            throw new RuntimeException('That membership could not be found.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT pm.id, pm.user_id, pm.audience_type, pm.participation_role, pm.status, CONCAT(u.first_name, ' ', u.last_name) AS name FROM production_memberships pm JOIN users u ON u.id = pm.user_id WHERE pm.id = :id AND pm.production_id = :production_id FOR UPDATE");
            $stmt->execute(['id' => $membershipId, 'production_id' => $productionId]);
            $membership = $stmt->fetch();
            if (!$membership || $membership['status'] !== 'active') {
                throw new RuntimeException('That active production membership could not be found.');
            }

            if ($membership['audience_type'] === 'guardian') {
                $studentStmt = $db->prepare("SELECT student_pm.user_id AS student_id, CONCAT(student.first_name, ' ', student.last_name) AS student_name FROM family_relationships fr JOIN production_memberships student_pm ON student_pm.user_id = fr.student_user_id AND student_pm.production_id = :production_id AND student_pm.audience_type = 'student' AND student_pm.status = 'active' JOIN users student ON student.id = student_pm.user_id AND student.active=1 AND student.account_status<>'disabled' WHERE fr.guardian_user_id = :guardian_id AND fr.status = 'active'");
                $studentStmt->execute(['production_id' => $productionId, 'guardian_id' => (int)$membership['user_id']]);
                foreach ($studentStmt->fetchAll() as $student) {
                    $otherStmt = $db->prepare("SELECT COUNT(DISTINCT guardian_pm.user_id) FROM family_relationships fr JOIN production_memberships guardian_pm ON guardian_pm.user_id = fr.guardian_user_id AND guardian_pm.production_id = :production_id AND guardian_pm.audience_type = 'guardian' AND guardian_pm.status = 'active' JOIN users guardian ON guardian.id=guardian_pm.user_id AND guardian.active=1 AND guardian.account_status<>'disabled' WHERE fr.student_user_id = :student_id AND fr.status = 'active' AND guardian_pm.user_id <> :guardian_id");
                    $otherStmt->execute([
                        'production_id' => $productionId,
                        'student_id' => (int)$student['student_id'],
                        'guardian_id' => (int)$membership['user_id'],
                    ]);
                    if ((int)$otherStmt->fetchColumn() < 1) {
                        throw new RuntimeException('Cannot remove this guardian while ' . $student['student_name'] . ' remains in the production without another available active guardian member.');
                    }
                }
            }

            $update = $db->prepare("UPDATE production_memberships SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute(['id' => $membershipId]);
            if ($membership['audience_type'] === 'student') {
                TheatreHistoryService::closeStudentCredit($db, $productionId, (int)$membership['user_id']);
            }

            self::audit($db, (int)$actor['id'], 'production.membership_removed', 'production', $productionId, 'Deactivated a production membership.', [
                'membership_id' => $membershipId,
                'user_id' => (int)$membership['user_id'],
                'audience_type' => $membership['audience_type'],
                'participation_role' => $membership['participation_role'],
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The production membership could not be removed.');
        }
    }

    private static function members(PDO $db, int $productionId): array
    {
        $stmt = $db->prepare("SELECT pm.id, pm.user_id, pm.audience_type, pm.participation_role, pm.status, CONCAT(u.first_name, ' ', u.last_name) AS name, u.initials, u.display_role AS account_role, u.account_status FROM production_memberships pm JOIN users u ON u.id = pm.user_id WHERE pm.production_id = :production_id ORDER BY FIELD(pm.status,'active','inactive'), u.account_status='disabled', FIELD(pm.audience_type,'student','guardian','staff'), u.last_name, u.first_name");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function candidates(PDO $db, int $productionId): array
    {
        $stmt = $db->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.display_role AS role, u.initials, EXISTS(SELECT 1 FROM production_memberships pm WHERE pm.production_id = :production_id AND pm.user_id = u.id AND pm.status = 'active') AS already_active FROM users u WHERE u.active = 1 AND u.account_status<>'disabled' ORDER BY u.last_name, u.first_name");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function guardianCoverage(PDO $db, int $productionId): array
    {
        $stmt = $db->prepare("SELECT student_pm.user_id AS student_id, CONCAT(student.first_name, ' ', student.last_name) AS student_name, COUNT(DISTINCT CASE WHEN guardian.id IS NOT NULL THEN guardian_pm.user_id END) AS guardian_count FROM production_memberships student_pm JOIN users student ON student.id = student_pm.user_id AND student.active=1 AND student.account_status<>'disabled' LEFT JOIN family_relationships fr ON fr.student_user_id = student_pm.user_id AND fr.status = 'active' LEFT JOIN production_memberships guardian_pm ON guardian_pm.user_id = fr.guardian_user_id AND guardian_pm.production_id = student_pm.production_id AND guardian_pm.audience_type = 'guardian' AND guardian_pm.status = 'active' LEFT JOIN users guardian ON guardian.id=guardian_pm.user_id AND guardian.active=1 AND guardian.account_status<>'disabled' WHERE student_pm.production_id = :production_id AND student_pm.audience_type = 'student' AND student_pm.status = 'active' GROUP BY student_pm.user_id, student.first_name, student.last_name ORDER BY student.last_name, student.first_name");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
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

    private static function page(string $basePath, array $user, ?array $production, array $members, array $candidates, array $coverage): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['production_people_flash'] ?? $_SESSION['production_context_flash'] ?? null;
        unset($_SESSION['production_people_flash'], $_SESSION['production_context_flash']);
        $active = array_values(array_filter($members, static fn(array $m): bool => $m['status'] === 'active' && $m['account_status'] !== 'disabled'));
        $disabledActive = array_values(array_filter($members, static fn(array $m): bool => $m['status'] === 'active' && $m['account_status'] === 'disabled'));
        $students = array_values(array_filter($active, static fn(array $m): bool => $m['audience_type'] === 'student'));
        $guardians = array_values(array_filter($active, static fn(array $m): bool => $m['audience_type'] === 'guardian'));
        $staff = array_values(array_filter($active, static fn(array $m): bool => $m['audience_type'] === 'staff'));
        $subnav = [
            ['label' => 'Overview', 'href' => '/production', 'active' => false],
            ['label' => 'People', 'href' => '/production/people', 'active' => true],
            ['label' => 'Schedule', 'href' => '/schedule', 'active' => false],
            ['label' => 'Updates', 'href' => '/production/notices', 'active' => false],
            ['label' => 'Resources', 'href' => '/resources', 'active' => false],
            ['label' => 'Playbill', 'href' => '/playbills', 'active' => false],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>Production people · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-people.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production/people', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production', 'People & cast', $basePath, $subnav); ?><div class="production-people-page">
        <?php if ($flash): ?><div class="production-people-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if (!$production): ?><section class="production-people-empty"><h2>No active production selected</h2><p>Activate a production or switch the working production before managing its membership.</p></section><?php else: ?>
        <section class="production-people-hero"><div><small><?= $esc(strtoupper((string)$production['season'])) ?> · WORKING PRODUCTION</small><h2><?= $esc($production['title']) ?></h2><p>Manage only the people who belong to this selected production. Student additions automatically carry their available active guardian relationships into this production audience.</p></div><div class="production-people-metrics"><span><b><?= count($students) ?></b><small>Students</small></span><span><b><?= count($guardians) ?></b><small>Guardians</small></span><span><b><?= count($staff) ?></b><small>Staff</small></span></div></section>

        <div class="production-people-layout"><section class="production-people-panel"><header><div><small>ACTIVE ROSTER</small><h3>Production membership</h3></div><span><?= count($active) ?> live</span></header>
        <?php foreach (['student' => 'Students / Cast', 'guardian' => 'Guardians', 'staff' => 'Production Staff'] as $type => $label): ?><div class="production-people-group"><h4><?= $esc($label) ?></h4><?php $group = array_values(array_filter($active, static fn(array $m): bool => $m['audience_type'] === $type)); if (!$group): ?><p class="production-people-muted">No active <?= $esc($label) ?>.</p><?php else: foreach ($group as $member): ?><article class="production-member"><i><?= $esc($member['initials']) ?></i><div><b><?= $esc($member['name']) ?></b><span><?= $esc((string)$member['participation_role']) ?></span><small><?= $esc($member['account_role']) ?></small></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_people_csrf']) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="membership_id" value="<?= (int)$member['id'] ?>"><button type="submit">Remove</button></form></article><?php endforeach; endif; ?></div><?php endforeach; ?>
        <?php if($disabledActive):?><div class="production-people-group"><h4>Disabled accounts · cleanup</h4><p class="production-people-muted">These memberships are retained for history but do not count in the live roster, schedule audiences, or guardian coverage. Remove the production membership when appropriate.</p><?php foreach($disabledActive as $member):?><article class="production-member"><i><?= $esc($member['initials']) ?></i><div><b><?= $esc($member['name']) ?></b><span><?= $esc((string)$member['participation_role']) ?></span><small>Account disabled · <?= $esc(ucfirst((string)$member['audience_type'])) ?></small></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_people_csrf']) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="membership_id" value="<?= (int)$member['id'] ?>"><button type="submit">Remove</button></form></article><?php endforeach;?></div><?php endif;?>
        </section>

        <aside class="production-people-panel add"><header><div><small>ADD TO <?= $esc(strtoupper($production['title'])) ?></small><h3>Assign a person</h3></div></header><form method="post" class="production-people-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_people_csrf']) ?>"><input type="hidden" name="action" value="add"><label>Person<select name="user_id" required><option value="">Choose a person</option><?php foreach ($candidates as $candidate): ?><option value="<?= (int)$candidate['id'] ?>"><?= $esc($candidate['name']) ?> · <?= $esc($candidate['role']) ?><?= (bool)$candidate['already_active'] ? ' · already active' : '' ?></option><?php endforeach; ?></select></label><label>Production audience<select name="audience_type" required><option value="student">Student / cast</option><option value="guardian">Guardian</option><option value="staff">Staff</option></select></label><label>Participation role<input name="participation_role" maxlength="120" required placeholder="e.g. Matilda, Parent / Guardian, Director"></label><button class="button full" type="submit">Add to <?= $esc($production['title']) ?></button></form><div class="production-people-rule"><b>Guardian safety is automatic.</b><p>Students cannot be added without an available active guardian relationship. Their active guardians are added to this production audience automatically.</p></div></aside></div>

        <section class="production-people-panel coverage"><header><div><small>SAFEGUARDING CHECK</small><h3>Student guardian coverage</h3></div></header><?php if (!$coverage): ?><p class="production-people-muted">No live students are assigned.</p><?php else: foreach ($coverage as $row): ?><article><span><b><?= $esc($row['student_name']) ?></b><small>Active production student</small></span><em class="<?= (int)$row['guardian_count'] > 0 ? 'good' : 'danger' ?>"><?= (int)$row['guardian_count'] ?> guardian<?= (int)$row['guardian_count'] === 1 ? '' : 's' ?></em></article><?php endforeach; endif; ?></section>
        <?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production', 'Restricted', $basePath); ?><div class="production-people-page"><section class="production-people-empty"><h2>Staff only</h2><p>Your current role cannot manage production membership.</p><a class="button" href="<?= $url('/production') ?>">Back to production</a></section></div></main></div></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['production_people_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
