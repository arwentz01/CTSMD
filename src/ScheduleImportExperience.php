<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/IcsImportService.php';

final class ScheduleImportExperience
{
    private const ROUTE = '/production/schedule/import';
    private const SESSION_KEY = 'schedule_ics_preview';

    public static function handles(string $route): bool { return $route === self::ROUTE; }

    public static function render(string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
        if (!AccessPolicy::canManageSchedule($user)) self::forbidden($basePath, $user);
        $_SESSION['schedule_import_csrf'] ??= bin2hex(random_bytes(24));

        $production = ProductionContext::selected($db, $user);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') self::handlePost($db, $user, $production, $basePath);

        $preview = $_SESSION[self::SESSION_KEY] ?? null;
        if ($preview && (!$production || (int)($preview['production_id'] ?? 0) !== (int)$production['id'])) {
            unset($_SESSION[self::SESSION_KEY]);
            $preview = null;
        }
        $flash = $_SESSION['schedule_import_flash'] ?? null;
        unset($_SESSION['schedule_import_flash']);
        self::page($basePath, $user, $production, is_array($preview) ? $preview : null, $flash);
    }

    private static function handlePost(PDO $db, array $user, ?array $production, string $basePath): never
    {
        if (!hash_equals((string)($_SESSION['schedule_import_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            self::flash('error', 'Your import session expired. Please try again.');
            self::redirect(($basePath ?: '') . self::ROUTE);
        }
        if (!$production) {
            self::flash('error', 'Select an active production before importing a schedule.');
            self::redirect(($basePath ?: '') . '/production');
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'preview') self::previewUpload($db, $production, $_FILES['ics_file'] ?? [], $_POST);
            elseif ($action === 'import') self::importSelected($db, $user, $production, $_POST);
            elseif ($action === 'clear') unset($_SESSION[self::SESSION_KEY]);
            else throw new RuntimeException('Choose a valid calendar import action.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }
        self::redirect(($basePath ?: '') . self::ROUTE);
    }

    private static function previewUpload(PDO $db, array $production, array $file, array $input): void
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException($error === UPLOAD_ERR_NO_FILE ? 'Choose an .ics calendar file to import.' : 'The calendar upload did not complete successfully.');
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > 2 * 1024 * 1024) throw new RuntimeException('Use an .ics file smaller than 2 MB.');
        $name = (string)($file['name'] ?? 'calendar.ics');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'ics') throw new RuntimeException('Choose a file ending in .ics.');
        $tmp = (string)($file['tmp_name'] ?? '');
        $contents = $tmp !== '' ? @file_get_contents($tmp) : false;
        if ($contents === false) throw new RuntimeException('The uploaded calendar could not be read.');

        $events = IcsImportService::parse($contents);
        $existing = self::existingKeys($db, (int)$production['id']);
        foreach ($events as $index => &$event) {
            $event['key'] = hash('sha256', ($event['uid'] ?: (string)$index) . '|' . $event['title'] . '|' . $event['starts_at']);
            $event['duplicate'] = isset($existing[self::duplicateKey((string)$event['title'], (string)$event['starts_at'])]);
            $event['selectable'] = !$event['duplicate'] && !$event['cancelled'] && !$event['recurring'];
        }
        unset($event);

        $itemType = trim((string)($input['item_type'] ?? 'rehearsal'));
        $visibility = (string)($input['visibility'] ?? 'all');
        $fallbackLocation = trim((string)($input['fallback_location'] ?? ''));
        if ($itemType === '' || mb_strlen($itemType) > 80) throw new RuntimeException('Choose a valid activity type.');
        if (!in_array($visibility, ['all','family','staff'], true)) throw new RuntimeException('Choose a valid audience.');
        if (mb_strlen($fallbackLocation) > 190) throw new RuntimeException('The fallback location must be 190 characters or fewer.');

        $_SESSION[self::SESSION_KEY] = [
            'production_id'=>(int)$production['id'],
            'filename'=>$name,
            'item_type'=>$itemType,
            'visibility'=>$visibility,
            'fallback_location'=>$fallbackLocation,
            'events'=>$events,
        ];
        $available = count(array_filter($events, static fn(array $event): bool => (bool)$event['selectable']));
        self::flash('success', 'Calendar parsed. ' . $available . ' event' . ($available === 1 ? '' : 's') . ' ready to import.');
    }

    private static function importSelected(PDO $db, array $user, array $production, array $input): void
    {
        $preview = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($preview) || (int)($preview['production_id'] ?? 0) !== (int)$production['id']) throw new RuntimeException('Upload and preview the calendar again before importing.');
        $selected = array_values(array_unique(array_map('strval', (array)($input['event_keys'] ?? []))));
        if (!$selected) throw new RuntimeException('Choose at least one calendar event to import.');

        $eventsByKey = [];
        foreach ((array)$preview['events'] as $event) $eventsByKey[(string)$event['key']] = $event;
        $existing = self::existingKeys($db, (int)$production['id']);
        $inserted = [];
        $skipped = 0;

        $db->beginTransaction();
        try {
            $insert = $db->prepare("INSERT INTO schedule_items (production_id,title,starts_at,ends_at,family_call_at,location,visibility,audience_mode,item_type) VALUES (:production,:title,:starts_at,:ends_at,NULL,:location,:visibility,'production',:item_type)");
            foreach ($selected as $key) {
                $event = $eventsByKey[$key] ?? null;
                if (!$event || empty($event['selectable'])) { $skipped++; continue; }
                $duplicateKey = self::duplicateKey((string)$event['title'], (string)$event['starts_at']);
                if (isset($existing[$duplicateKey])) { $skipped++; continue; }
                $location = trim((string)$event['location']);
                if ($location === '') $location = trim((string)($preview['fallback_location'] ?? ''));
                if ($location === '') $location = 'TBD';
                $location = mb_substr($location, 0, 190);
                $title = mb_substr(trim((string)$event['title']), 0, 190);
                $insert->execute([
                    'production'=>(int)$production['id'],
                    'title'=>$title,
                    'starts_at'=>(string)$event['starts_at'],
                    'ends_at'=>(string)$event['ends_at'],
                    'location'=>$location,
                    'visibility'=>(string)$preview['visibility'],
                    'item_type'=>(string)$preview['item_type'],
                ]);
                $itemId = (int)$db->lastInsertId();
                $inserted[] = $itemId;
                $existing[$duplicateKey] = true;
            }
            if (!$inserted) throw new RuntimeException('None of the selected events could be imported. They may already exist or require review.');
            self::audit($db, (int)$user['id'], (int)$production['id'], $inserted, (string)($preview['filename'] ?? 'calendar.ics'), $skipped);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The selected calendar events could not be imported.');
        }

        unset($_SESSION[self::SESSION_KEY]);
        self::flash('success', count($inserted) . ' schedule event' . (count($inserted) === 1 ? '' : 's') . ' imported into ' . $production['title'] . ($skipped ? '; ' . $skipped . ' skipped.' : '.'));
    }

