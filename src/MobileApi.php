<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/CommunicationReadStateService.php';
require_once __DIR__ . '/SafeguardedConversationPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/RehearsalVideoService.php';
require_once __DIR__ . '/ProductionNotificationAccess.php';
require_once __DIR__ . '/CastingCommunicationService.php';
require_once __DIR__ . '/CastingOfferService.php';
require_once __DIR__ . '/ProductionChecklistAssignmentService.php';
require_once __DIR__.'/LatePickupService.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/CalendarService.php';
require_once __DIR__ . '/VolunteerCoverageService.php';
require_once __DIR__ . '/PushService.php';
require_once __DIR__ . '/CommunicationExperience.php';
require_once __DIR__ . '/CommunityExperience.php';
require_once __DIR__ . '/FormExperience.php';
require_once __DIR__ . '/VolunteerExperience.php';
require_once __DIR__ . '/VolunteerApprovalExperience.php';

final class MobileApi
{
    private const TOKEN_TTL_DAYS = 30;

    public static function handles(string $route): bool
    {
        return str_starts_with($route, '/api/mobile/');
    }

    public static function render(string $route): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $db = Database::connect(dirname(__DIR__));
            if ($route === '/api/mobile/login') self::login($db);
            $user = self::bearerUser($db);
            if (!$user) self::json(['error' => 'Authentication required.'], 401);

