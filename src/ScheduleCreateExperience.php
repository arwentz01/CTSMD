<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/ScheduleAudience.php';

final class ScheduleCreateExperience
{
    private const ROUTES = ['/production/schedule/new'];

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
        if (!AccessPolicy::canManageProduction($user)) self::forbidden($basePath, $user);

        $_SESSION['schedule_create_csrf'] ??= bin2hex(random_bytes(24));
        $production = ProductionContext::selected($db, $user);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') self::handlePost($db, $user, $production, $basePath);
        $groups = $production ? ScheduleAudience::groups($db, (int)$production['id']) : [];
        self::page($basePath, $user, $production, $groups);
    }

    private static function handlePost(PDO $db, array $user, ?array $production, string $basePath): never
    {
        if (!hash_equals((string)($_SESSION['schedule_create_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/production/schedule/new');
        }
        if (!$production) {
            self::flash('error', 'Select an active production before creating schedule items.');
            self::redirect($basePath . '/production');
        }
        try {
            $itemId = self::createScheduleItem($db, $user, $production, $_POST);
            $_SESSION['production_flash'] = ['type' => 'success', 'message' => 'Schedule item created in ' . $production['title'] . '. Review its audience below or return to the schedule.'];
            self::redirect($basePath . '/production/edit?id=' . $itemId);
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            self::redirect($basePath . '/production/schedule/new');
        }
    }

    private static function createScheduleItem(PDO $db, array $user, array $production, array $input): int
    {
        $selected = ProductionContext::selected($db, $user);
        if (!$selected || (int)$selected['id'] !== (int)$production['id']) throw new RuntimeException('The working production changed before this item was saved. Review the production selector and try again.');

        $title = trim((string)($input['title'] ?? ''));
        $location = trim((string)($input['location'] ?? ''));
        $itemType = trim((string)($input['item_type'] ?? 'rehearsal'));
        $visibility = (string)($input['visibility'] ?? 'all');
        $audienceMode = (string)($input['audience_mode'] ?? 'production');
        $groupIds = (array)($input['group_ids'] ?? []);
        $startsAt = self::parseLocalDateTime((string)($input['starts_at'] ?? ''), 'Start time');
        $endsAt = self::parseOptionalLocalDateTime((string)($input['ends_at'] ?? ''), 'End time');
        $familyCallAt = self::parseOptionalLocalDateTime((string)($input['family_call_at'] ?? ''), 'Family call');
        $prepareNotice = isset($input['prepare_notice']);

        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a schedule title no longer than 190 characters.');
        if ($location === '' || mb_strlen($location) > 190) throw new RuntimeException('Enter a location no longer than 190 characters.');
        if ($itemType === '' || mb_strlen($itemType) > 80) throw new RuntimeException('Enter an activity type no longer than 80 characters.');
        if (!in_array($visibility, ['family', 'staff', 'all'], true)) throw new RuntimeException('Choose a valid audience.');
        if (!in_array($audienceMode, ['production','groups'], true)) throw new RuntimeException('Choose whether this call is for the full production or specific groups.');
        if ($endsAt !== null && $endsAt <= $startsAt) throw new RuntimeException('The end time must be after the start time.');
        if ($familyCallAt !== null && $familyCallAt > $startsAt) throw new RuntimeException('Family call cannot be later than the activity start time.');

        $validatedGroups = $audienceMode === 'groups' ? ScheduleAudience::validateGroupIds($db, (int)$production['id'], $groupIds) : [];
        if ($audienceMode === 'groups' && !$validatedGroups) throw new RuntimeException('Choose at least one production group.');

        $db->beginTransaction();
        try {
            $insert = $db->prepare('INSERT INTO schedule_items (production_id,title,starts_at,ends_at,family_call_at,location,visibility,audience_mode,item_type) VALUES (:production_id,:title,:starts_at,:ends_at,:family_call_at,:location,:visibility,:audience_mode,:item_type)');
            $insert->execute([
                'production_id'=>(int)$production['id'],'title'=>$title,'starts_at'=>$startsAt->format('Y-m-d H:i:s'),'ends_at'=>$endsAt?->format('Y-m-d H:i:s'),'family_call_at'=>$familyCallAt?->format('Y-m-d H:i:s'),'location'=>$location,'visibility'=>$visibility,'audience_mode'=>$audienceMode,'item_type'=>$itemType,
            ]);
            $itemId=(int)$db->lastInsertId();
            ScheduleAudience::replaceItemGroups($db,$itemId,(int)$production['id'],$audienceMode,$validatedGroups);

            $audience=ScheduleAudience::audienceMembers($db,(int)$production['id'],$visibility,$audienceMode,$validatedGroups);
            $noticeId=null;
            if($prepareNotice){
                $notice=$db->prepare("INSERT INTO schedule_change_notices (schedule_item_id,production_id,created_by_user_id,audience_scope,audience_count,subject,body,status) VALUES (:item,:production,:actor,:scope,:count,:subject,:body,'draft')");
                $notice->execute(['item'=>$itemId,'production'=>(int)$production['id'],'actor'=>(int)$user['id'],'scope'=>$visibility,'count'=>count($audience),'subject'=>'New schedule item · '.$title,'body'=>self::communicationCopy(['title'=>$title,'starts_at'=>$startsAt->format('Y-m-d H:i:s'),'family_call_at'=>$familyCallAt?->format('Y-m-d H:i:s'),'location'=>$location])]);
                $noticeId=(int)$db->lastInsertId();
            }
            self::audit($db,(int)$user['id'],'schedule.created',$itemId,[
                'production_id'=>(int)$production['id'],'title'=>$title,'starts_at'=>$startsAt->format('Y-m-d H:i:s'),'ends_at'=>$endsAt?->format('Y-m-d H:i:s'),'family_call_at'=>$familyCallAt?->format('Y-m-d H:i:s'),'location'=>$location,'visibility'=>$visibility,'audience_mode'=>$audienceMode,'group_ids'=>$validatedGroups,'item_type'=>$itemType,'communication_draft_id'=>$noticeId,'audience_user_ids'=>array_map(static fn(array $row):int=>(int)$row['id'],$audience),
            ]);
            $db->commit(); return $itemId;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            if($e instanceof RuntimeException)throw $e;
            throw new RuntimeException('The schedule item could not be created.');
        }
    }

    private static function communicationCopy(array $item): string
    {
        $start=new DateTimeImmutable($item['starts_at']);
        $parts=['A new production schedule item has been added: '.$item['title'].'.',$start->format('l, F j \\a\\t g:i A').' at '.$item['location'].'.'];
        if(!empty($item['family_call_at']))$parts[]='Family call: '.(new DateTimeImmutable($item['family_call_at']))->format('g:i A').'.';
        $parts[]='Please review the selected production schedule in CTSMD Connect for the current details.';
        return implode(' ',$parts);
    }

    private static function audit(PDO $db,int $actor,string $event,int $itemId,array $metadata):void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,\'schedule_item\',:id,\'Created production schedule item.\',:metadata)');
        $stmt->execute(['actor'=>$actor,'event'=>$event,'id'=>$itemId,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    private static function parseLocalDateTime(string $value,string $label):DateTimeImmutable
    {
        $date=DateTimeImmutable::createFromFormat('Y-m-d\\TH:i',trim($value)); if(!$date)throw new RuntimeException($label.' is required.'); return $date;
    }
    private static function parseOptionalLocalDateTime(string $value,string $label):?DateTimeImmutable
    {
        if(trim($value)==='')return null; $date=DateTimeImmutable::createFromFormat('Y-m-d\\TH:i',trim($value)); if(!$date)throw new RuntimeException($label.' is not a valid date and time.'); return $date;
    }

    private static function page(string $basePath,array $user,?array $production,array $groups):never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path; $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $flash=$_SESSION['schedule_create_flash']??$_SESSION['production_context_flash']??null; unset($_SESSION['schedule_create_flash'],$_SESSION['production_context_flash']);
        $defaultStart=(new DateTimeImmutable('tomorrow 18:00'))->format('Y-m-d\\TH:i'); $defaultEnd=(new DateTimeImmutable('tomorrow 20:30'))->format('Y-m-d\\TH:i');
        header('Content-Type: text/html; charset=utf-8');?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>New schedule item · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/schedule-create.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production/schedule/new',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','New schedule item',$basePath,[['label'=>'Overview','href'=>'/production','active'=>false],['label'=>'Schedule','href'=>'/schedule','active'=>true],['label'=>'Groups','href'=>'/production/groups','active'=>false],['label'=>'Resources','href'=>'/resources','active'=>false],['label'=>'Playbill','href'=>'/playbills','active'=>false]]);?><div class="sc-page">
<?php if($flash):?><div class="sc-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
<?php if(!$production):?><section class="sc-empty"><small>NO ACTIVE PRODUCTION</small><h2>Select the production context first.</h2><p>Schedule items belong to one active production. Choose the show workspace before building its schedule.</p><a class="button" href="<?= $url('/production') ?>">Choose production</a></section>
<?php else:?><section class="sc-hero"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Add something to the callboard.</h2><p>Call the whole production or target the exact cast/crew groups needed for this activity.</p></div><a href="<?= $url('/schedule') ?>">← Back to schedule</a></section>
<div class="sc-layout"><form class="sc-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['schedule_create_csrf']) ?>"><label>Title<input name="title" maxlength="190" required placeholder="Ensemble Rehearsal"></label>
<div class="sc-pair"><label>Activity type<select name="item_type"><option value="rehearsal">Rehearsal</option><option value="performance">Performance</option><option value="meeting">Meeting</option><option value="orientation">Orientation</option><option value="volunteer">Volunteer activity</option><option value="call">Call / check-in</option><option value="other">Other</option></select></label><label>People included<select name="visibility"><option value="all">Participants + staff/guardians as targeted</option><option value="family">Students + their guardians</option><option value="staff">Staff only</option></select></label></div>
<fieldset class="sc-target"><legend>Who is this for?</legend><label class="sc-radio"><input type="radio" name="audience_mode" value="production" checked><span><b>Whole production</b><small>Uses the broad audience choice above.</small></span></label><label class="sc-radio"><input type="radio" name="audience_mode" value="groups"><span><b>Specific production groups</b><small>Only members of the selected groups are called. Student guardians inherit family-facing calls.</small></span></label><div class="sc-groups"><?php if($groups):foreach($groups as $group):?><label><input type="checkbox" name="group_ids[]" value="<?= (int)$group['id'] ?>"><span><b><?= $esc($group['name']) ?></b><small><?= (int)$group['member_count'] ?> active members · <?= $esc(ucfirst($group['group_type'])) ?></small></span></label><?php endforeach;else:?><p>No groups exist yet. <a href="<?= $url('/production/groups') ?>">Create production groups →</a></p><?php endif;?></div></fieldset>
<div class="sc-pair"><label>Start<input type="datetime-local" name="starts_at" required value="<?= $esc($defaultStart) ?>"></label><label>End<input type="datetime-local" name="ends_at" value="<?= $esc($defaultEnd) ?>"></label></div><div class="sc-pair"><label>Family call <span>optional</span><input type="datetime-local" name="family_call_at"></label><label>Location<input name="location" maxlength="190" required placeholder="Main Stage"></label></div>
<label class="sc-check"><input type="checkbox" name="prepare_notice" value="1" checked><span><b>Prepare a communication draft</b><small>The draft uses the same resolved production/group audience. Nothing is sent automatically.</small></span></label><footer><a href="<?= $url('/schedule') ?>">Cancel</a><button class="button" type="submit">Create schedule item</button></footer></form>
<aside class="sc-side"><small>TARGETED CALLS</small><h3>One schedule, less noise.</h3><ol><li>Create reusable groups like Full Cast, Ensemble, Principals or Tech Crew.</li><li>Choose one or several groups for a rehearsal.</li><li>Students see their group calls; their active guardians inherit family-facing calls.</li><li>Communication drafts resolve from that same audience.</li></ol></aside></div><?php endif;?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(string $basePath,array $user):never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;http_response_code(403);?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Restricted',$basePath);?><div class="sc-page"><section class="sc-empty"><b>Staff only</b><p>Your current role cannot create production schedule items.</p><a class="button" href="<?= $url('/schedule') ?>">View schedule</a></section></div></main></div></body></html><?php exit;
    }
    private static function flash(string $type,string $message):void{$_SESSION['schedule_create_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
