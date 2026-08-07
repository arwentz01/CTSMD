<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';

final class PeopleExperience
{
    private const ROUTES = ['/family-hub', '/people', '/people/view'];

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
        $_SESSION['people_csrf'] ??= bin2hex(random_bytes(24));

        if ($route !== '/family-hub' && !self::isStaff((string)$user['role'])) {
            self::forbidden($basePath, $user);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, $basePath);
        }

        if ($route === '/family-hub') {
            self::familyPage($db, $basePath, $user);
        }

        $people = self::people($db);
        $selected = null;
        if ($route === '/people/view') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selected = self::person($db, (int)$id);
        }

        self::staffPage($route, $basePath, $user, $people, $selected, $db);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        if (!self::isStaff((string)$user['role'])) {
            self::forbidden($basePath, $user);
        }

        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['people_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/people');
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'add_relationship') {
                $guardianId = filter_input(INPUT_POST, 'guardian_user_id', FILTER_VALIDATE_INT) ?: 0;
                $studentId = filter_input(INPUT_POST, 'student_user_id', FILTER_VALIDATE_INT) ?: 0;
                $type = (string)($_POST['relationship_type'] ?? 'guardian');
                self::addRelationship($db, (int)$user['id'], (int)$guardianId, (int)$studentId, $type);
                self::flash('success', 'Family relationship saved.');
                self::redirect($basePath . '/people/view?id=' . (int)$studentId);
            }
            if ($action === 'deactivate_relationship') {
                $relationshipId = filter_input(INPUT_POST, 'relationship_id', FILTER_VALIDATE_INT) ?: 0;
                $studentId = filter_input(INPUT_POST, 'student_user_id', FILTER_VALIDATE_INT) ?: 0;
                self::deactivateRelationship($db, (int)$relationshipId);
                self::flash('success', 'Family relationship deactivated. History was retained.');
                self::redirect($basePath . '/people/view?id=' . (int)$studentId);
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/people');
    }

    private static function addRelationship(PDO $db, int $actorId, int $guardianId, int $studentId, string $type): void
    {
        if ($guardianId < 1 || $studentId < 1 || $guardianId === $studentId) {
            throw new RuntimeException('Choose a valid guardian and student.');
        }
        if (!in_array($type, ['parent', 'guardian', 'caregiver'], true)) {
            throw new RuntimeException('Choose a valid relationship type.');
        }

        $stmt = $db->prepare('SELECT id, display_role, active FROM users WHERE id IN (:guardian, :student)');
        $stmt->execute(['guardian' => $guardianId, 'student' => $studentId]);
        $users = $stmt->fetchAll();
        if (count($users) !== 2) {
            throw new RuntimeException('One of those people could not be found.');
        }

        $byId = [];
        foreach ($users as $row) {
            $byId[(int)$row['id']] = $row;
        }
        if (empty($byId[$guardianId]['active']) || empty($byId[$studentId]['active'])) {
            throw new RuntimeException('Family relationships can only use active people.');
        }
        if (!str_contains(strtolower((string)$byId[$studentId]['display_role']), 'student')) {
            throw new RuntimeException('The linked child must currently have a student role.');
        }
        if (str_contains(strtolower((string)$byId[$guardianId]['display_role']), 'student')) {
            throw new RuntimeException('A student cannot be assigned as the guardian in this relationship.');
        }

        $sql = "INSERT INTO family_relationships (guardian_user_id, student_user_id, relationship_type, is_primary, status, created_by_user_id)
                VALUES (:guardian, :student, :relationship_type, 0, 'active', :actor)
                ON DUPLICATE KEY UPDATE relationship_type = VALUES(relationship_type), status = 'active', created_by_user_id = VALUES(created_by_user_id), updated_at = CURRENT_TIMESTAMP";
        $insert = $db->prepare($sql);
        $insert->execute([
            'guardian' => $guardianId,
            'student' => $studentId,
            'relationship_type' => $type,
            'actor' => $actorId,
        ]);
    }

    private static function deactivateRelationship(PDO $db, int $relationshipId): void
    {
        if ($relationshipId < 1) {
            throw new RuntimeException('That relationship could not be found.');
        }
        $stmt = $db->prepare("UPDATE family_relationships SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $relationshipId]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('That relationship is already inactive or unavailable.');
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

    private static function isStaff(string $role): bool
    {
        $role = strtolower($role);
        return str_contains($role, 'manager') || str_contains($role, 'director') || str_contains($role, 'admin') || str_contains($role, 'staff');
    }

    private static function familyForGuardian(PDO $db, int $guardianId): array
    {
        $stmt = $db->prepare("SELECT fr.id AS relationship_id, fr.relationship_type, fr.is_primary, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.initials, u.display_role AS role
                              FROM family_relationships fr
                              JOIN users u ON u.id = fr.student_user_id
                              WHERE fr.guardian_user_id = :guardian_id AND fr.status = 'active' AND u.active = 1
                              ORDER BY fr.is_primary DESC, u.last_name, u.first_name");
        $stmt->execute(['guardian_id' => $guardianId]);
        return $stmt->fetchAll();
    }

    private static function guardiansForStudent(PDO $db, int $studentId): array
    {
        $stmt = $db->prepare("SELECT fr.id AS relationship_id, fr.relationship_type, fr.is_primary, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.initials, u.display_role AS role
                              FROM family_relationships fr
                              JOIN users u ON u.id = fr.guardian_user_id
                              WHERE fr.student_user_id = :student_id AND fr.status = 'active' AND u.active = 1
                              ORDER BY fr.is_primary DESC, u.last_name, u.first_name");
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    private static function people(PDO $db): array
    {
        $sql = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.initials, u.display_role AS role, u.email,
                       (SELECT COUNT(*) FROM family_relationships fr WHERE fr.guardian_user_id = u.id AND fr.status = 'active') AS student_links,
                       (SELECT COUNT(*) FROM family_relationships fr WHERE fr.student_user_id = u.id AND fr.status = 'active') AS guardian_links
                FROM users u WHERE u.active = 1 ORDER BY u.last_name, u.first_name";
        return $db->query($sql)->fetchAll();
    }

    private static function person(PDO $db, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, first_name, last_name, initials, display_role AS role, email, active FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private static function relationshipCandidates(PDO $db, int $excludeId): array
    {
        $stmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role FROM users WHERE active = 1 AND id <> :exclude ORDER BY last_name, first_name");
        $stmt->execute(['exclude' => $excludeId]);
        return $stmt->fetchAll();
    }

    private static function familyPage(PDO $db, string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $family = self::familyForGuardian($db, (int)$user['id']);

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My family · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/people-implementation.css') ?>"></head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/family-hub', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Home', 'My family', $basePath, [['label'=>'Today','href'=>'/app','active'=>false],['label'=>'My family','href'=>'/family-hub','active'=>true],['label'=>'Notifications','href'=>'/notifications','active'=>false]]); ?><div class="people-page">
<section class="people-hero"><small>FAMILY RELATIONSHIPS</small><h2>Your theatre family</h2><p>Family links are now explicit organization records. They are used to determine guardian context and will later drive safe conversation creation.</p></section>
<?php if ($family): ?><div class="family-card-grid"><?php foreach ($family as $person): ?><article class="family-person-card"><header><i><?= $esc($person['initials']) ?></i><div><h3><?= $esc($person['name']) ?></h3><p><?= $esc($person['role']) ?></p></div></header><div class="family-relation"><span><?= $esc(ucfirst($person['relationship_type'])) ?></span><?php if ($person['is_primary']): ?><b>Primary guardian</b><?php endif; ?></div><div class="family-safety-note"><b>Guardian context available</b><p>CTSMD can use this relationship when a safeguarded student/adult conversation is created.</p></div></article><?php endforeach; ?></div><?php else: ?><section class="people-empty"><b>No linked students</b><p>A staff member must establish family relationships before they can be used for safeguarding.</p></section><?php endif; ?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function staffPage(string $route, string $basePath, array $user, array $people, ?array $selected, PDO $db): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['people_flash'] ?? null;
        unset($_SESSION['people_flash']);
        $title = $route === '/people' ? 'People & families' : ($selected['name'] ?? 'Person');
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/people-implementation.css') ?>"></head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Administration', $title, $basePath); ?><div class="people-page"><?php if ($flash): ?><div class="people-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
<?php if ($route === '/people'): ?>
<section class="people-hero"><small>STAFF WORKSPACE</small><h2>People are relationships, not rows.</h2><p>See family context before access settings. This directory is restricted server-side to staff-like roles.</p></section>
<div class="people-directory"><?php foreach ($people as $person): ?><a href="<?= $url('/people/view?id=' . (int)$person['id']) ?>"><i><?= $esc($person['initials']) ?></i><div><h3><?= $esc($person['name']) ?></h3><p><?= $esc($person['role']) ?></p><small><?= (int)$person['student_links'] ?> student links · <?= (int)$person['guardian_links'] ?> guardian links</small></div><span>Open →</span></a><?php endforeach; ?></div>
<?php else: ?>
<?php if (!$selected): ?><section class="people-empty"><b>Person not found</b><a class="button" href="<?= $url('/people') ?>">Back to people</a></section><?php else: $isStudent = str_contains(strtolower((string)$selected['role']), 'student'); $links = $isStudent ? self::guardiansForStudent($db, (int)$selected['id']) : self::familyForGuardian($db, (int)$selected['id']); $candidates = self::relationshipCandidates($db, (int)$selected['id']); ?>
<section class="person-header"><div class="person-id"><i><?= $esc($selected['initials']) ?></i><div><small>PERSON RECORD</small><h2><?= $esc($selected['name']) ?></h2><p><?= $esc($selected['role']) ?> · <?= $esc((string)$selected['email']) ?></p></div></div><a href="<?= $url('/people') ?>">← Directory</a></section>
<div class="person-layout"><section class="people-panel"><header><small>FAMILY CONTEXT</small><h3><?= $isStudent ? 'Guardians & caregivers' : 'Linked students' ?></h3></header><?php if ($links): ?><?php foreach ($links as $link): ?><article class="relationship-row"><div><b><?= $esc($link['name']) ?></b><small><?= $esc(ucfirst($link['relationship_type'])) ?><?= $link['is_primary'] ? ' · Primary' : '' ?></small></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['people_csrf']) ?>"><input type="hidden" name="action" value="deactivate_relationship"><input type="hidden" name="relationship_id" value="<?= (int)$link['relationship_id'] ?>"><input type="hidden" name="student_user_id" value="<?= $isStudent ? (int)$selected['id'] : (int)$link['id'] ?>"><button type="submit">Deactivate</button></form></article><?php endforeach; ?><?php else: ?><div class="people-empty compact"><b>No active family links</b><p>This person has no explicit family relationship in CTSMD yet.</p></div><?php endif; ?></section>
<section class="people-panel"><header><small>ADD RELATIONSHIP</small><h3><?= $isStudent ? 'Link a guardian' : 'Link a student' ?></h3></header><form class="relationship-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['people_csrf']) ?>"><input type="hidden" name="action" value="add_relationship"><?php if ($isStudent): ?><input type="hidden" name="student_user_id" value="<?= (int)$selected['id'] ?>"><label>Guardian<select name="guardian_user_id" required><option value="">Choose person</option><?php foreach ($candidates as $candidate): if (str_contains(strtolower((string)$candidate['role']), 'student')) continue; ?><option value="<?= (int)$candidate['id'] ?>"><?= $esc($candidate['name'] . ' · ' . $candidate['role']) ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="guardian_user_id" value="<?= (int)$selected['id'] ?>"><label>Student<select name="student_user_id" required><option value="">Choose student</option><?php foreach ($candidates as $candidate): if (!str_contains(strtolower((string)$candidate['role']), 'student')) continue; ?><option value="<?= (int)$candidate['id'] ?>"><?= $esc($candidate['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?><label>Relationship<select name="relationship_type"><option value="parent">Parent</option><option value="guardian">Guardian</option><option value="caregiver">Caregiver</option></select></label><button class="button" type="submit">Save relationship</button><p class="people-help">Relationships are deactivated, not deleted, so safeguarding history remains traceable.</p></form></section></div>
<?php endif; ?><?php endif; ?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/people-implementation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/people', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Administration', 'Restricted', $basePath); ?><div class="people-page"><section class="people-forbidden"><span>STAFF ONLY</span><h2>This workspace is restricted.</h2><p>Your current role, <?= $esc($user['role']) ?>, does not have access to organization-wide people and family administration.</p><a class="button" href="<?= $url('/family-hub') ?>">Go to my family</a></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['people_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