            // A caregiver-only identity cannot inherit general guardian mobile
            // endpoints. Serve only a scoped family logistics contract.
            $roleCheck=$db->prepare("SELECT relationship_type FROM family_relationships WHERE guardian_user_id=:id AND status='active'");
            $roleCheck->execute(['id'=>(int)$user['id']]);$familyTypes=$roleCheck->fetchAll(PDO::FETCH_COLUMN);
            $caregiverOnly=in_array('caregiver',$familyTypes,true) && !array_intersect($familyTypes,['parent','guardian']);
            if($caregiverOnly && !in_array($route,['/api/mobile/me','/api/mobile/my-family','/api/mobile/caregiver-schedule','/api/mobile/logout'],true)){
                self::json(['error'=>'Caregiver accounts are limited to family logistics on mobile.'],403);
            }
            // Mirror the web membership gate: a pending account without an active
            // production must not gain access to organization-wide video content.
            if(in_array($route,['/api/mobile/videos','/api/mobile/video-play'],true)
               &&!Auth::isApprovedMember($user)
               &&!ProductionContext::activeProductions($db,$user)){
                self::json(['error'=>'Video Library access requires approved membership or active production participation.'],403);
            }
            match ($route) {
                '/api/mobile/me' => self::json(['user' => self::publicUser($user)]),
                '/api/mobile/my-family' => self::myFamily($db, $user),
                '/api/mobile/caregiver-schedule' => self::caregiverSchedule($db, $user),
                '/api/mobile/theatre' => self::theatre($db, $user),
                '/api/mobile/videos' => self::videos($db,$user),
                '/api/mobile/video-play' => self::videoPlay($db,$user),
                '/api/mobile/production-casting' => self::productionCasting($db,$user),
                '/api/mobile/production-artwork' => self::productionArtwork($db,$user),
                '/api/mobile/event' => self::eventDetail($db, $user),
                '/api/mobile/daily-tasks' => self::dailyTasks($db, $user),
                '/api/mobile/casting-offer-respond' => self::castingOfferRespond($db,$user),
                '/api/mobile/production-todo-complete' => self::productionTodoComplete($db,$user),
                '/api/mobile/volunteer-signup' => self::volunteerSignup($db, $user),
                '/api/mobile/volunteer-cancel' => self::volunteerCancel($db, $user),
                '/api/mobile/volunteer-approval' => self::volunteerApproval($db, $user),
                '/api/mobile/form-detail' => self::formDetail($db, $user),
                '/api/mobile/form-submit' => self::formSubmit($db, $user),
                '/api/mobile/notifications' => self::notifications($db, $user),
                '/api/mobile/notification-read' => self::notificationRead($db, $user),
                '/api/mobile/messages' => self::messages($db, $user),
                '/api/mobile/message-thread' => self::messageThread($db, $user),
                '/api/mobile/message-send' => self::messageSend($db, $user),
                '/api/mobile/message-recipients' => self::messageRecipients($db, $user),
                '/api/mobile/message-create' => self::messageCreate($db, $user),
                '/api/mobile/channels' => self::channels($db, $user),
                '/api/mobile/community-feed' => self::communityFeed($db, $user),
                '/api/mobile/channel' => self::channel($db, $user),
                '/api/mobile/channel-post' => self::channelPost($db, $user),
                '/api/mobile/channel-replies' => self::channelReplies($db, $user),
                '/api/mobile/channel-reaction' => self::channelReaction($db, $user),
                '/api/mobile/community-photo' => self::communityPhoto($db, $user),
                '/api/mobile/push-subscription' => self::pushSubscription($db, $user),
                '/api/mobile/logout' => self::logout($db),
                default => self::json(['error' => 'Mobile API route not found.'], 404),
            };
        } catch (PDOException $e) {
            error_log('Mobile API database error: ' . $e->getMessage());
            self::json(['error' => 'The CTSMD service could not complete that request.'], 500);
        } catch (RateLimitExceeded $e) {
            header('Retry-After: '.$e->retryAfter);self::json(['error'=>$e->getMessage(),'retry_after'=>$e->retryAfter],429);
        } catch (RuntimeException $e) {
            self::json(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('Mobile API error: ' . $e->getMessage());
            self::json(['error' => 'The CTSMD service could not complete that request.'], 500);
        }
    }

    private static function videos(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $available=ProductionContext::activeProductions($db,$user);
        $byId=[];
        foreach(array_merge([['id'=>0]],$available) as $production){
            foreach(RehearsalVideoService::visible($db,$user,(int)$production['id']) as $item){
                if($item['status']!=='active')continue;
                $byId[(int)$item['id']]=[
                    'id'=>(int)$item['id'],
                    'title'=>(string)$item['title'],
                    'description'=>$item['description'],
                    'category'=>(string)$item['category'],
                    'audience_scope'=>(string)$item['audience_scope'],
                    'production_id'=>$item['production_id']===null?null:(int)$item['production_id'],
                    'group_name'=>$item['group_name'],
                    'byte_size'=>(int)$item['byte_size'],
                    'created_at'=>$item['created_at'],
                ];
            }
        }
        usort($byId,static fn(array $a,array $b):int=>strcmp((string)$b['created_at'],(string)$a['created_at']));
        self::json(['videos'=>array_values($byId)]);
    }

    private static function videoPlay(PDO $db,array $user):never
    {
        if(!in_array((string)($_SERVER['REQUEST_METHOD']??'GET'),['GET','HEAD'],true))self::json(['error'=>'Method not allowed.'],405);
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $video=RehearsalVideoService::record($db,(int)$id);
        if(!$video||$video['status']!=='active'){self::json(['error'=>'Video unavailable.'],404);}
        $scope=(string)($video['audience_scope']??'production');
        if($scope!=='platform'){
            $available=ProductionContext::activeProductions($db,$user);
            $found=false;
            foreach($available as $production)if((int)$production['id']===(int)$video['production_id']){$found=true;break;}
            if(!$found){self::json(['error'=>'Video unavailable.'],404);}
        }
        if(!RehearsalVideoService::canView($db,$user,$video)&&!RehearsalVideoService::canManage($user)){
            self::json(['error'=>'Video unavailable.'],404);
        }
        header_remove('Content-Type');
        RehearsalVideoService::stream($video);
    }

    private static function caregiverSchedule(PDO $db,array $user):never {
        self::requireMethod('GET');
        $q=$db->prepare("SELECT u.id,u.first_name,u.last_name FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE fr.guardian_user_id=:adult AND fr.status='active' AND fr.relationship_type='caregiver'");
        $q->execute(['adult'=>(int)$user['id']]);$children=$q->fetchAll();
        $from=new DateTimeImmutable('now');$until=$from->modify('+45 days');$calls=[];
        foreach($children as $child){
            $sid=(int)$child['id'];$q=$db->prepare("SELECT id,first_name,last_name,initials,display_role role,active FROM users WHERE id=:id");$q->execute(['id'=>$sid]);$student=$q->fetch();
            if(!$student)continue;
            $student['roles']=Auth::roles($db,$sid);$student['permissions']=Auth::permissions($db,$sid);
            foreach(CalendarService::visibleEvents($db,$student,$from,$until) as $event){
                if(($event['status']??'active')==='cancelled')continue;
                $calls[]=['student_first_name'=>$child['first_name'],'production_title'=>$event['production_title'],'title'=>$event['title'],'starts_at'=>$event['starts_at'],'ends_at'=>$event['ends_at'],'location'=>$event['location']];
            }
        }
        usort($calls,static fn(array $a,array $b):int=>strcmp((string)$a['starts_at'],(string)$b['starts_at']));
        self::json(['calls'=>$calls]);
    }

    private static function myFamily(PDO $db,array $user): never
    {
        self::requireMethod('GET');
        $id=(int)$user['id'];
        // Only current, active family relationships. No organization-wide roster exposure.
        $students=$db->prepare("SELECT u.id,u.first_name,CONCAT(u.first_name,' ',u.last_name) name,fr.is_primary,fr.relationship_type
          FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status='active'
          WHERE fr.guardian_user_id=:id AND fr.status='active'
          ORDER BY u.first_name,u.last_name,u.id");
        $students->execute(['id'=>$id]);
        $guardians=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,fr.is_primary
          FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status='active'
          WHERE fr.student_user_id=:id AND fr.status='active'
          ORDER BY fr.is_primary DESC,u.first_name,u.last_name,u.id");
        $guardians->execute(['id'=>$id]);
        $studentRows=$students->fetchAll();
        // For caregiver relationships expose only the minimum needed to label
        // the logistics page; never include full student names or account IDs.
        foreach($studentRows as &$child){
            if($child['relationship_type']==='caregiver'){
                $child=['first_name'=>(string)$child['first_name'],'relationship_type'=>'caregiver'];
            }
        }
        unset($child);
        self::json(['students'=>$studentRows,'guardians'=>$guardians->fetchAll()]);
    }

    private static function login(PDO $db): never
    {
        self::requireMethod('POST');
        $input = self::input();
        $email = mb_strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        if ($email === '' || $password === '') throw new RuntimeException('Enter your email and password.');
        $rateSubject=RateLimiter::requestSubject($email);RateLimiter::enforce($db,'mobile-login',$rateSubject,8,900,900);

        $stmt = $db->prepare("SELECT id,password_hash,active,account_status FROM users WHERE LOWER(email)=:email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!$row || !(bool)$row['active'] || $row['account_status'] !== 'active' || empty($row['password_hash']) || !password_verify($password, (string)$row['password_hash'])) {
            self::json(['error' => 'Email or password was not recognized.'], 401);
        }

        $userId = (int)$row['id'];
        RateLimiter::clear($db,'mobile-login',$rateSubject);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = (new DateTimeImmutable('+' . self::TOKEN_TTL_DAYS . ' days'))->format('Y-m-d H:i:s');
        $db->prepare("DELETE FROM auth_mobile_tokens WHERE user_id=:user AND (expires_at<CURRENT_TIMESTAMP OR revoked_at IS NOT NULL)")->execute(['user' => $userId]);
        $db->prepare("INSERT INTO auth_mobile_tokens (user_id,token_hash,expires_at,created_at,last_used_at) VALUES (:user,:hash,:expires,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute(['user'=>$userId,'hash'=>$hash,'expires'=>$expires]);
        $db->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$userId]);
        $user = self::loadUser($db, $userId);
        self::json(['token'=>$token,'expires_at'=>$expires,'user'=>self::publicUser($user)]);
    }

    private static function productionArtwork(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $productionId=(int)($_GET['production_id']??0);
        $role=(string)($_GET['role']??'banner');
        if(!in_array($role,['banner','poster','thumbnail'],true))self::json(['error'=>'Image not found.'],404);
        $allowed=false;
        foreach(ProductionContext::activeProductions($db,$user) as $p){if((int)$p['id']===$productionId){$allowed=true;break;}}
        if(!$allowed)self::json(['error'=>'Image not found.'],404);
        $s=$db->prepare('SELECT sfv.* FROM production_artwork pa JOIN stored_file_versions sfv ON sfv.stored_file_id=pa.stored_file_id WHERE pa.production_id=:id AND pa.role=:role ORDER BY sfv.version_number DESC LIMIT 1');
        $s->execute(['id'=>$productionId,'role'=>$role]);$image=$s->fetch();
        if(!$image&&$role==='banner'){$s->execute(['id'=>$productionId,'role'=>'poster']);$image=$s->fetch();}
        if(!$image||!in_array($image['mime_type'],['image/jpeg','image/png','image/webp'],true))self::json(['error'=>'Image not found.'],404);
        header('Cache-Control: private, no-store');
        header('Vary: Authorization');
        StorageService::stream(dirname(__DIR__),$image,false);
    }

    private static function productionCasting(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $id=(int)($_GET['production_id']??0);
        $allowed=false;
        foreach(ProductionContext::activeProductions($db,$user) as $p){if((int)$p['id']===$id){$allowed=true;break;}}
        if(!$allowed)self::json(['error'=>'Production unavailable.'],404);
        self::json([
            'callbacks'=>CastingCommunicationService::publishedCallbacksForProduction($db,$id),
            'cast'=>CastingCommunicationService::publishedCastForProduction($db,$id)
        ]);
    }

    private static function theatre(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        // Reuse the web platform's audience filters. Never expose an unrestricted
        // schedule or rely on filtering events in the mobile client.
        $productions=ProductionContext::activeProductions($db,$user);
        // Discoverable summaries are deliberately narrow. They never inherit
        // schedules, membership, casting drafts, or private artwork permissions.
        $known=array_fill_keys(array_map(static fn(array $p):int=>(int)$p['id'],$productions),true);
        $announcements=$db->query("SELECT id,title,season,lifecycle_stage,audition_starts_at,audition_ends_at,performance_starts_at,performance_ends_at FROM productions WHERE announcement_published_at IS NOT NULL AND lifecycle_stage IN ('upcoming','planning','in_production','performing') ORDER BY id DESC")->fetchAll();
        foreach($announcements as $row){
            if(isset($known[(int)$row['id']]))continue;
            $productions[]=['id'=>(int)$row['id'],'title'=>$row['title'],'season'=>$row['season'],
                'status'=>$row['lifecycle_stage'],'lifecycle_stage'=>$row['lifecycle_stage'],
                'is_active'=>0,'has_artwork'=>false,'is_public_preview'=>true,
                'audition_starts_at'=>$row['audition_starts_at'],'audition_ends_at'=>$row['audition_ends_at'],
                'performance_starts_at'=>$row['performance_starts_at'],'performance_ends_at'=>$row['performance_ends_at']];
        }
        $events=CalendarService::visibleEvents(
            $db,$user,new DateTimeImmutable('-1 day'),new DateTimeImmutable('+90 days')
        );
        if($productions){
            $ids=array_map(static fn(array $p):int=>(int)$p['id'],$productions);
            $placeholders=implode(',',array_fill(0,count($ids),'?'));
            $q=$db->prepare("SELECT production_id,role FROM production_artwork WHERE production_id IN ($placeholders)");
            $q->execute($ids);$art=[];
            foreach($q->fetchAll() as $row)$art[(int)$row['production_id']][(string)$row['role']]=true;
            foreach($productions as &$p)$p['has_artwork']=empty($p['is_public_preview'])&&(!empty($art[(int)$p['id']]['banner'])||!empty($art[(int)$p['id']]['poster']));
            unset($p);
        }
        self::json(['productions'=>$productions,'events'=>array_slice($events,0,100)]);
    }

    private static function eventDetail(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if(!$id||$id<1)self::json(['error'=>'An event ID is required.'],400);
        // Narrow the existing audience-filtered calendar query to this event's date.
        // A known event ID alone never gives access to a restricted production event.
        $s=$db->prepare("SELECT starts_at FROM schedule_items WHERE id=:id LIMIT 1");
        $s->execute(['id'=>$id]);$start=$s->fetchColumn();
        if(!$start)self::json(['error'=>'Event not found.'],404);
        $day=new DateTimeImmutable((string)$start);
        $events=CalendarService::visibleEvents($db,$user,$day->modify('-1 day'),$day->modify('+1 day'));
        foreach($events as $event){
            if((int)$event['id']===$id)self::json(['event'=>$event]);
        }
        self::json(['error'=>'Event not found.'],404);
    }

    private static function volunteerApproval(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $action=(string)($input['action']??'');
        if($action==='request'){
            $id=filter_var($input['shift_id']??null,FILTER_VALIDATE_INT);
            if(!$id||$id<1)self::json(['error'=>'A valid volunteer shift is required.'],400);
            $note=$input['note']??'';
            if(!is_string($note))self::json(['error'=>'Invalid request note.'],400);
            VolunteerApprovalExperience::requestApproval($db,$user,(int)$id,trim($note));
        }elseif($action==='withdraw'){
            $id=filter_var($input['request_id']??null,FILTER_VALIDATE_INT);
            if(!$id||$id<1)self::json(['error'=>'A valid approval request is required.'],400);
            VolunteerApprovalExperience::withdrawRequest($db,$user,(int)$id);
        }else self::json(['error'=>'Unsupported approval action.'],400);
        self::json(['ok'=>true]);
    }

    private static function volunteerCancel(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $shift=filter_var($input['shift_id']??null,FILTER_VALIDATE_INT);
        if(!$shift||$shift<1)self::json(['error'=>'A valid volunteer shift is required.'],400);
        // Shared web cancellation guard rejects started shifts, checked-in
        // attendance, completed service and other users' commitments.
        VolunteerExperience::cancelSignup($db,(int)$user['id'],(int)$shift);
        self::json(['ok'=>true]);
    }

    private static function volunteerSignup(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $shift=filter_var($input['shift_id']??null,FILTER_VALIDATE_INT);
        if(!$shift||$shift<1)self::json(['error'=>'A valid volunteer shift is required.'],400);
        // Reuse the web signup transaction: requirements, active volunteer status,
        // approval gate, shift start, live capacity and duplicate signup checks.
        VolunteerExperience::signup($db,(int)$user['id'],(int)$shift);
        self::json(['ok'=>true]);
    }

    private static function formSubmit(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $id=filter_var($input['assignment_id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)self::json(['error'=>'A valid form assignment is required.'],400);
        // The web and mobile entry points use the exact same transaction,
        // assignee check, completion rules, review workflow and audit trail.
        $payload=[];
        if(($input['acknowledgment']??false)===true)$payload['acknowledgment']='1';
        $payload['typed_signature']=is_string($input['typed_signature']??null)?$input['typed_signature']:'';
        $payload['response_text']=is_string($input['response_text']??null)?$input['response_text']:'';
        FormExperience::submit($db,$user,(int)$id,$payload);
        self::json(['ok'=>true]);
    }

    private static function formDetail(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if(!$id||$id<1) self::json(['error'=>'A form assignment ID is required.'],400);
        // Match the web Forms page: only the authenticated assignee can read
        // an active assignment. Do not expose arbitrary form definitions.
        $s=$db->prepare("SELECT fa.id,fa.status,fa.due_at,fa.completed_at,f.title,f.form_type,f.instructions,f.completion_mode,f.review_required,fs.status AS submission_status,fs.submitted_at,fs.reviewed_at,fs.reviewer_note FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 LEFT JOIN form_submissions fs ON fs.assignment_id=fa.id WHERE fa.id=:id AND fa.user_id=:user LIMIT 1");
        $s->execute(['id'=>$id,'user'=>(int)$user['id']]);
        $assignment=$s->fetch(PDO::FETCH_ASSOC);
        if(!$assignment) self::json(['error'=>'This form is not available to your account.'],404);
        self::json(['form'=>$assignment]);
    }

    private static function dailyTasks(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id=(int)$user['id'];
        // Same assignee + active form boundary used by the member Forms page.
        $forms=$db->prepare("SELECT fa.id,fa.status,fa.due_at,f.title,fs.status submission_status FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 LEFT JOIN form_submissions fs ON fs.assignment_id=fa.id WHERE fa.user_id=:user ORDER BY fa.due_at,fa.id LIMIT 100");
        $forms->execute(['user'=>$id]);
        $live=VolunteerCoverageService::isLiveVolunteer($db,$id);
        $rows=[];
        if($live){
        $shifts=$db->prepare("SELECT vs.id,vs.title,vs.category,vs.starts_at,vs.ends_at,vs.location,vs.required_slots,vs.approval_required,mine.status my_status,req.id my_request_id,req.status my_request_status FROM volunteer_shifts vs LEFT JOIN volunteer_shift_signups mine ON mine.shift_id=vs.id AND mine.user_id=:mine LEFT JOIN volunteer_shift_approval_requests req ON req.shift_id=vs.id AND req.user_id=:request WHERE vs.starts_at>NOW() AND (vs.production_id IS NULL OR EXISTS(SELECT 1 FROM productions p WHERE p.id=vs.production_id AND p.is_active=1)) ORDER BY vs.starts_at,vs.id LIMIT 100");
        $shifts->execute(['mine'=>$id,'request'=>$id]);
        $rows=$shifts->fetchAll();
        $missingMap=VolunteerCoverageService::missingRequirementsForShifts($db,$id,array_column($rows,'id'));
        foreach($rows as &$row){
            $row['missing_requirements']=$missingMap[(int)$row['id']]??[];
            $row['eligible']=$live && !$row['missing_requirements'];
            $row['open_slots']=max(0,(int)$row['required_slots']-VolunteerCoverageService::eligibleLiveSignupCount($db,(int)$row['id']));
        }
        unset($row);
        }
        self::json(['forms'=>$forms->fetchAll(),'shifts'=>$rows,'volunteer_enabled'=>$live,
            'production_todos'=>ProductionChecklistAssignmentService::actionItems($db,$id),
            'casting_offers'=>CastingOfferService::pendingForUser($db,$id),
            'late_pickup_notices'=>LatePickupService::familyNotices($db,$id)]);
    }

    private static function castingOfferRespond(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        CastingOfferService::respond($db,(int)$user['id'],(int)($input['offer_id']??0),(string)($input['decision']??''),(string)($input['response_note']??''));
        self::json(['ok'=>true]);
    }

    private static function productionTodoComplete(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        ProductionChecklistAssignmentService::complete($db,(int)$user['id'],(int)($input['assignment_id']??0));
        self::json(['ok'=>true]);
    }

    private static function notifications(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $s=$db->prepare("SELECT id,source_type,source_id,title,body,action_path,read_at,created_at FROM app_notifications WHERE recipient_user_id=:user ORDER BY created_at DESC,id DESC LIMIT 250");
        $s->execute(['user'=>(int)$user['id']]);
        $items=array_slice(ProductionNotificationAccess::filter($db,(int)$user['id'],$s->fetchAll()),0,75);
        // Navigation is a hint only: target pages still enforce their own authorization.
        // Never let stored notification paths navigate outside the CTSMD site.
        foreach($items as &$item){
            $path=(string)($item['action_path']??'');
            if(!preg_match('~^/(?!/)[a-zA-Z0-9/_?=&%-]*$~D',$path))$item['action_path']=null;
        }
        unset($item);
        self::json(['notifications'=>$items,'unread_count'=>count(array_filter($items,static fn(array $n):bool=>empty($n['read_at'])))]);
    }

    private static function notificationRead(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();$id=(int)($input['notification_id']??0);
        if($id<1)throw new RuntimeException('A notification is required.');
        $check=$db->prepare("SELECT source_type,source_id FROM app_notifications WHERE id=:id AND recipient_user_id=:user LIMIT 1");
        $check->execute(['id'=>$id,'user'=>(int)$user['id']]);
        $record=$check->fetch(PDO::FETCH_ASSOC);
        if(!$record||!ProductionNotificationAccess::visible($db,(int)$user['id'],$record))self::json(['error'=>'Notification unavailable.'],404);
        $s=$db->prepare("UPDATE app_notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id=:id AND recipient_user_id=:user");
        $s->execute(['id'=>$id,'user'=>(int)$user['id']]);
        self::json(['ok'=>true]);
    }

    private static function messages(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $stmt = $db->prepare("SELECT c.id,c.subject,c.conversation_type,MAX(m.created_at) latest_at,COUNT(DISTINCT m.id) message_count,COUNT(DISTINCT cp.user_id) participant_count,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.hidden_at IS NULL AND um.id>mine.last_read_message_id AND um.sender_user_id<>mine.user_id AND (mine.history_through_message_id IS NULL OR um.id<=mine.history_through_message_id)) unread_count,(SELECT sm.body FROM messages sm WHERE sm.conversation_id=c.id AND sm.hidden_at IS NULL AND (mine.history_through_message_id IS NULL OR sm.id<=mine.history_through_message_id) ORDER BY sm.id DESC LIMIT 1) preview FROM conversations c JOIN conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=:user JOIN conversation_participants cp ON cp.conversation_id=c.id LEFT JOIN messages m ON m.conversation_id=c.id AND m.hidden_at IS NULL AND (mine.history_through_message_id IS NULL OR m.id<=mine.history_through_message_id) GROUP BY c.id,c.subject,c.conversation_type,mine.last_read_message_id,mine.user_id,mine.history_through_message_id ORDER BY latest_at DESC,c.id DESC");
        $stmt->execute(['user'=>(int)$user['id']]);
        $rows=$stmt->fetchAll();$names=$db->prepare("SELECT cp.user_id,cp.participant_role,CONCAT(u.first_name,' ',u.last_name) name FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id");foreach($rows as &$row){$names->execute(['id'=>$row['id']]);$people=$names->fetchAll();$visible=array_values(array_map(static fn(array $p):string=>(string)$p['name'],array_filter($people,static fn(array $p):bool=>(int)$p['user_id']!==(int)$user['id']&&$p['participant_role']!=='guardian')));$row['display_name']=$row['conversation_type']==='group'?($row['subject']?:'Group conversation'):(implode(', ',$visible)?:$row['subject']);}unset($row);self::json(['conversations'=>$rows]);
    }

    private static function messageThread(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id = (int)($_GET['id'] ?? 0);
        if ($id < 1) throw new RuntimeException('Conversation id is required.');
        $access = $db->prepare("SELECT c.id,c.subject,c.conversation_type,cp.history_through_message_id,cp.access_revoked_at FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=:user WHERE c.id=:id LIMIT 1");
        $access->execute(['user'=>(int)$user['id'],'id'=>$id]);
        $conversation = $access->fetch();
        if (!$conversation) self::json(['error'=>'Conversation not found.'],404);

        $p=$db->prepare("SELECT cp.user_id,cp.participant_role,cp.guardian_required,cp.last_read_message_id,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role,u.initials FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id ORDER BY cp.user_id");
        $p->execute(['id'=>$id]);
        $m=$db->prepare("SELECT m.id,m.body,m.created_at,m.sender_user_id,CONCAT(u.first_name,' ',u.last_name) sender,u.display_role sender_role,u.initials FROM messages m JOIN users u ON u.id=m.sender_user_id WHERE m.conversation_id=:id AND m.hidden_at IS NULL AND (:cutoff IS NULL OR m.id<=:cutoff2) ORDER BY m.created_at,m.id");
        $m->execute(['id'=>$id,'cutoff'=>$conversation['history_through_message_id'],'cutoff2'=>$conversation['history_through_message_id']]);
        CommunicationReadStateService::markConversationRead($db,(int)$user['id'],$id);
        $people=$p->fetchAll();$visible=array_values(array_map(static fn(array $person):string=>(string)$person['name'],array_filter($people,static fn(array $person):bool=>(int)$person['user_id']!==(int)$user['id']&&$person['participant_role']!=='guardian')));$conversation['display_name']=$conversation['conversation_type']==='group'?($conversation['subject']?:'Group conversation'):(implode(', ',$visible)?:$conversation['subject']);self::json(['conversation'=>$conversation,'participants'=>$people,'messages'=>$m->fetchAll()]);
    }

    private static function messageRecipients(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        self::json(['recipients'=>CommunicationExperience::mobileRecipients($db,$user)]);
    }

    private static function messageCreate(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $recipientId=(int)($input['recipient_id']??0);
        $subject=''; // Direct messages are identified by participants, not a user-entered subject.
        $body=trim((string)($input['body']??''));
        $id=CommunicationExperience::mobileCreateConversation($db,$user,$recipientId,$subject,$body);
        self::json(['ok'=>true,'conversation_id'=>$id]);
    }

    private static function messageSend(PDO $db, array $user): never
    {
        self::requireMethod('POST');
        $input=self::input();$id=(int)($input['conversation_id']??0);$body=trim((string)($input['body']??''));
        if($id<1||$body===''||mb_strlen($body)>4000)throw new RuntimeException('Choose a conversation and write a message up to 4,000 characters.');
        $db->beginTransaction();
        try{
            SafeguardedConversationPolicy::enforceForSend($db,$id,(int)$user['id']);
            $insert=$db->prepare("INSERT INTO messages (conversation_id,sender_user_id,body,created_at) VALUES (:conversation,:sender,:body,CURRENT_TIMESTAMP)");
            $insert->execute(['conversation'=>$id,'sender'=>(int)$user['id'],'body'=>$body]);
            $messageId=(int)$db->lastInsertId();
            CommunicationReadStateService::markConversationThroughMessage($db,(int)$user['id'],$id,$messageId);
            $db->commit();
            self::json(['ok'=>true,'message_id'=>$messageId]);
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e instanceof RuntimeException?$e:new RuntimeException('We could not send that message. Please try again.');
        }
    }

    private static function channels(PDO $db,array $user): never
    {
        self::requireMethod('GET');
        $rows=$db->query("SELECT c.id,c.name,c.description,c.channel_type,c.production_id,p.title production_title FROM channels c LEFT JOIN productions p ON p.id=c.production_id WHERE c.archived_at IS NULL ORDER BY COALESCE(p.title,''),c.name")->fetchAll();
        $unread=CommunicationReadStateService::communityUnread($db,$user);$visible=[];
        foreach($rows as $row){if(!CommunicationReadStateService::canAccessChannel($db,$user,(int)$row['id']))continue;$row['unread_count']=(int)($unread['channels'][(int)$row['id']]??0);$visible[]=$row;}
        self::json(['channels'=>$visible]);
    }

    private static function communityFeed(PDO $db,array $user): never
    {
        self::requireMethod('GET');
        $channels=$db->query("SELECT id FROM channels WHERE archived_at IS NULL AND include_in_feed=1")->fetchAll(PDO::FETCH_COLUMN);
        $allowed=[];
        foreach($channels as $id){if(CommunicationReadStateService::canAccessChannel($db,$user,(int)$id))$allowed[]=(int)$id;}
        if(!$allowed){self::json(['posts'=>[]]);}
        $placeholders=implode(',',array_fill(0,count($allowed),'?'));
        // Feed browsing must not call markChannelRead; unread state belongs to each channel.
        $stmt=$db->prepare("SELECT cp.id,cp.channel_id,c.name channel_name,c.production_id,p.title production_title,cp.body,cp.created_at,cp.pinned,cp.author_user_id,u.initials,CONCAT(u.first_name,' ',u.last_name) author FROM channel_posts cp JOIN channels c ON c.id=cp.channel_id LEFT JOIN productions p ON p.id=c.production_id JOIN users u ON u.id=cp.author_user_id WHERE cp.channel_id IN ($placeholders) AND cp.moderation_status='published' AND cp.hidden_at IS NULL AND cp.deleted_at IS NULL ORDER BY cp.created_at DESC,cp.id DESC LIMIT 60");
        $stmt->execute($allowed);
        $posts=$stmt->fetchAll();$media=CommunityAttachmentService::forPosts($db,array_map(static fn(array $p):int=>(int)$p['id'],$posts));foreach($posts as &$post)$post['attachments']=$media[(int)$post['id']]??[];unset($post);self::addHeartCounts($db,$posts,(int)$user['id']);self::json(['posts'=>$posts]);
    }

    private static function addHeartCounts(PDO $db,array &$posts,int $viewerId): void
    {
        if(!$posts)return;
        $ids=array_map(static fn(array $p):int=>(int)$p['id'],$posts);$placeholders=implode(',',array_fill(0,count($ids),'?'));
        $s=$db->prepare("SELECT post_id,COUNT(*) heart_count,MAX(CASE WHEN user_id=? THEN 1 ELSE 0 END) hearted FROM channel_post_reactions WHERE reaction='heart' AND post_id IN ($placeholders) GROUP BY post_id");
        $s->execute(array_merge([$viewerId],$ids));$totals=[];
        foreach($s->fetchAll() as $row)$totals[(int)$row['post_id']]=$row;
        foreach($posts as &$post){$row=$totals[(int)$post['id']]??[];$post['heart_count']=(int)($row['heart_count']??0);$post['hearted']=(bool)($row['hearted']??false);}unset($post);
    }

    private static function channel(PDO $db,array $user): never
    {
        self::requireMethod('GET');$id=(int)($_GET['id']??0);
        if($id<1||!CommunicationReadStateService::canAccessChannel($db,$user,$id))self::json(['error'=>'Channel not found.'],404);
        $c=$db->prepare("SELECT c.id,c.name,c.description,c.channel_type,c.production_id,c.post_scope,p.title production_title FROM channels c LEFT JOIN productions p ON p.id=c.production_id WHERE c.id=:id AND c.archived_at IS NULL LIMIT 1");$c->execute(['id'=>$id]);$channel=$c->fetch();$channel['can_post']=CommunityExperience::mobileCanPost($db,$user,$id);
        $p=$db->prepare("SELECT cp.id,cp.body,cp.created_at,cp.author_user_id,cp.pinned,CONCAT(u.first_name,' ',u.last_name) author,u.display_role author_role,u.initials FROM channel_posts cp JOIN users u ON u.id=cp.author_user_id WHERE cp.channel_id=:id AND cp.moderation_status='published' AND cp.hidden_at IS NULL AND cp.deleted_at IS NULL ORDER BY cp.pinned DESC,cp.created_at,cp.id");$p->execute(['id'=>$id]);
        CommunicationReadStateService::markChannelRead($db,(int)$user['id'],$id);
        $posts=$p->fetchAll();$media=CommunityAttachmentService::forPosts($db,array_map(static fn(array $post):int=>(int)$post['id'],$posts));foreach($posts as &$post)$post['attachments']=$media[(int)$post['id']]??[];unset($post);self::addHeartCounts($db,$posts,(int)$user['id']);self::json(['channel'=>$channel,'posts'=>$posts]);
    }

    private static function communityPhoto(PDO $db,array $user): never
    {
        self::requireMethod('GET');
        $id=(int)($_GET['id']??0);
        if($id<1)self::json(['error'=>'Photo unavailable.'],404);
        $attachment=CommunityAttachmentService::attachment($db,$id);
        if(!$attachment||$attachment['moderation_status']!=='published'||$attachment['hidden_at']!==null||$attachment['deleted_at']!==null||!CommunicationReadStateService::canAccessChannel($db,$user,(int)$attachment['channel_id']))self::json(['error'=>'Photo unavailable.'],404);
        if(!in_array((string)$attachment['mime_type'],['image/jpeg','image/png','image/webp'],true))self::json(['error'=>'Not a displayable image.'],415);
        // Images can contain children and other private member content. Never cache
        // them in shared infrastructure or allow content sniffing.
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Vary: Authorization');
        StorageService::stream(dirname(__DIR__),$attachment,false);
        exit;
    }

    private static function channelPost(PDO $db,array $user): never
    {
        self::requireMethod('POST');$input=self::input();$id=(int)($input['channel_id']??0);$body=trim((string)($input['body']??''));
        if($id<1||$body===''||mb_strlen($body)>5000)throw new RuntimeException('Choose a channel and write a post up to 5,000 characters.');
        if(!CommunicationReadStateService::canAccessChannel($db,$user,$id))self::json(['error'=>'Channel not found.'],404);
        $upload=$_FILES['attachment']??[];
        $result=CommunityExperience::mobileCreatePost($db,$user,$id,$body,$upload);
        self::json(['ok'=>true,'post_id'=>(int)$result['post_id'],'status'=>(string)$result['status']]);
    }

    private static function channelReaction(PDO $db,array $user): never
    {
        self::requireMethod('POST');$input=self::input();$postId=(int)($input['post_id']??0);
        $stmt=$db->prepare("SELECT channel_id FROM channel_posts WHERE id=:id AND moderation_status='published' AND hidden_at IS NULL AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id'=>$postId]);$channelId=(int)($stmt->fetchColumn()?:0);
        if(!$channelId||!CommunicationReadStateService::canAccessChannel($db,$user,$channelId))self::json(['error'=>'Post not found.'],404);
        $db->beginTransaction();
        try{
            // Serialize first reactions too; a missing reaction row cannot be locked.
            $lock=$db->prepare("SELECT id FROM channel_posts WHERE id=:post AND channel_id=:channel AND moderation_status='published' AND hidden_at IS NULL AND deleted_at IS NULL FOR UPDATE");
            $lock->execute(['post'=>$postId,'channel'=>$channelId]);
            if(!$lock->fetchColumn())throw new RuntimeException('Post unavailable.');
            $check=$db->prepare("SELECT 1 FROM channel_post_reactions WHERE post_id=:post AND user_id=:user AND reaction='heart' FOR UPDATE");
            $check->execute(['post'=>$postId,'user'=>(int)$user['id']]);
            $liked=(bool)$check->fetchColumn();
            if($liked){$update=$db->prepare("DELETE FROM channel_post_reactions WHERE post_id=:post AND user_id=:user AND reaction='heart'");}
            else{$update=$db->prepare("INSERT INTO channel_post_reactions (post_id,user_id,reaction) VALUES (:post,:user,'heart')");}
            $update->execute(['post'=>$postId,'user'=>(int)$user['id']]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        self::json(['ok'=>true,'hearted'=>!$liked]);
    }

    private static function channelReplies(PDO $db,array $user): never
    {
        $postId=(int)($_GET['post_id']??0);
        $input=[];
        if($_SERVER['REQUEST_METHOD']==='POST'){$input=self::input();$postId=(int)($input['post_id']??0);}
        if($postId<1)self::json(['error'=>'Post not found.'],404);
        $postQuery=$db->prepare("SELECT cp.id,cp.channel_id FROM channel_posts cp WHERE cp.id=:id AND cp.moderation_status='published' AND cp.hidden_at IS NULL AND cp.deleted_at IS NULL LIMIT 1");
        $postQuery->execute(['id'=>$postId]);$post=$postQuery->fetch();
        if(!$post||!CommunicationReadStateService::canAccessChannel($db,$user,(int)$post['channel_id']))self::json(['error'=>'Post not found.'],404);
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!CommunityExperience::mobileCanPost($db,$user,(int)$post['channel_id']))self::json(['error'=>'Posting is not allowed in this channel.'],403);
            $body=trim((string)($input['body']??''));
            if($body===''||mb_strlen($body)>4000)throw new RuntimeException('Write a reply up to 4,000 characters.');
            $decision=ModerationService::evaluate($db,$body);
            $status=(string)($decision['status']??'');
            if(!in_array($status,['published','pending','rejected'],true))throw new RuntimeException('Reply moderation unavailable.');
            $s=$db->prepare("INSERT INTO channel_post_replies (post_id,author_user_id,body,moderation_status) VALUES (:post,:author,:body,:status)");
            $s->execute(['post'=>$postId,'author'=>(int)$user['id'],'body'=>$body,'status'=>$status]);
            self::json(['ok'=>true,'status'=>$status,'reply_id'=>(int)$db->lastInsertId()]);
        }
        self::requireMethod('GET');
        $stmt=$db->prepare("SELECT r.id,r.post_id,r.body,r.created_at,r.author_user_id,u.initials,CONCAT(u.first_name,' ',u.last_name) author FROM channel_post_replies r JOIN users u ON u.id=r.author_user_id WHERE r.post_id=:post AND r.moderation_status='published' AND r.deleted_at IS NULL ORDER BY r.created_at ASC,r.id ASC LIMIT 200");
        $stmt->execute(['post'=>$postId]);self::json(['replies'=>$stmt->fetchAll()]);
    }

    private static function logout(PDO $db): never
    {
        self::requireMethod('POST');
        $token=self::bearerToken();
        $input=self::input();
        $identity=trim((string)($input['push_identity']??''));
        if($token){
            // Only remove the calling device's registered push identity; other devices remain signed in.
            if($identity!==''&&strlen($identity)<=2048){
                $user=self::bearerUser($db);
                if($user){
                    $db->prepare('DELETE FROM mobile_push_subscriptions WHERE user_id=:user AND identity=:identity')->execute(['user'=>(int)$user['id'],'identity'=>$identity]);
                    if(str_starts_with($identity,'https://'))PushService::unsubscribe($db,(int)$user['id'],$identity);
                }
            }
            $db->prepare('UPDATE auth_mobile_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE token_hash=:hash')->execute(['hash'=>hash('sha256',$token)]);
        }
        self::json(['ok'=>true]);
    }

    private static function pushSubscription(PDO $db,array $user): never
    {
        $userId=(int)$user['id'];
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        if($method==='POST'){
            $input=self::input();
            $sub=$input['subscription']??null;
            if(!is_array($sub))throw new RuntimeException('Push subscription is required.');
            $identity=trim((string)($sub['endpoint']??$sub['fcmToken']??$sub['apnsToken']??''));
            if($identity==='')throw new RuntimeException('Push subscription identity is missing.');
            $previous=trim((string)($input['previous_identity']??''));
            // A standards-based browser Web Push subscription has endpoint + encryption keys.
            // Native APNs/FCM tokens are NOT Web Push endpoints and need a distinct sender.
            $isWeb=isset($sub['endpoint']);
            if($isWeb){
                PushService::subscribe($db,$userId,$sub,(string)($_SERVER['HTTP_USER_AGENT']??''));
            }elseif(!isset($sub['fcmToken'])&&!isset($sub['apnsToken'])){
                throw new RuntimeException('Unsupported push subscription type.');
            }
            if($previous!==''&&$previous!==$identity){
                $db->prepare("DELETE FROM mobile_push_subscriptions WHERE user_id=:user AND identity=:identity")->execute(['user'=>$userId,'identity'=>$previous]);
                if(str_starts_with($previous,'https://'))PushService::unsubscribe($db,$userId,$previous);
            }
            $json=json_encode($sub,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $stmt=$db->prepare("INSERT INTO mobile_push_subscriptions (user_id,identity,subscription_json,created_at,updated_at) VALUES (:user,:identity,:json,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),subscription_json=VALUES(subscription_json),updated_at=CURRENT_TIMESTAMP");
            $stmt->execute(['user'=>$userId,'identity'=>$identity,'json'=>$json]);
            self::json(['ok'=>true,'delivery_type'=>$isWeb?'web_push':'native_pending']);
        }
        if($method==='DELETE'){
            $identity=trim((string)($_GET['identity']??''));
            if($identity!==''){
                $db->prepare("DELETE FROM mobile_push_subscriptions WHERE user_id=:user AND identity=:identity")->execute(['user'=>$userId,'identity'=>$identity]);
                if(str_starts_with($identity,'https://'))PushService::unsubscribe($db,$userId,$identity);
            }else{
                $stmt=$db->prepare("SELECT identity FROM mobile_push_subscriptions WHERE user_id=:user");
                $stmt->execute(['user'=>$userId]);
                foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $old){
                    if(str_starts_with((string)$old,'https://'))PushService::unsubscribe($db,$userId,(string)$old);
                }
                $db->prepare("DELETE FROM mobile_push_subscriptions WHERE user_id=:user")->execute(['user'=>$userId]);
            }
            self::json(['ok'=>true]);
        }
        self::json(['error'=>'Method not allowed.'],405);
    }

    private static function bearerUser(PDO $db): ?array
    {
        $token=self::bearerToken();if(!$token||!preg_match('/^[a-f0-9]{64}$/',$token))return null;
        $stmt=$db->prepare("SELECT t.user_id FROM auth_mobile_tokens t JOIN users u ON u.id=t.user_id AND u.active=1 AND u.account_status='active' WHERE t.token_hash=:hash AND t.revoked_at IS NULL AND t.expires_at>CURRENT_TIMESTAMP LIMIT 1");$stmt->execute(['hash'=>hash('sha256',$token)]);$id=(int)($stmt->fetchColumn()?:0);if($id<1)return null;
        $db->prepare('UPDATE auth_mobile_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE token_hash=:hash')->execute(['hash'=>hash('sha256',$token)]);
        return self::loadUser($db,$id);
    }

    private static function loadUser(PDO $db,int $id): array
    {
        $s=$db->prepare("SELECT id,first_name,last_name,email,initials,display_role role,organization_membership_status FROM users WHERE id=:id AND active=1 AND account_status='active' LIMIT 1");$s->execute(['id'=>$id]);$u=$s->fetch();if(!$u)throw new RuntimeException('This account is unavailable.');$u['name']=trim($u['first_name'].' '.$u['last_name']);$u['roles']=Auth::roles($db,$id);$u['permissions']=Auth::permissions($db,$id);return $u;
    }

    private static function publicUser(array $u): array{return ['id'=>(int)$u['id'],'name'=>$u['name'],'first_name'=>$u['first_name'],'last_name'=>$u['last_name'],'email'=>$u['email'],'initials'=>$u['initials'],'role'=>$u['role'],'roles'=>$u['roles']];}
    private static function bearerToken(): ?string{$h=(string)($_SERVER['HTTP_AUTHORIZATION']??'');return preg_match('/^Bearer\s+(.+)$/i',$h,$m)?trim($m[1]):null;}
    private static function input(): array{$raw=file_get_contents('php://input')?:'';$data=json_decode($raw,true);return is_array($data)?$data:$_POST;}
    private static function requireMethod(string $method): void{if(($_SERVER['REQUEST_METHOD']??'GET')!==$method)self::json(['error'=>'Method not allowed.'],405);}

    private static function json(array $data,int $status=200): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
}
