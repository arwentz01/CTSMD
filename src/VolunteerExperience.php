<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';

final class VolunteerExperience
{
    private const ROUTES = ['/volunteer-readiness', '/volunteer-shifts', '/volunteer/shift'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) {
            self::redirect(($basePath ?: '') . '/login');
        }
        $userId = (int)$user['id'];
        $_SESSION['volunteer_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $userId, $basePath);
        }

        $credentials = self::credentials($db, $userId);
        $shifts = self::shifts($db, $userId);
        $selectedShift = null;
        if ($route === '/volunteer/shift') {
            $shiftId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selectedShift = self::shiftById($db, $userId, (int)$shiftId);
        }

        self::page($route, $basePath, $user, $credentials, $shifts, $selectedShift);
    }

    private static function handlePost(PDO $db, int $userId, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['volunteer_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/volunteer-shifts');
        }

        $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT) ?: 0;
        $action = (string)($_POST['action'] ?? '');
        if ($shiftId < 1) {
            self::flash('error', 'That volunteer shift could not be found.');
            self::redirect($basePath . '/volunteer-shifts');
        }

        try {
            if ($action === 'signup') {
                self::signup($db, $userId, (int)$shiftId);
                self::flash('success', 'You are signed up. The shift is now part of your volunteer commitments.');
            } elseif ($action === 'cancel') {
                self::cancelSignup($db, $userId, (int)$shiftId);
                self::flash('success', 'Your volunteer signup was cancelled.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/volunteer/shift?id=' . (int)$shiftId);
    }

    private static function signup(PDO $db, int $userId, int $shiftId): void
    {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id, required_slots, approval_required FROM volunteer_shifts WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $shiftId]);
            $shift = $stmt->fetch();
            if (!$shift) throw new RuntimeException('That volunteer shift no longer exists.');
            if ((bool)$shift['approval_required']) throw new RuntimeException('This role requires coordinator approval. Submit it through Approval requests.');

            $missing = self::missingRequirements($db, $userId, $shiftId);
            if ($missing) throw new RuntimeException('You are not eligible yet. Complete: ' . implode(', ', $missing) . '.');

            $existing = $db->prepare("SELECT id, status FROM volunteer_shift_signups WHERE shift_id = :shift_id AND user_id = :user_id FOR UPDATE");
            $existing->execute(['shift_id' => $shiftId, 'user_id' => $userId]);
            $signup = $existing->fetch();
            if ($signup && in_array($signup['status'], ['signed_up', 'checked_in', 'completed'], true)) {
                $db->commit();
                return;
            }

            $count = $db->prepare("SELECT COUNT(*) FROM volunteer_shift_signups WHERE shift_id = :shift_id AND status IN ('signed_up','checked_in','completed')");
            $count->execute(['shift_id' => $shiftId]);
            if ((int)$count->fetchColumn() >= (int)$shift['required_slots']) throw new RuntimeException('That shift filled before your signup was completed.');

            if ($signup) {
                $update = $db->prepare("UPDATE volunteer_shift_signups SET status = 'signed_up', created_at = CURRENT_TIMESTAMP WHERE id = :id");
                $update->execute(['id' => $signup['id']]);
            } else {
                $insert = $db->prepare("INSERT INTO volunteer_shift_signups (shift_id, user_id, status) VALUES (:shift_id, :user_id, 'signed_up')");
                $insert->execute(['shift_id' => $shiftId, 'user_id' => $userId]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('We could not complete that signup. Please try again.');
        }
    }

    private static function cancelSignup(PDO $db, int $userId, int $shiftId): void
    {
        $stmt = $db->prepare("UPDATE volunteer_shift_signups SET status = 'cancelled' WHERE shift_id = :shift_id AND user_id = :user_id AND status = 'signed_up'");
        $stmt->execute(['shift_id' => $shiftId, 'user_id' => $userId]);
    }

    private static function credentials(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT vr.name, vr.category, COALESCE(vc.status, 'missing') AS status, vc.expires_at FROM volunteer_requirements vr LEFT JOIN volunteer_credentials vc ON vc.requirement_id = vr.id AND vc.user_id = :user_id ORDER BY vr.id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private static function shifts(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT vs.id, vs.title, vs.category, vs.starts_at, vs.ends_at, vs.location, vs.required_slots, vs.approval_required, COALESCE(cap.active_signups,0) AS active_signups, mine.status AS my_status FROM volunteer_shifts vs LEFT JOIN (SELECT shift_id, COUNT(*) AS active_signups FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) cap ON cap.shift_id = vs.id LEFT JOIN volunteer_shift_signups mine ON mine.shift_id = vs.id AND mine.user_id = :user_id ORDER BY vs.starts_at ASC");
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['missing'] = self::missingRequirements($db, $userId, (int)$row['id']);
            $row['requirements_met'] = !$row['missing'];
            $row['approval_gate'] = (bool)$row['approval_required'];
            $row['actionable'] = $row['requirements_met'] && !$row['approval_gate'];
            $row['open_slots'] = max((int)$row['required_slots'] - (int)$row['active_signups'], 0);
        }
        unset($row);
        return $rows;
    }

    private static function shiftById(PDO $db, int $userId, int $shiftId): ?array
    {
        if ($shiftId < 1) return null;
        foreach (self::shifts($db, $userId) as $shift) if ((int)$shift['id'] === $shiftId) return $shift;
        return null;
    }

    private static function missingRequirements(PDO $db, int $userId, int $shiftId): array
    {
        $stmt = $db->prepare("SELECT vr.name FROM volunteer_shift_requirements vsr JOIN volunteer_requirements vr ON vr.id = vsr.requirement_id LEFT JOIN volunteer_credentials vc ON vc.requirement_id = vr.id AND vc.user_id = :user_id WHERE vsr.shift_id = :shift_id AND (vc.id IS NULL OR vc.status <> 'approved' OR (vc.expires_at IS NOT NULL AND vc.expires_at < NOW())) ORDER BY vr.id");
        $stmt->execute(['user_id' => $userId, 'shift_id' => $shiftId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function flash(string $type, string $message): void{$_SESSION['volunteer_flash'] = ['type' => $type, 'message' => $message];}
    private static function redirect(string $url): never{header('Location: ' . $url, true, 303);exit;}

    private static function page(string $route, string $basePath, array $user, array $credentials, array $shifts, ?array $selectedShift): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['volunteer_flash'] ?? null;unset($_SESSION['volunteer_flash']);
        $readyCount = count(array_filter($credentials, static fn(array $c): bool => $c['status'] === 'approved' && (!$c['expires_at'] || strtotime($c['expires_at']) >= time())));
        $eligibleCount = count(array_filter($shifts, static fn(array $s): bool => $s['actionable'] && $s['open_slots'] > 0));
        $commitments = array_values(array_filter($shifts, static fn(array $s): bool => in_array($s['my_status'], ['signed_up','checked_in','completed'], true)));
        $title = match ($route) {'/volunteer-readiness' => 'Volunteer readiness','/volunteer-shifts' => 'Volunteer opportunities',default => $selectedShift['title'] ?? 'Volunteer shift'};
        $subnav = [
            ['label' => 'Readiness', 'href' => '/volunteer-readiness', 'active' => $route === '/volunteer-readiness'],
            ['label' => 'Opportunities', 'href' => '/volunteer-shifts', 'active' => in_array($route, ['/volunteer-shifts','/volunteer/shift'], true)],
            ['label' => 'Approval requests', 'href' => '/volunteer/approvals', 'active' => false],
            ['label' => 'Training', 'href' => '/volunteer/training', 'active' => false],
            ['label' => 'Service record', 'href' => '/volunteer/history', 'active' => false],
            ['label' => 'Verifications', 'href' => '/volunteer/verifications', 'active' => false],
        ];

        header('Content-Type: text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?=$esc($title)?> · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/volunteer-implementation.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Volunteer',$title,$basePath,$subnav);?><div class="vol-page"><?php if($flash):?><div class="vol-flash <?=$esc($flash['type'])?>"><?=$esc($flash['message'])?></div><?php endif;?>
        <?php if($route==='/volunteer-readiness'):?><section class="vol-hero"><div><small>YOUR VOLUNTEER PROFILE</small><h2>Know what you can help with before you browse.</h2><p>CTSMD checks required training and credentials against each shift. If something is locked, you should be able to see exactly why.</p></div><div class="vol-score"><b><?=$readyCount?>/<?=count($credentials)?></b><span>requirements current</span></div></section><div class="vol-readiness-layout"><section class="vol-panel"><header><small>REQUIREMENTS</small><h3>Your readiness</h3></header><?php foreach($credentials as $credential):$current=$credential['status']==='approved'&&(!$credential['expires_at']||strtotime($credential['expires_at'])>=time());?><article class="vol-credential"><span><b><?=$esc($credential['name'])?></b><small><?=$credential['expires_at']?'Expires '.$esc(date('M j, Y',strtotime($credential['expires_at']))):$esc(ucfirst($credential['category']))?></small></span><em class="<?=$current?'good':'attention'?>"><?=$current?'Current':$esc(ucfirst($credential['status']))?></em></article><?php endforeach;?></section><aside class="vol-panel accent"><small>OPPORTUNITIES</small><h3><?=$eligibleCount?> shifts you can take now</h3><p>Open shifts can be instant signup or approval-gated. Gated roles use the Approval requests workspace once your requirements are current.</p><a class="button full" href="<?=$url('/volunteer-shifts')?>">Browse opportunities</a><a class="vol-back" href="<?=$url('/volunteer/approvals')?>">Approval-gated roles →</a><a class="vol-back" href="<?=$url('/volunteer/history')?>">View service record →</a><?php if($commitments):?><div class="vol-commitment"><b><?=count($commitments)?> current commitment<?=count($commitments)===1?'':'s'?></b><span>Your confirmed shifts remain visible in the opportunities list.</span></div><?php endif;?></aside></div>
        <?php elseif($route==='/volunteer-shifts'):?><section class="vol-heading"><div><small>OPEN OPPORTUNITIES</small><h2>Help where you’re cleared and needed.</h2><p>Availability, eligibility, approval gates and your existing commitments are shown before you open a shift.</p></div><a href="<?=$url('/volunteer-readiness')?>">Review readiness →</a></section><div class="vol-shift-list"><?php foreach($shifts as $shift):$signed=in_array($shift['my_status'],['signed_up','checked_in','completed'],true);?><a class="vol-shift-card<?=!$shift['actionable']?' locked':''?>" href="<?=$url('/volunteer/shift?id='.(int)$shift['id'])?>"><div class="vol-date"><b><?=$esc(date('M',strtotime($shift['starts_at'])))?></b><span><?=$esc(date('j',strtotime($shift['starts_at'])))?></span></div><div><small><?=$esc(strtoupper($shift['category']))?></small><h3><?=$esc($shift['title'])?></h3><p><?=$esc(date('g:i A',strtotime($shift['starts_at'])))?>–<?=$esc(date('g:i A',strtotime($shift['ends_at'])))?> · <?=$esc($shift['location'])?></p><?php if($shift['missing']):?><span class="vol-lock-reason">Needs <?=$esc(implode(' + ',$shift['missing']))?></span><?php elseif($shift['approval_gate']):?><span class="vol-lock-reason">Coordinator approval required</span><?php endif;?></div><div class="vol-shift-status"><?php if($signed):?><b class="signed">Signed up</b><?php elseif($shift['approval_gate']):?><b class="locked">Approval</b><?php elseif(!$shift['requirements_met']):?><b class="locked">Locked</b><?php elseif($shift['open_slots']<1):?><b>Full</b><?php else:?><b class="eligible"><?=(int)$shift['open_slots']?> open</b><?php endif;?><span>View →</span></div></a><?php endforeach;?></div>
        <?php else:?><?php if(!$selectedShift):?><section class="vol-empty"><b>Shift not found</b><p>This volunteer opportunity may have been removed.</p><a class="button" href="<?=$url('/volunteer-shifts')?>">Back to opportunities</a></section><?php else:$signed=in_array($selectedShift['my_status'],['signed_up','checked_in','completed'],true);?><section class="vol-detail-hero"><div><small><?=$esc(strtoupper($selectedShift['category']))?></small><h2><?=$esc($selectedShift['title'])?></h2><p><?=$esc(date('l, F j · g:i A',strtotime($selectedShift['starts_at'])))?>–<?=$esc(date('g:i A',strtotime($selectedShift['ends_at'])))?><br><?=$esc($selectedShift['location'])?></p></div><div class="vol-capacity"><b><?=(int)$selectedShift['open_slots']?></b><span>open of <?=(int)$selectedShift['required_slots']?></span></div></section><div class="vol-detail-grid"><section class="vol-panel"><header><small>ELIGIBILITY</small><h3><?=$selectedShift['requirements_met']?'Your requirements are current':'One or more requirements need attention'?></h3></header><?php if($selectedShift['missing']):?><div class="vol-blocked"><b>This shift is locked for you.</b><p>Complete or renew: <?=$esc(implode(', ',$selectedShift['missing']))?>.</p><a href="<?=$url('/volunteer-readiness')?>">Review readiness →</a></div><?php elseif($selectedShift['approval_gate']):?><div class="vol-cleared"><b>✓ Credentials current.</b><p>This role still needs a coordinator decision before a slot can be confirmed.</p></div><?php else:?><div class="vol-cleared"><b>✓ You are cleared for this role.</b><p>Eligibility is re-checked when you confirm signup.</p></div><?php endif;?><div class="vol-facts"><span><b>Commitment</b><small><?=$esc(date('g:i A',strtotime($selectedShift['starts_at'])))?>–<?=$esc(date('g:i A',strtotime($selectedShift['ends_at'])))?></small></span><span><b>Location</b><small><?=$esc($selectedShift['location'])?></small></span><span><b>Coverage</b><small><?=(int)$selectedShift['open_slots']?> remaining</small></span></div></section><aside class="vol-decision"><small>YOUR DECISION</small><?php if($signed):?><h3>You’re signed up.</h3><p>This commitment is reserved for you.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['volunteer_csrf'])?>"><input type="hidden" name="shift_id" value="<?=(int)$selectedShift['id']?>"><input type="hidden" name="action" value="cancel"><button class="button secondary full" type="submit">Cancel signup</button></form><?php elseif($selectedShift['approval_gate']):?><h3>Request coordinator approval.</h3><p>Your credentials are current. Submit a request; the role is only reserved after staff approves it.</p><a class="button full" href="<?=$url('/volunteer/approvals')?>">Open approval requests</a><?php elseif(!$selectedShift['requirements_met']):?><h3>Not available yet.</h3><p>Once the missing requirement is approved, eligible shifts become actionable automatically.</p><a class="button full" href="<?=$url('/volunteer-readiness')?>">Review requirements</a><?php elseif($selectedShift['open_slots']<1):?><h3>This shift is full.</h3><p>No additional signup can be accepted right now.</p><a class="button secondary full" href="<?=$url('/volunteer-shifts')?>">Find another shift</a><?php else:?><h3>Ready to help?</h3><p>Confirming reserves one of the remaining volunteer slots.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['volunteer_csrf'])?>"><input type="hidden" name="shift_id" value="<?=(int)$selectedShift['id']?>"><input type="hidden" name="action" value="signup"><button class="button full" type="submit">Confirm signup</button></form><?php endif;?><a class="vol-back" href="<?=$url('/volunteer-shifts')?>">← All opportunities</a></aside></div><?php endif;?><?php endif;?></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
