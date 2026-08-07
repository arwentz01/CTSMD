<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class VolunteerShiftManagementExperience
{
    private const ROUTES = ['/admin/volunteer-shifts', '/admin/volunteer-shifts/new', '/admin/volunteer-shifts/view'];

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
        if (!AccessPolicy::isStaff($user)) {
            self::forbidden($basePath, $user);
        }

        $_SESSION['volunteer_shift_admin_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $route, $basePath, $user);
        }

        $production = self::currentProduction($db);
        $requirements = self::requirements($db);
        $shift = null;
        if ($route === '/admin/volunteer-shifts/view') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $shift = self::shift($db, (int)$id);
        }

        self::page($db, $route, $basePath, $user, $production, $requirements, $shift);
    }

    private static function handlePost(PDO $db, string $route, string $basePath, array $user): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['volunteer_shift_admin_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/admin/volunteer-shifts');
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $id = self::saveShift($db, $user, 0, $_POST);
                self::flash('success', 'Volunteer shift created.');
                self::redirect($basePath . '/admin/volunteer-shifts/view?id=' . $id);
            }
            if ($action === 'update') {
                $id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT) ?: 0;
                self::saveShift($db, $user, (int)$id, $_POST);
                self::flash('success', 'Volunteer shift updated.');
                self::redirect($basePath . '/admin/volunteer-shifts/view?id=' . (int)$id);
            }
            if ($action === 'roster_status') {
                $signupId = filter_input(INPUT_POST, 'signup_id', FILTER_VALIDATE_INT) ?: 0;
                $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT) ?: 0;
                self::updateRosterStatus($db, $user, (int)$signupId, (string)($_POST['status'] ?? ''));
                self::flash('success', 'Volunteer roster status updated.');
                self::redirect($basePath . '/admin/volunteer-shifts/view?id=' . (int)$shiftId);
            }
            throw new RuntimeException('Choose a valid volunteer operation.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT) ?: 0;
            $fallback = $route === '/admin/volunteer-shifts/new' ? '/admin/volunteer-shifts/new' : ($shiftId ? '/admin/volunteer-shifts/view?id=' . (int)$shiftId : '/admin/volunteer-shifts');
            self::redirect($basePath . $fallback);
        }
    }

    private static function saveShift(PDO $db, array $actor, int $shiftId, array $input): int
    {
        $production = self::currentProduction($db);
        if (!$production) {
            throw new RuntimeException('Make a production current before creating or editing volunteer shifts.');
        }

        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $location = trim((string)($input['location'] ?? ''));
        $startsAt = self::parseLocalDateTime((string)($input['starts_at'] ?? ''), 'Start time');
        $endsAt = self::parseLocalDateTime((string)($input['ends_at'] ?? ''), 'End time');
        $requiredSlots = filter_var($input['required_slots'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]) ?: 0;
        $approvalRequired = isset($input['approval_required']) ? 1 : 0;
        $requirements = array_values(array_unique(array_filter(array_map('intval', (array)($input['requirement_ids'] ?? [])), static fn(int $id): bool => $id > 0)));

        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a shift title no longer than 190 characters.');
        if ($category === '' || mb_strlen($category) > 100) throw new RuntimeException('Enter a category no longer than 100 characters.');
        if ($location === '' || mb_strlen($location) > 190) throw new RuntimeException('Enter a location no longer than 190 characters.');
        if ($endsAt <= $startsAt) throw new RuntimeException('The shift end time must be after the start time.');
        if ($requiredSlots < 1) throw new RuntimeException('Enter at least one required volunteer slot.');

        if ($requirements) {
            $placeholders = implode(',', array_fill(0, count($requirements), '?'));
            $check = $db->prepare("SELECT id FROM volunteer_requirements WHERE id IN ($placeholders)");
            $check->execute($requirements);
            if (count($check->fetchAll(PDO::FETCH_COLUMN)) !== count($requirements)) {
                throw new RuntimeException('One or more selected requirements no longer exist.');
            }
        }

        $db->beginTransaction();
        try {
            $before = null;
            if ($shiftId > 0) {
                $stmt = $db->prepare('SELECT id, production_id, title, category, starts_at, ends_at, location, required_slots, approval_required FROM volunteer_shifts WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $shiftId]);
                $before = $stmt->fetch();
                if (!$before) throw new RuntimeException('That volunteer shift no longer exists.');
                if ((int)$before['production_id'] !== (int)$production['id']) throw new RuntimeException('Only shifts for the current production can be edited here.');

                $confirmed = $db->prepare("SELECT COUNT(*) FROM volunteer_shift_signups WHERE shift_id = :shift_id AND status IN ('signed_up','checked_in','completed')");
                $confirmed->execute(['shift_id' => $shiftId]);
                $confirmedCount = (int)$confirmed->fetchColumn();
                if ($requiredSlots < $confirmedCount) {
                    throw new RuntimeException('Capacity cannot be lower than the ' . $confirmedCount . ' currently confirmed volunteers.');
                }

                $update = $db->prepare('UPDATE volunteer_shifts SET title = :title, category = :category, starts_at = :starts_at, ends_at = :ends_at, location = :location, required_slots = :required_slots, approval_required = :approval_required WHERE id = :id');
                $update->execute([
                    'title' => $title,
                    'category' => $category,
                    'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                    'location' => $location,
                    'required_slots' => $requiredSlots,
                    'approval_required' => $approvalRequired,
                    'id' => $shiftId,
                ]);
            } else {
                $insert = $db->prepare('INSERT INTO volunteer_shifts (production_id, title, category, starts_at, ends_at, location, required_slots, approval_required) VALUES (:production_id, :title, :category, :starts_at, :ends_at, :location, :required_slots, :approval_required)');
                $insert->execute([
                    'production_id' => (int)$production['id'],
                    'title' => $title,
                    'category' => $category,
                    'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                    'location' => $location,
                    'required_slots' => $requiredSlots,
                    'approval_required' => $approvalRequired,
                ]);
                $shiftId = (int)$db->lastInsertId();
            }

            $db->prepare('DELETE FROM volunteer_shift_requirements WHERE shift_id = :shift_id')->execute(['shift_id' => $shiftId]);
            if ($requirements) {
                $link = $db->prepare('INSERT INTO volunteer_shift_requirements (shift_id, requirement_id) VALUES (:shift_id, :requirement_id)');
                foreach ($requirements as $requirementId) {
                    $link->execute(['shift_id' => $shiftId, 'requirement_id' => $requirementId]);
                }
            }

            self::audit($db, (int)$actor['id'], $before ? 'volunteer.shift_updated' : 'volunteer.shift_created', 'volunteer_shift', $shiftId, $before ? 'Updated volunteer shift configuration.' : 'Created volunteer shift.', [
                'production_id' => (int)$production['id'],
                'before' => $before ?: null,
                'after' => [
                    'title' => $title,
                    'category' => $category,
                    'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                    'location' => $location,
                    'required_slots' => $requiredSlots,
                    'approval_required' => (bool)$approvalRequired,
                    'requirement_ids' => $requirements,
                ],
            ]);

            $db->commit();
            return $shiftId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The volunteer shift could not be saved.');
        }
    }

    private static function updateRosterStatus(PDO $db, array $actor, int $signupId, string $status): void
    {
        $allowed = ['signed_up','checked_in','completed','no_show','cancelled'];
        if ($signupId < 1 || !in_array($status, $allowed, true)) {
            throw new RuntimeException('Choose a valid roster status.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT s.id, s.shift_id, s.user_id, s.status, vs.title, CONCAT(u.first_name, ' ', u.last_name) AS volunteer_name FROM volunteer_shift_signups s JOIN volunteer_shifts vs ON vs.id = s.shift_id JOIN users u ON u.id = s.user_id WHERE s.id = :id FOR UPDATE");
            $stmt->execute(['id' => $signupId]);
            $signup = $stmt->fetch();
            if (!$signup) throw new RuntimeException('That roster entry no longer exists.');
            if ($signup['status'] === $status) {
                $db->commit();
                return;
            }

            $update = $db->prepare('UPDATE volunteer_shift_signups SET status = :status WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $signupId]);

            self::audit($db, (int)$actor['id'], 'volunteer.roster_status_changed', 'volunteer_shift_signup', $signupId, 'Updated volunteer shift roster status.', [
                'shift_id' => (int)$signup['shift_id'],
                'shift_title' => $signup['title'],
                'volunteer_user_id' => (int)$signup['user_id'],
                'volunteer_name' => $signup['volunteer_name'],
                'before' => $signup['status'],
                'after' => $status,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The roster status could not be updated.');
        }
    }

    private static function currentProduction(PDO $db): ?array
    {
        $row = $db->query("SELECT id, title, season, status FROM productions WHERE status = 'current' ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: null;
    }

    private static function requirements(PDO $db): array
    {
        return $db->query('SELECT id, name, category, expires FROM volunteer_requirements ORDER BY name')->fetchAll();
    }

    private static function shifts(PDO $db): array
    {
        $production = self::currentProduction($db);
        if (!$production) return [];
        $stmt = $db->prepare("SELECT vs.id, vs.title, vs.category, vs.starts_at, vs.ends_at, vs.location, vs.required_slots, vs.approval_required,
            COALESCE(cap.confirmed,0) AS confirmed,
            COALESCE(req.requirement_count,0) AS requirement_count,
            COALESCE(pend.pending_requests,0) AS pending_requests
            FROM volunteer_shifts vs
            LEFT JOIN (SELECT shift_id, COUNT(*) AS confirmed FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) cap ON cap.shift_id = vs.id
            LEFT JOIN (SELECT shift_id, COUNT(*) AS requirement_count FROM volunteer_shift_requirements GROUP BY shift_id) req ON req.shift_id = vs.id
            LEFT JOIN (SELECT shift_id, COUNT(*) AS pending_requests FROM volunteer_shift_approval_requests WHERE status = 'pending' GROUP BY shift_id) pend ON pend.shift_id = vs.id
            WHERE vs.production_id = :production_id ORDER BY vs.starts_at ASC, vs.id ASC");
        $stmt->execute(['production_id' => (int)$production['id']]);
        return $stmt->fetchAll();
    }

    private static function shift(PDO $db, int $shiftId): ?array
    {
        if ($shiftId < 1) return null;
        $stmt = $db->prepare('SELECT vs.*, p.title AS production_title FROM volunteer_shifts vs LEFT JOIN productions p ON p.id = vs.production_id WHERE vs.id = :id LIMIT 1');
        $stmt->execute(['id' => $shiftId]);
        $shift = $stmt->fetch();
        if (!$shift) return null;

        $req = $db->prepare('SELECT vr.id, vr.name, vr.category FROM volunteer_shift_requirements vsr JOIN volunteer_requirements vr ON vr.id = vsr.requirement_id WHERE vsr.shift_id = :shift_id ORDER BY vr.name');
        $req->execute(['shift_id' => $shiftId]);
        $shift['requirements'] = $req->fetchAll();
        $shift['requirement_ids'] = array_map(static fn(array $row): int => (int)$row['id'], $shift['requirements']);

        $roster = $db->prepare("SELECT s.id AS signup_id, s.user_id, s.status, s.created_at, CONCAT(u.first_name, ' ', u.last_name) AS name, u.display_role,
            (SELECT COUNT(*) FROM volunteer_shift_approval_requests r WHERE r.shift_id = s.shift_id AND r.user_id = s.user_id AND r.status = 'approved') AS approved_request
            FROM volunteer_shift_signups s JOIN users u ON u.id = s.user_id WHERE s.shift_id = :shift_id ORDER BY FIELD(s.status,'checked_in','signed_up','completed','no_show','cancelled','waitlisted'), u.last_name, u.first_name");
        $roster->execute(['shift_id' => $shiftId]);
        $shift['roster'] = $roster->fetchAll();

        $pending = $db->prepare("SELECT r.id, r.user_id, r.request_note, r.requested_at, CONCAT(u.first_name, ' ', u.last_name) AS name FROM volunteer_shift_approval_requests r JOIN users u ON u.id = r.user_id WHERE r.shift_id = :shift_id AND r.status = 'pending' ORDER BY r.requested_at ASC");
        $pending->execute(['shift_id' => $shiftId]);
        $shift['pending_requests'] = $pending->fetchAll();

        return $shift;
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

    private static function parseLocalDateTime(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', trim($value));
        if (!$date) throw new RuntimeException($label . ' is required.');
        return $date;
    }

    private static function page(PDO $db, string $route, string $basePath, array $user, ?array $production, array $requirements, ?array $shift): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['volunteer_shift_admin_flash'] ?? null;
        unset($_SESSION['volunteer_shift_admin_flash']);
        $shifts = self::shifts($db);
        $defaultStart = (new DateTimeImmutable('next saturday 10:00'))->format('Y-m-d\\TH:i');
        $defaultEnd = (new DateTimeImmutable('next saturday 14:00'))->format('Y-m-d\\TH:i');
        $editing = $route === '/admin/volunteer-shifts/view' && $shift;
        $selectedRequirements = $editing ? $shift['requirement_ids'] : [];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>Volunteer Operations · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/volunteer-shift-management.css') ?>"></head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations', $editing ? $shift['title'] : ($route === '/admin/volunteer-shifts/new' ? 'New volunteer shift' : 'Volunteer Operations'), $basePath, [
['label'=>'Shifts','href'=>'/admin/volunteer-shifts','active'=>$route === '/admin/volunteer-shifts'],
['label'=>'Approval queue','href'=>'/admin/volunteer-approvals','active'=>false],
]); ?><div class="vsm-page">
<?php if ($flash): ?><div class="vsm-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
<?php if (!$production): ?><section class="vsm-empty"><small>NO CURRENT PRODUCTION</small><h2>Volunteer shifts need a production context.</h2><p>Make a production current before creating staffing opportunities.</p><a class="button" href="<?= $url('/admin/productions') ?>">Manage productions</a></section>
<?php elseif ($route === '/admin/volunteer-shifts'): ?>
<section class="vsm-hero"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Staff the work behind the show.</h2><p>Create opportunities, define clearance requirements, see coverage, and move confirmed volunteers through check-in and completion.</p></div><a class="button" href="<?= $url('/admin/volunteer-shifts/new') ?>">Create shift</a></section>
<div class="vsm-list"><?php if (!$shifts): ?><div class="vsm-empty"><b>No volunteer shifts yet.</b><p>Create the first opportunity for this production.</p></div><?php else: foreach ($shifts as $row): $open=max((int)$row['required_slots']-(int)$row['confirmed'],0); ?><a href="<?= $url('/admin/volunteer-shifts/view?id='.(int)$row['id']) ?>" class="vsm-row"><div class="vsm-date"><b><?= $esc(date('M',strtotime($row['starts_at']))) ?></b><span><?= $esc(date('j',strtotime($row['starts_at']))) ?></span></div><div><small><?= $esc(strtoupper($row['category'])) ?><?= (bool)$row['approval_required'] ? ' · APPROVAL REQUIRED' : '' ?></small><h3><?= $esc($row['title']) ?></h3><p><?= $esc(date('g:i A',strtotime($row['starts_at']))) ?>–<?= $esc(date('g:i A',strtotime($row['ends_at']))) ?> · <?= $esc($row['location']) ?></p><span><?= (int)$row['requirement_count'] ?> requirement<?= (int)$row['requirement_count']===1?'':'s' ?> · <?= (int)$row['pending_requests'] ?> pending approval<?= (int)$row['pending_requests']===1?'':'s' ?></span></div><div class="vsm-cover"><b><?= (int)$row['confirmed'] ?>/<?= (int)$row['required_slots'] ?></b><span><?= $open ?> open</span><em>Manage →</em></div></a><?php endforeach; endif; ?></div>
<?php else: ?>
<section class="vsm-head"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2><?= $editing ? $esc($shift['title']) : 'Create volunteer shift' ?></h2><p><?= $editing ? 'Edit the opportunity and manage its confirmed roster.' : 'Define the opportunity, capacity, approval gate, and requirements.' ?></p></div><a href="<?= $url('/admin/volunteer-shifts') ?>">← All shifts</a></section>
<div class="vsm-layout"><form class="vsm-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['volunteer_shift_admin_csrf']) ?>"><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="shift_id" value="<?= (int)$shift['id'] ?>"><?php endif; ?><label>Shift title<input name="title" maxlength="190" required value="<?= $editing ? $esc($shift['title']) : '' ?>" placeholder="Front of House"></label><div class="vsm-pair"><label>Category<input name="category" maxlength="100" required value="<?= $editing ? $esc($shift['category']) : '' ?>" placeholder="front_of_house"></label><label>Location<input name="location" maxlength="190" required value="<?= $editing ? $esc($shift['location']) : '' ?>" placeholder="Lobby"></label></div><div class="vsm-pair"><label>Start<input type="datetime-local" name="starts_at" required value="<?= $editing ? $esc(date('Y-m-d\\TH:i',strtotime($shift['starts_at']))) : $esc($defaultStart) ?>"></label><label>End<input type="datetime-local" name="ends_at" required value="<?= $editing ? $esc(date('Y-m-d\\TH:i',strtotime($shift['ends_at']))) : $esc($defaultEnd) ?>"></label></div><label>Required volunteers<input type="number" name="required_slots" min="1" max="500" required value="<?= $editing ? (int)$shift['required_slots'] : 1 ?>"></label><label class="vsm-check"><input type="checkbox" name="approval_required" value="1"<?= $editing && (bool)$shift['approval_required'] ? ' checked' : '' ?>><span><b>Coordinator approval required</b><small>Eligible volunteers request the shift instead of reserving a slot immediately.</small></span></label><fieldset><legend>Eligibility requirements</legend><?php if (!$requirements): ?><p>No volunteer requirements exist yet.</p><?php else: foreach ($requirements as $requirement): ?><label class="vsm-check"><input type="checkbox" name="requirement_ids[]" value="<?= (int)$requirement['id'] ?>"<?= in_array((int)$requirement['id'],$selectedRequirements,true)?' checked':'' ?>><span><b><?= $esc($requirement['name']) ?></b><small><?= $esc(ucfirst(str_replace('_',' ',$requirement['category']))) ?><?= (bool)$requirement['expires'] ? ' · expiring credential' : '' ?></small></span></label><?php endforeach; endif; ?></fieldset><footer><a href="<?= $url('/admin/volunteer-shifts') ?>">Cancel</a><button class="button" type="submit"><?= $editing ? 'Save shift' : 'Create shift' ?></button></footer></form>
<aside class="vsm-side"><?php if (!$editing): ?><small>STAFFING MODEL</small><h3>Eligibility before signup.</h3><p>The same requirements selected here are checked when a volunteer signs up or requests approval.</p><div><b>Approval is optional.</b><span>Use it for roles that need an extra human decision even after credentials are current.</span></div><?php else: $confirmed=count(array_filter($shift['roster'],static fn(array $r):bool=>in_array($r['status'],['signed_up','checked_in','completed'],true))); ?><small>LIVE COVERAGE</small><b class="vsm-big"><?= $confirmed ?>/<?= (int)$shift['required_slots'] ?></b><h3>confirmed volunteers</h3><p><?= count($shift['pending_requests']) ?> approval request<?= count($shift['pending_requests'])===1?'':'s' ?> waiting.</p><?php if ($shift['pending_requests']): ?><a class="button secondary full" href="<?= $url('/admin/volunteer-approvals') ?>">Review approvals</a><?php endif; ?><div class="vsm-reqs"><b>Requirements</b><?php if (!$shift['requirements']): ?><span>None</span><?php else: foreach ($shift['requirements'] as $requirement): ?><span><?= $esc($requirement['name']) ?></span><?php endforeach; endif; ?></div><?php endif; ?></aside></div>
<?php if ($editing): ?><section class="vsm-roster"><header><div><small>CONFIRMED ROSTER</small><h3>Shift staffing</h3></div><span><?= count($shift['roster']) ?> roster record<?= count($shift['roster'])===1?'':'s' ?></span></header><?php if (!$shift['roster']): ?><div class="vsm-empty compact"><b>No confirmed volunteers yet.</b><p>Eligible volunteer signups will appear here.</p></div><?php else: ?><div class="vsm-roster-list"><?php foreach ($shift['roster'] as $member): ?><article><div><b><?= $esc($member['name']) ?></b><small><?= $esc($member['display_role']) ?> · <?= $esc(str_replace('_',' ',$member['status'])) ?></small></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['volunteer_shift_admin_csrf']) ?>"><input type="hidden" name="action" value="roster_status"><input type="hidden" name="shift_id" value="<?= (int)$shift['id'] ?>"><input type="hidden" name="signup_id" value="<?= (int)$member['signup_id'] ?>"><select name="status" aria-label="Status for <?= $esc($member['name']) ?>"><?php foreach (['signed_up'=>'Confirmed','checked_in'=>'Checked in','completed'=>'Completed','no_show'=>'No show','cancelled'=>'Cancelled'] as $value=>$label): ?><option value="<?= $esc($value) ?>"<?= $member['status']===$value?' selected':'' ?>><?= $esc($label) ?></option><?php endforeach; ?></select><button type="submit">Update</button></form></article><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
<?php endif; ?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/volunteer-readiness',$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations','Restricted',$basePath); ?><div class="vsm-page"><section class="vsm-empty"><b>Staff only</b><p>Your current role cannot manage volunteer opportunities.</p><a class="button" href="<?= $url('/volunteer-shifts') ?>">View opportunities</a></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['volunteer_shift_admin_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