    private static function existingKeys(PDO $db, int $productionId): array
    {
        $stmt = $db->prepare('SELECT title,starts_at FROM schedule_items WHERE production_id=:production');
        $stmt->execute(['production'=>$productionId]);
        $keys = [];
        foreach ($stmt->fetchAll() as $row) $keys[self::duplicateKey((string)$row['title'], (string)$row['starts_at'])] = true;
        return $keys;
    }

    private static function duplicateKey(string $title, string $startsAt): string
    {
        return mb_strtolower(trim($title)) . '|' . $startsAt;
    }

    private static function audit(PDO $db, int $actorId, int $productionId, array $itemIds, string $filename, int $skipped): void
    {
        $stmt = $db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'schedule.ics_imported','production',:production,'Imported production schedule from iCalendar file.',:metadata)");
        $stmt->execute([
            'actor'=>$actorId,
            'production'=>$productionId,
            'metadata'=>json_encode(['filename'=>$filename,'schedule_item_ids'=>$itemIds,'imported_count'=>count($itemIds),'skipped_count'=>$skipped], JSON_THROW_ON_ERROR),
        ]);
    }

    private static function page(string $basePath, array $user, ?array $production, ?array $preview, ?array $flash): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $events = (array)($preview['events'] ?? []);
        $ready = count(array_filter($events, static fn(array $event): bool => (bool)($event['selectable'] ?? false)));
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Import calendar · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/schedule-import.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Import schedule',$basePath,[['label'=>'Overview','href'=>'/production','active'=>false],['label'=>'Schedule','href'=>'/schedule','active'=>true],['label'=>'Groups','href'=>'/production/groups','active'=>false],['label'=>'Resources','href'=>'/resources','active'=>false]]);?><div class="si-page">
        <?php if($flash):?><div class="si-flash <?=$e((string)$flash['type'])?>"><?=$e((string)$flash['message'])?></div><?php endif;?>
        <?php if(!$production):?><section class="si-empty"><h2>Select a production first.</h2><p>Imported events must belong to an active working production.</p><a class="button" href="<?=$url('/production')?>">Choose production</a></section><?php elseif(!$preview):?>
        <section class="si-hero"><div><small><?=$e(strtoupper((string)$production['title']))?></small><h2>Bring an existing calendar into the callboard.</h2><p>Upload an iCalendar (.ics) export to preview events before anything is written to CTSMD Connect.</p></div><a href="<?=$url('/schedule')?>">← Schedule</a></section>
        <form class="si-upload" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['schedule_import_csrf'])?>"><input type="hidden" name="action" value="preview"><label class="si-file">Calendar file<input type="file" name="ics_file" accept=".ics,text/calendar" required><small>Maximum 2 MB · up to 500 VEVENT entries</small></label><div class="si-grid"><label>Imported activity type<select name="item_type"><option value="rehearsal">Rehearsal</option><option value="performance">Performance</option><option value="meeting">Meeting</option><option value="orientation">Orientation</option><option value="call">Call / check-in</option><option value="other">Other</option></select></label><label>Audience<select name="visibility"><option value="all">Participants + staff/guardians</option><option value="family">Students + guardians</option><option value="staff">Staff only</option></select></label></div><label>Fallback location <span>optional</span><input name="fallback_location" maxlength="190" placeholder="Used only when an ICS event has no location"></label><div class="si-note"><b>Safe import behavior</b><span>Nothing is imported during preview. Existing title + start-time matches are flagged as duplicates. Cancelled and recurring master events are held for review.</span></div><button class="button" type="submit">Preview calendar</button></form>
        <?php else:?><section class="si-hero"><div><small><?=$e(strtoupper((string)$production['title']))?></small><h2>Review <?=$e((string)($preview['filename'] ?? 'calendar.ics'))?></h2><p><?=$ready?> events are ready to import. Check the dates and uncheck anything CTSMD should not create.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['schedule_import_csrf'])?>"><input type="hidden" name="action" value="clear"><button type="submit" class="si-link-button">Choose another file</button></form></section>
        <form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['schedule_import_csrf'])?>"><input type="hidden" name="action" value="import"><div class="si-table"><div class="si-row si-head"><span>Import</span><span>Event</span><span>When</span><span>Location</span><span>Status</span></div><?php foreach($events as $event):$selectable=(bool)$event['selectable'];?><label class="si-row<?=$selectable?'':' blocked'?>"><span><input type="checkbox" name="event_keys[]" value="<?=$e((string)$event['key'])?>"<?=$selectable?' checked':' disabled'?>></span><span><b><?=$e((string)$event['title'])?></b><?php if(!empty($event['description'])):?><small><?=$e(mb_strimwidth(str_replace("\n",' ',(string)$event['description']),0,120,'…'))?></small><?php endif;?></span><span><b><?=$e(date('D M j, Y',strtotime((string)$event['starts_at'])))?></b><small><?=$event['all_day']?'All day':$e(date('g:i A',strtotime((string)$event['starts_at'])).'–'.date('g:i A',strtotime((string)$event['ends_at'])))?></small></span><span><?=$e((string)($event['location'] ?: ($preview['fallback_location'] ?: 'TBD')))?></span><span><?php if($event['duplicate']):?><strong>Duplicate</strong><?php elseif($event['cancelled']):?><strong>Cancelled</strong><?php elseif($event['recurring']):?><strong>Recurring rule</strong><small>Needs recurrence expansion</small><?php else:?><em>Ready</em><?php endif;?></span></label><?php endforeach;?></div><footer class="si-actions"><a href="<?=$url('/schedule')?>">Cancel</a><button class="button" type="submit"<?=$ready?'':' disabled'?>>Import selected events</button></footer></form><?php endif;?>
        </div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function flash(string $type, string $message): void { $_SESSION['schedule_import_flash'] = ['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
    private static function forbidden(string $basePath, array $user): never
    {
        http_response_code(403);$url=static fn(string $path):string=>($basePath?:'').$path;?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Restricted',$basePath);?><p style="padding:2rem">Schedule management permission is required to import a calendar.</p></main></div></body></html><?php exit;
    }
}
