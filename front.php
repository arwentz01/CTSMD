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
require_once __DIR__ . '/src/SchemaGuard.php';SchemaGuard::requireCurrentSchema(__DIR__,$detectedBasePath);
require_once __DIR__ . '/src/PublicExperience.php';if(PublicExperience::handles($route))PublicExperience::render($route,$detectedBasePath);
require_once __DIR__ . '/src/AuthExperience.php';if(AuthExperience::handles($route))AuthExperience::render($route,$detectedBasePath);
if($route==='/calendar/feed'){require_once __DIR__.'/src/CalendarExperience.php';CalendarExperience::render($route,$detectedBasePath);}
if(in_array($route,['/playbill','/playbill/asset'],true)){require_once __DIR__.'/src/Playbill2Experience.php';Playbill2Experience::render($route,$detectedBasePath);}
if($route==='/health'){require __DIR__.'/index.php';exit;}
if(!Auth::check()){$returnTo='?return_to='.rawurlencode($route.(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:''));header('Location: '.($detectedBasePath?:'').'/login'.$returnTo,true,303);exit;}
$dbForAuth=Database::connect(__DIR__);$currentAuthUser=Auth::currentUser($dbForAuth);if(!$currentAuthUser){header('Location: '.($detectedBasePath?:'').'/login',true,303);exit;}
$localIdentity=!empty($_SESSION['auth_local_identity'])&&Auth::localIdentitySwitchEnabled();$pendingAllowed=['/app','/onboarding','/family/manage','/family-hub','/notifications','/notification-preferences','/push-settings','/help'];if(!$localIdentity&&!Auth::isApprovedMember($currentAuthUser)&&!in_array($route,$pendingAllowed,true)){header('Location: '.($detectedBasePath?:'').'/app',true,303);exit;}
require_once __DIR__.'/src/AccessPolicy.php';$scheduleWriteRoutes=['/production/schedule/new','/production/edit','/production/notices','/production/notice'];$calendarScheduleAction=$route==='/calendar'&&$_SERVER['REQUEST_METHOD']==='POST'&&in_array((string)($_POST['action']??''),['duplicate','cancel'],true);if((in_array($route,$scheduleWriteRoutes,true)||$calendarScheduleAction)&&!AccessPolicy::canManageSchedule($currentAuthUser)){http_response_code(403);header('Content-Type:text/plain; charset=utf-8');exit('Schedule management permission is required.');}
require_once __DIR__.'/src/VolunteerLifecycleGuard.php';try{VolunteerLifecycleGuard::assertActionAllowed($dbForAuth,$route,$_POST);}catch(RuntimeException $e){if($route==='/volunteer/shift'){$_SESSION['volunteer_flash']=['type'=>'error','message'=>$e->getMessage()];$shiftId=(int)($_POST['shift_id']??0);header('Location: '.($detectedBasePath?:'').'/volunteer/shift?id='.$shiftId,true,303);exit;}$_SESSION['volunteer_approval_flash']=['type'=>'error','message'=>$e->getMessage()];$target=$route==='/volunteer/approvals'?'/volunteer/approvals':'/admin/volunteer-approvals';header('Location: '.($detectedBasePath?:'').$target,true,303);exit;}
require_once __DIR__.'/src/IdentityRolePolicy.php';if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){try{$identityAction=(string)($_POST['action']??'');if($identityAction==='add_relationship'&&in_array($route,['/people','/people/view','/admin/accounts','/admin/accounts/view'],true))IdentityRolePolicy::assertFamilyPair($dbForAuth,(int)($_POST['guardian_user_id']??0),(int)($_POST['student_user_id']??0));if($route==='/production/people'&&$identityAction==='add')IdentityRolePolicy::assertProductionAudience($dbForAuth,(int)($_POST['user_id']??0),(string)($_POST['audience_type']??''));}catch(RuntimeException $e){if($route==='/production/people'){$_SESSION['production_people_flash']=['type'=>'error','message'=>$e->getMessage()];header('Location: '.($detectedBasePath?:'').'/production/people',true,303);exit;}if(str_starts_with($route,'/admin/accounts')){$_SESSION['accounts_flash']=['type'=>'error','message'=>$e->getMessage()];$accountId=(int)($_POST['user_id']??0);header('Location: '.($detectedBasePath?:'').'/admin/accounts/view?id='.$accountId,true,303);exit;}$_SESSION['people_flash']=['type'=>'error','message'=>$e->getMessage()];$studentId=(int)($_POST['student_user_id']??0);header('Location: '.($detectedBasePath?:'').'/people/view?id='.$studentId,true,303);exit;}}
require_once __DIR__.'/src/CommunicationReadStateService.php';if($route==='/messages/thread'){$conversationId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;if($conversationId>0)CommunicationReadStateService::markConversationRead($dbForAuth,(int)$currentAuthUser['id'],(int)$conversationId);}if($route==='/channels/view'){$channelId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;if($channelId>0&&CommunicationReadStateService::canAccessChannel($dbForAuth,$currentAuthUser,(int)$channelId))CommunicationReadStateService::markChannelRead($dbForAuth,(int)$currentAuthUser['id'],(int)$channelId);}unset($currentAuthUser,$dbForAuth);
require_once __DIR__.'/src/AccountManagementExperience.php';if(AccountManagementExperience::handles($route))AccountManagementExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/RegistrationOperationsExperience.php';if(RegistrationOperationsExperience::handles($route))RegistrationOperationsExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/EmailOperationsExperience.php';if(EmailOperationsExperience::handles($route))EmailOperationsExperience::render($detectedBasePath);
require_once __DIR__.'/src/NotificationPreferenceExperience.php';if(NotificationPreferenceExperience::handles($route))NotificationPreferenceExperience::render($detectedBasePath);
require_once __DIR__.'/src/HelpExperience.php';if(HelpExperience::handles($route))HelpExperience::render($detectedBasePath);
require_once __DIR__.'/src/FamilyDashboardExperience.php';if(FamilyDashboardExperience::handles($route))FamilyDashboardExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/HomeExperience.php';if(HomeExperience::handles($route))HomeExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/StaffDashboardExperience.php';if(StaffDashboardExperience::handles($route))StaffDashboardExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/OperationsHubExperience.php';if(OperationsHubExperience::handles($route))OperationsHubExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/CalendarExperience.php';if(CalendarExperience::handles($route))CalendarExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/MemberCastExperience.php';if(MemberCastExperience::handles($route))MemberCastExperience::render($detectedBasePath);
require_once __DIR__.'/src/StudentProfileExperience.php';if(StudentProfileExperience::handles($route))StudentProfileExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ActingResumeExperience.php';if(ActingResumeExperience::handles($route))ActingResumeExperience::render($detectedBasePath);
require_once __DIR__.'/src/TheatreHistoryExperience.php';if(TheatreHistoryExperience::handles($route))TheatreHistoryExperience::render($detectedBasePath);
require_once __DIR__.'/src/ProductionArchiveExperience.php';if(ProductionArchiveExperience::handles($route))ProductionArchiveExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/OrganizationResourceExperience.php';if(OrganizationResourceExperience::handles($route))OrganizationResourceExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/AccountLibraryExperience.php';if(AccountLibraryExperience::handles($route))AccountLibraryExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ProductionContextExperience.php';if(ProductionContextExperience::handles($route))ProductionContextExperience::render($detectedBasePath);
require_once __DIR__.'/src/PeopleExperience.php';if(PeopleExperience::handles($route))PeopleExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/NotificationExperience.php';if(NotificationExperience::handles($route))NotificationExperience::render($detectedBasePath);
require_once __DIR__.'/src/FamilyFormsExperience.php';if(FamilyFormsExperience::handles($route))FamilyFormsExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/FormManagementExperience.php';if(FormManagementExperience::handles($route))FormManagementExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/FormBuilderIndexExperience.php';if(FormBuilderIndexExperience::handles($route))FormBuilderIndexExperience::render($detectedBasePath);
require_once __DIR__.'/src/DynamicFormExperience.php';if(DynamicFormExperience::handles($route))DynamicFormExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/VolunteerServiceRecordExperience.php';if(VolunteerServiceRecordExperience::handles($route))VolunteerServiceRecordExperience::render($detectedBasePath);
require_once __DIR__.'/src/VolunteerVerificationExperience.php';if(VolunteerVerificationExperience::handles($route))VolunteerVerificationExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/VolunteerDevelopmentExperience.php';if(VolunteerDevelopmentExperience::handles($route))VolunteerDevelopmentExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/VolunteerShiftManagementExperience.php';if(VolunteerShiftManagementExperience::handles($route))VolunteerShiftManagementExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/VolunteerApprovalExperience.php';if(VolunteerApprovalExperience::handles($route))VolunteerApprovalExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/VolunteerExperience.php';if(VolunteerExperience::handles($route))VolunteerExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/TeamExperience.php';if(TeamExperience::handles($route))TeamExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ModerationExperience.php';if(ModerationExperience::handles($route))ModerationExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/CommunityManagementExperience.php';if(CommunityManagementExperience::handles($route))CommunityManagementExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/CommunityExperience.php';if(CommunityExperience::handles($route))CommunityExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ProductionLifecycleExperience.php';if(ProductionLifecycleExperience::handles($route))ProductionLifecycleExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/CastingExperience.php';if(CastingExperience::handles($route))CastingExperience::render($detectedBasePath);
require_once __DIR__.'/src/ProductionReadinessExperience.php';if(ProductionReadinessExperience::handles($route))ProductionReadinessExperience::render($detectedBasePath);
require_once __DIR__.'/src/ProductionDayExperience.php';if(ProductionDayExperience::handles($route))ProductionDayExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ProductionWorkspaceExperience.php';if(ProductionWorkspaceExperience::handles($route))ProductionWorkspaceExperience::render($detectedBasePath);
require_once __DIR__.'/src/ProductionGroupExperience.php';if(ProductionGroupExperience::handles($route))ProductionGroupExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ProductionPeopleExperience.php';if(ProductionPeopleExperience::handles($route))ProductionPeopleExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/AttendanceExperience.php';if(AttendanceExperience::handles($route))AttendanceExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ScheduleCreateExperience.php';if(ScheduleCreateExperience::handles($route))ScheduleCreateExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ScheduleNoticeExperience.php';if(ScheduleNoticeExperience::handles($route))ScheduleNoticeExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ProductionFileExperience.php';if(ProductionFileExperience::handles($route))ProductionFileExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ResourceExperience.php';if(ResourceExperience::handles($route))ResourceExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/Playbill2Experience.php';if(Playbill2Experience::handles($route))Playbill2Experience::render($route,$detectedBasePath);
require_once __DIR__.'/src/PlaybillExperience.php';if(PlaybillExperience::handles($route))PlaybillExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/ProductionExperience.php';if(ProductionExperience::handles($route))ProductionExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/CommunicationExperience.php';if(CommunicationExperience::handles($route))CommunicationExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/SafeguardingCaseExperience.php';if(SafeguardingCaseExperience::handles($route))SafeguardingCaseExperience::render($route,$detectedBasePath);
require_once __DIR__.'/src/SafeguardingExperience.php';if(SafeguardingExperience::handles($route))SafeguardingExperience::render($route,$detectedBasePath);
if(Auth::localIdentitySwitchEnabled()){
    require_once __DIR__.'/src/VisualPass3.php';if(VisualPass3::handles($route)){$data=require __DIR__.'/src/mock-data.php';VisualPass3::render($route,$detectedBasePath,$data);}
    require_once __DIR__.'/src/VisualPass.php';if(VisualPass::handles($route)){$data=require __DIR__.'/src/mock-data.php';VisualPass::render($route,$detectedBasePath,$data);}
}
require __DIR__.'/index.php';
