<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';

final class MyAccountExperience
{
    private const ROUTE = '/account';

    public static function handles(string $route): bool
    {
        return $route === self::ROUTE;
    }

    public static function render(string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
        $_SESSION['my_account_csrf'] ??= bin2hex(random_bytes(24));

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::handlePost($db, $user, $basePath);
        }

        $detailStmt = $db->prepare("SELECT id,first_name,last_name,email,initials,display_role,account_status,organization_membership_status,email_verified_at,password_changed_at,last_login_at FROM users WHERE id=:id AND active=1 LIMIT 1");
        $detailStmt->execute(['id'=>(int)$user['id']]);
        $account = $detailStmt->fetch();
        if (!$account) self::redirect(($basePath ?: '') . '/logout');

        $roles = Auth::roles($db, (int)$user['id']);
        $flash = $_SESSION['my_account_flash'] ?? null;
        unset($_SESSION['my_account_flash']);
        self::page($basePath, $user, $account, $roles, $flash);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['my_account_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect(($basePath ?: '') . self::ROUTE);
        }

        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'profile') {
                self::updateProfile($db, (int)$user['id'], $_POST);
                self::flash('success', 'Your profile details were updated.');
            } elseif ($action === 'password') {
                self::changePassword($db, (int)$user['id'], $_POST);
                self::flash('success', 'Your password was changed.');
            } else {
                throw new RuntimeException('Choose a valid account action.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect(($basePath ?: '') . self::ROUTE);
    }

    private static function updateProfile(PDO $db, int $userId, array $input): void
    {
        $first = trim((string)($input['first_name'] ?? ''));
        $last = trim((string)($input['last_name'] ?? ''));
        if ($first === '' || $last === '') throw new RuntimeException('Enter both your first and last name.');
        if (mb_strlen($first) > 100 || mb_strlen($last) > 100) throw new RuntimeException('Names must be 100 characters or fewer.');

        $initials = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
        $db->beginTransaction();
        try {
            $beforeStmt = $db->prepare('SELECT first_name,last_name,initials FROM users WHERE id=:id AND active=1 FOR UPDATE');
            $beforeStmt->execute(['id'=>$userId]);
            $before = $beforeStmt->fetch();
            if (!$before) throw new RuntimeException('Your account is no longer available.');

            $db->prepare('UPDATE users SET first_name=:first,last_name=:last,initials=:initials WHERE id=:id AND active=1')->execute([
                'first'=>$first,
                'last'=>$last,
                'initials'=>$initials,
                'id'=>$userId,
            ]);
            self::audit($db, $userId, 'account.profile_updated', 'user', $userId, 'Updated personal account details.', [
                'before'=>$before,
                'after'=>['first_name'=>$first,'last_name'=>$last,'initials'=>$initials],
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('Your profile could not be updated.');
        }
    }

    private static function changePassword(PDO $db, int $userId, array $input): void
    {
        $current = (string)($input['current_password'] ?? '');
        $next = (string)($input['new_password'] ?? '');
        $confirm = (string)($input['confirm_password'] ?? '');
        if ($current === '' || $next === '' || $confirm === '') throw new RuntimeException('Complete all password fields.');
        if (strlen($next) < 12) throw new RuntimeException('Use a new password with at least 12 characters.');
        if ($next !== $confirm) throw new RuntimeException('The new passwords do not match.');

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=:id AND active=1 AND account_status='active' FOR UPDATE");
            $stmt->execute(['id'=>$userId]);
            $hash = $stmt->fetchColumn();
            if (!$hash) throw new RuntimeException('This account does not have a password yet. Use the password-reset flow to establish one.');
            if (!password_verify($current, (string)$hash)) throw new RuntimeException('Your current password is incorrect.');
            if (password_verify($next, (string)$hash)) throw new RuntimeException('Choose a password different from your current password.');

            $db->prepare("UPDATE users SET password_hash=:hash,password_changed_at=CURRENT_TIMESTAMP WHERE id=:id AND active=1 AND account_status='active'")->execute([
                'hash'=>password_hash($next, PASSWORD_DEFAULT),
                'id'=>$userId,
            ]);
            $db->prepare('UPDATE auth_password_resets SET used_at=COALESCE(used_at,CURRENT_TIMESTAMP) WHERE user_id=:user AND used_at IS NULL')->execute(['user'=>$userId]);
            self::audit($db, $userId, 'account.password_changed', 'user', $userId, 'Changed account password.', []);
            $db->commit();
            session_regenerate_id(true);
            $_SESSION[Auth::SESSION_USER_ID] = $userId;
            $_SESSION['auth_authenticated_at'] = time();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('Your password could not be changed.');
        }
    }

    private static function audit(PDO $db, int $actorId, string $eventType, string $subjectType, int $subjectId, string $summary, array $metadata): void
    {
        $stmt = $db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event_type,:subject_type,:subject_id,:summary,:metadata)');
        $stmt->execute([
            'actor'=>$actorId,
            'event_type'=>$eventType,
            'subject_type'=>$subjectType,
            'subject_id'=>$subjectId,
            'summary'=>$summary,
            'metadata'=>json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private static function page(string $basePath, array $user, array $account, array $roles, ?array $flash): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>My Account · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/my-account.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/account',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Account','My Account',$basePath);?><div class="myaccount-page"><?php if($flash):?><div class="myaccount-flash <?=$e((string)$flash['type'])?>"><?=$e((string)$flash['message'])?></div><?php endif;?>
        <section class="myaccount-hero"><div><small>YOUR CTSMD CONNECT ACCOUNT</small><h2><?=$e(trim((string)$account['first_name'].' '.(string)$account['last_name']))?></h2><p>Keep the basics current and manage your password without needing a staff administrator.</p></div><div class="myaccount-avatar"><?=$e((string)$account['initials'])?></div></section>
        <div class="myaccount-grid"><section class="myaccount-card"><header><small>PROFILE</small><h3>Personal details</h3></header><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['my_account_csrf'])?>"><input type="hidden" name="action" value="profile"><label>First name<input name="first_name" maxlength="100" required value="<?=$e((string)$account['first_name'])?>"></label><label>Last name<input name="last_name" maxlength="100" required value="<?=$e((string)$account['last_name'])?>"></label><label>Email<input value="<?=$e((string)$account['email'])?>" disabled></label><p class="myaccount-note">Email is your sign-in identity. Email changes should use a verified change workflow rather than a simple profile edit.</p><button type="submit">Save profile</button></form></section>
        <section class="myaccount-card"><header><small>SECURITY</small><h3>Change password</h3></header><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['my_account_csrf'])?>"><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label><p class="myaccount-note">Use at least 12 characters. Changing your password invalidates any outstanding password-reset links.</p><button type="submit">Change password</button></form></section>
        <section class="myaccount-card summary"><header><small>ACCOUNT STATUS</small><h3>Access summary</h3></header><dl><div><dt>Membership</dt><dd><?=$e(ucfirst((string)$account['organization_membership_status']))?></dd></div><div><dt>Account</dt><dd><?=$e(ucfirst((string)$account['account_status']))?></dd></div><div><dt>Email verified</dt><dd><?=$account['email_verified_at']?'Yes':'No'?></dd></div><div><dt>Last sign-in</dt><dd><?=$account['last_login_at']?$e(date('M j, Y · g:i A',strtotime((string)$account['last_login_at']))):'Not recorded'?></dd></div><div><dt>Password changed</dt><dd><?=$account['password_changed_at']?$e(date('M j, Y',strtotime((string)$account['password_changed_at']))):'Not recorded'?></dd></div></dl><div class="myaccount-role-list"><?php foreach($roles as $role):?><span><?=$e(ucwords(str_replace('_',' ',(string)$role)))?></span><?php endforeach;?></div><a class="myaccount-link" href="<?=$url('/notification-preferences')?>">Notification preferences →</a></section></div></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['my_account_flash'] = ['type'=>$type,'message'=>$message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
