<?php

declare(strict_types=1);

if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$detectedBasePath = rtrim(str_replace('/front.php', '', $scriptName), '/');
$_ENV['APP_BASE_PATH'] = $detectedBasePath;
$_SERVER['APP_BASE_PATH'] = $detectedBasePath;
putenv('APP_BASE_PATH=' . $detectedBasePath);
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$route = $requestPath;
if ($detectedBasePath !== '' && str_starts_with($route, $detectedBasePath)) $route = substr($route, strlen($detectedBasePath)) ?: '/';
$route = rtrim($route, '/') ?: '/';
require_once __DIR__ . '/src/Auth.php';Auth::startSession();
if ($route === '/dev/identity') {
    if (!Auth::localIdentitySwitchEnabled()) {
        http_response_code(404);
        exit('Not found');
    }
    require_once __DIR__ . '/src/DevIdentityExperience.php';DevIdentityExperience::render($detectedBasePath);
}
if ($route === '/navigation' && Auth::localIdentitySwitchEnabled()) {require_once __DIR__ . '/src/NavigationReview.php';$data=require __DIR__.'/src/mock-data.php';NavigationReview::render($detectedBasePath,$data);}
require_once __DIR__ . '/src/RuntimeSchemaGuard.php';RuntimeSchemaGuard::requireCurrentSchema(__DIR__,$detectedBasePath);
require_once __DIR__ . '/src/PublicExperience.php';if(PublicExperience::handles($route))PublicExperience::render($route,$detectedBasePath);
require_once __DIR__ . '/src/AuthExperience.php';if(AuthExperience::handles($route))AuthExperience::render($route,$detectedBasePath);
if($route==='/calendar/feed'){require_once __DIR__.'/src/CalendarExperience.php';CalendarExperience::render($route,$detectedBasePath);}
if(in_array($route,['/playbill','/playbill/asset'],true)){require_once __DIR__.'/src/Playbill2Experience.php';Playbill2Experience::render($route,$detectedBasePath);}
if($route==='/health'){require __DIR__.'/index.php';exit;}
if(!Auth::check()){$returnTo='?return_to='.rawurlencode($route.(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''));header('Location: '.($detectedBasePath?:'').'/login'.$returnTo,true,303);exit;}
$dbForAuth=Database::connect(__DIR__);$currentAuthUser=Auth::currentUser($dbForAuth);if(!$currentAuthUser){header('Location: '.($detectedBasePath?:'').'/login',true,303);exit;}
$localIdentity=!empty($_SESSION['auth_local_identity'])&&Auth::localIdentitySwitchEnabled();$pendingAllowed=['/app','/account','/onboarding','/family/manage','/family-hub','/notifications','/notification-preferences','/push-settings','/help'];$productionAccess=$dbForAuth->prepare("SELECT 1 FROM productions p WHERE p.is_active=1 AND (EXISTS (SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id AND pm.user_id=:viewer AND pm.status='active') OR EXISTS (SELECT 1 FROM family_relationships fr JOIN users child ON child.id=fr.student_user_id AND child.active=1 AND child.account_status<>'disabled' JOIN production_memberships cpm ON cpm.user_id=child.id AND cpm.status='active' WHERE cpm.production_id=p.id AND fr.guardian_user_id=:guardian AND fr.status='active')) LIMIT 1");$productionAccess->execute(['viewer'=>(int)$currentAuthUser['id'],'guardian'=>(int)$currentAuthUser['id']]);$hasProductionAccess=(bool)$productionAccess->fetchColumn();if(!$localIdentity&&!Auth::isApprovedMember($currentAuthUser)&&!$hasProductionAccess&&!in_array($route,$pendingAllowed,true)){header('Location: '.($detectedBasePath?:'').'/app',true,303);exit;}
$scheduleWriteRoutes=['/production/schedule/new','/production/edit','/production/notices','/production/notice'];$calendarScheduleAction=$route==='/calendar'&&$_SERVER['REQUEST_METHOD']==='POST'&&in_array((string)($_POST['action']??''),['duplicate','cancel'],true);if(in_array($route,$scheduleWriteRoutes,true)||$calendarScheduleAction){require_once __DIR__.'/src/AccessPolicy.php';if(!AccessPolicy::canManageSchedule($currentAuthUser)){http_response_code(403);header('Content-Type:text/plain; charset=utf-8');exit('Schedule management permission is required.');}}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&in_array($route,['/volunteer/shift','/volunteer/approvals','/admin/volunteer-approvals'],true)){require_once __DIR__.'/src/VolunteerLifecycleGuard.php';try{VolunteerLifecycleGuard::assertActionAllowed($dbForAuth,$route,$_POST);}catch(RuntimeException $e){if($route==='/volunteer/shift'){$_SESSION['volunteer_flash']=['type'=>'error','message'=>$e->getMessage()];$shiftId=(int)($_POST['shift_id']??0);header('Location: '.($detectedBasePath?:'').'/volunteer/shift?id='.$shiftId,true,303);exit;}$_SESSION['volunteer_approval_flash']=['type'=>'error','message'=>$e->getMessage()];$target=$route==='/volunteer/approvals'?'/volunteer/approvals':'/admin/volunteer-approvals';header('Location: '.($detectedBasePath?:'').$target,true,303);exit;}}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){require_once __DIR__.'/src/IdentityRolePolicy.php';try{$identityAction=(string)($_POST['action']??'');if($identityAction==='add_relationship'&&in_array($route,['/people','/people/view','/admin/accounts','/admin/accounts/view'],true))IdentityRolePolicy::assertFamilyPair($dbForAuth,(int)($_POST['guardian_user_id']??0),(int)($_POST['student_user_id']??0));if($route==='/production/people'&&$identityAction==='add')IdentityRolePolicy::assertProductionAudience($dbForAuth,(int)($_POST['user_id']??0),(string)($_POST['audience_type']??''));if(str_starts_with($route,'/admin/accounts')&&$identityAction==='save_roles')IdentityRolePolicy::assertRoleSelection($dbForAuth,(int)($_POST['user_id']??0),(array)($_POST['role_ids']??[]));}catch(RuntimeException $e){if($route==='/production/people'){$_SESSION['production_people_flash']=['type'=>'error','message'=>$e->getMessage()];header('Location: '.($detectedBasePath?:'').'/production/people',true,303);exit;}if(str_starts_with($route,'/admin/accounts')){$_SESSION['accounts_flash']=['type'=>'error','message'=>$e->getMessage()];$accountId=(int)($_POST['user_id']??0);header('Location: '.($detectedBasePath?:'').'/admin/accounts/view?id='.$accountId,true,303);exit;}$_SESSION['people_flash']=['type'=>'error','message'=>$e->getMessage()];$studentId=(int)($_POST['student_user_id']??0);header('Location: '.($detectedBasePath?:'').'/people/view?id='.$studentId,true,303);exit;}}
if(in_array($route,['/messages/thread','/channels/view'],true)){require_once __DIR__.'/src/CommunicationReadStateService.php';if($route==='/messages/thread'){$conversationId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;if($conversationId>0)CommunicationReadStateService::markConversationRead($dbForAuth,(int)$currentAuthUser['id'],(int)$conversationId);}if($route==='/channels/view'){$channelId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;if($channelId>0&&CommunicationReadStateService::canAccessChannel($dbForAuth,$currentAuthUser,(int)$channelId))CommunicationReadStateService::markChannelRead($dbForAuth,(int)$currentAuthUser['id'],(int)$channelId);}}
unset($currentAuthUser,$dbForAuth);
$experienceRoutes=[
    ['AccountManagementExperience',['/admin/accounts','/admin/accounts/view'],'route'],
    ['RegistrationOperationsExperience',['/admin/registrations','/admin/registrations/edit','/admin/registrations/view','/admin/registrations/intake'],'route'],
    ['EmailOperationsExperience',['/admin/email'],'base'],
    ['NotificationPreferenceExperience',['/notification-preferences','/push-settings','/push/subscribe','/push/unsubscribe','/push/test'],'base'],
    ['HelpExperience',['/help'],'base'],
    ['MyAccountExperience',['/account'],'base'],
    ['FamilyDashboardExperience',['/family-hub','/parent','/onboarding','/family/manage'],'route'],
    ['HomeExperience',['/app'],'route'],
    ['StaffDashboardExperience',['/staff'],'route'],
    ['OperationsHubExperience',['/admin/operations','/admin/operations/community','/admin/operations/resources','/admin/operations/forms'],'route'],
    ['CalendarExperience',['/calendar','/calendar/feed'],'route'],
    ['MemberCastExperience',['/cast'],'base'],
    ['StudentProfileExperience',['/student-profile','/student-profile/headshot'],'route'],
    ['ActingResumeExperience',['/theatre-history/resume'],'base'],
    ['TheatreHistoryExperience',['/theatre-history'],'base'],
    ['ProductionArchiveExperience',['/archive','/archive/production','/archive/community','/archive/file'],'route'],
    ['OrganizationResourceExperience',['/admin/member-resources','/admin/member-resources/edit','/member-resources/download'],'route'],
    ['AccountLibraryExperience',['/files','/files/view','/resources','/resources/view'],'route'],
    ['ProductionContextExperience',['/production/select'],'base'],
    ['PeopleExperience',['/family-hub','/people','/people/view'],'route'],
    ['NotificationExperience',['/notifications'],'base'],
    ['FamilyFormsExperience',['/forms','/forms/view','/admin/forms','/admin/forms/review','/admin/forms/group-assign'],'route'],
    ['FormManagementExperience',['/admin/forms/manage','/admin/forms/manage/edit','/admin/forms/manage/assign'],'route'],
    ['FormBuilderIndexExperience',['/admin/forms/build'],'base'],
    ['DynamicFormExperience',['/admin/forms/builder'],'route'],
    ['VolunteerServiceRecordExperience',['/volunteer/history','/volunteer/service-record'],'base'],
    ['VolunteerVerificationExperience',['/volunteer/verifications','/volunteer/verification','/admin/volunteer-verification'],'route'],
    ['VolunteerDevelopmentExperience',['/volunteer/history','/volunteer/training','/admin/volunteer-development'],'route'],
    ['VolunteerShiftManagementExperience',['/admin/volunteer-shifts','/admin/volunteer-shifts/new','/admin/volunteer-shifts/view'],'route'],
    ['VolunteerApprovalExperience',['/volunteer/approvals','/admin/volunteer-approvals','/admin/volunteer-approvals/review'],'route'],
    ['VolunteerExperience',['/volunteer-readiness','/volunteer-shifts','/volunteer/shift'],'route'],
    ['TeamExperience',['/admin/teams','/admin/teams/view','/admin/private-channel'],'route'],
    ['ModerationExperience',['/admin/moderation/terms','/admin/moderation/terms/edit','/admin/moderation/queue'],'route'],
    ['CommunityManagementExperience',['/admin/channels','/admin/channels/edit'],'route'],
    ['CommunityExperience',['/channels','/channels/view','/channels/attachment'],'route'],
    ['ProductionLifecycleExperience',['/admin/productions','/admin/productions/view'],'route'],
    ['CastingExperience',['/production/casting'],'base'],
    ['ProductionReadinessExperience',['/production/readiness'],'base'],
    ['ProductionDayExperience',['/production/day'],'route'],
    ['ProductionWorkspaceExperience',['/production'],'base'],
    ['ProductionGroupExperience',['/production/groups','/production/groups/view'],'route'],
    ['ProductionPeopleExperience',['/production/people'],'route'],
    ['AttendanceExperience',['/attendance','/attendance/take','/attendance/report'],'route'],
    ['ScheduleCreateExperience',['/production/schedule/new'],'route'],
    ['ScheduleNoticeExperience',['/production/notices','/production/notice'],'route'],
    ['ProductionFileExperience',['/files','/files/view','/files/download','/admin/files','/admin/files/edit'],'route'],
    ['ResourceExperience',['/resources','/resources/view','/admin/resources','/admin/resources/edit'],'route'],
    ['Playbill2Experience',['/playbill','/playbill/asset','/admin/playbill/media'],'route'],
    ['PlaybillExperience',['/playbills','/admin/playbill'],'route'],
    ['ProductionExperience',['/production','/schedule','/production/day','/production/edit','/resources','/playbills'],'route'],
    ['CommunicationExperience',['/messages','/messages/new','/messages/thread','/messages/attachment'],'route'],
    ['SafeguardingCaseExperience',['/safeguarding/cases','/safeguarding/case'],'route'],
    ['SafeguardingExperience',['/safeguarding','/safeguarding/review','/safeguarding/audit'],'route'],
];
foreach($experienceRoutes as [$class,$routes,$mode]){
    if(!in_array($route,$routes,true))continue;
    require_once __DIR__.'/src/'.$class.'.php';
    if(!$class::handles($route))continue;
    $mode==='route'?$class::render($route,$detectedBasePath):$class::render($detectedBasePath);
}
if(Auth::localIdentitySwitchEnabled()){
    require_once __DIR__.'/src/VisualPass3.php';if(VisualPass3::handles($route)){$data=require __DIR__.'/src/mock-data.php';VisualPass3::render($route,$detectedBasePath,$data);}
    require_once __DIR__.'/src/VisualPass.php';if(VisualPass::handles($route)){$data=require __DIR__.'/src/mock-data.php';VisualPass::render($route,$detectedBasePath,$data);}
}
require __DIR__.'/index.php';
