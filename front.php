<?php

declare(strict_types=1);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$detectedBasePath = rtrim(str_replace('/front.php', '', $scriptName), '/');
$_ENV['APP_BASE_PATH'] = $detectedBasePath;
$_SERVER['APP_BASE_PATH'] = $detectedBasePath;
putenv('APP_BASE_PATH=' . $detectedBasePath);

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$route = $requestPath;
if ($detectedBasePath !== '' && str_starts_with($route, $detectedBasePath)) {
    $route = substr($route, strlen($detectedBasePath)) ?: '/';
}
$route = rtrim($route, '/') ?: '/';

require_once __DIR__ . '/src/Auth.php';
Auth::startSession();

if ($route === '/dev/identity') {
    require_once __DIR__ . '/src/DevIdentityExperience.php';
    DevIdentityExperience::render($detectedBasePath);
}

if ($route === '/navigation' && Auth::localIdentitySwitchEnabled()) {
    require_once __DIR__ . '/src/NavigationReview.php';
    $data = require __DIR__ . '/src/mock-data.php';
    NavigationReview::render($detectedBasePath, $data);
}

require_once __DIR__ . '/src/SchemaGuard.php';
SchemaGuard::requireCurrentSchema(__DIR__, $detectedBasePath);

require_once __DIR__ . '/src/AuthExperience.php';
if (AuthExperience::handles($route)) {
    AuthExperience::render($route, $detectedBasePath);
}

// Token-authenticated calendar subscriptions do not require a browser login.
if ($route === '/calendar/feed') {
    require_once __DIR__ . '/src/CalendarExperience.php';
    CalendarExperience::render($route, $detectedBasePath);
}

// The published Playbill is intentionally public.
if ($route === '/playbill') {
    require_once __DIR__ . '/src/PlaybillExperience.php';
    PlaybillExperience::render($route, $detectedBasePath);
}

if ($route === '/health') {
    require __DIR__ . '/index.php';
    exit;
}

if (!Auth::check()) {
    $returnTo = $route !== '/' ? '?return_to=' . rawurlencode($route . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) : '';
    header('Location: ' . ($detectedBasePath ?: '') . '/login' . $returnTo, true, 303);
    exit;
}

$dbForAuth = Database::connect(__DIR__);
if (!Auth::currentUser($dbForAuth)) {
    header('Location: ' . ($detectedBasePath ?: '') . '/login', true, 303);
    exit;
}
unset($dbForAuth);

require_once __DIR__ . '/src/AccountManagementExperience.php';
if (AccountManagementExperience::handles($route)) {
    AccountManagementExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/CalendarExperience.php';
if (CalendarExperience::handles($route)) {
    CalendarExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ProductionContextExperience.php';
if (ProductionContextExperience::handles($route)) {
    ProductionContextExperience::render($detectedBasePath);
}

require_once __DIR__ . '/src/PeopleExperience.php';
if (PeopleExperience::handles($route)) {
    PeopleExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/NotificationExperience.php';
if (NotificationExperience::handles($route)) {
    NotificationExperience::render($detectedBasePath);
}

require_once __DIR__ . '/src/FormManagementExperience.php';
if (FormManagementExperience::handles($route)) {
    FormManagementExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/FormBuilderIndexExperience.php';
if (FormBuilderIndexExperience::handles($route)) {
    FormBuilderIndexExperience::render($detectedBasePath);
}

require_once __DIR__ . '/src/DynamicFormExperience.php';
if (DynamicFormExperience::handles($route)) {
    DynamicFormExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/FormExperience.php';
if (FormExperience::handles($route)) {
    FormExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/HomeExperience.php';
if (HomeExperience::handles($route)) {
    $data = require __DIR__ . '/src/mock-data.php';
    HomeExperience::render($route, $detectedBasePath, $data);
}

require_once __DIR__ . '/src/VolunteerDevelopmentExperience.php';
if (VolunteerDevelopmentExperience::handles($route)) {
    VolunteerDevelopmentExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/VolunteerShiftManagementExperience.php';
if (VolunteerShiftManagementExperience::handles($route)) {
    VolunteerShiftManagementExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/VolunteerApprovalExperience.php';
if (VolunteerApprovalExperience::handles($route)) {
    VolunteerApprovalExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/VolunteerExperience.php';
if (VolunteerExperience::handles($route)) {
    VolunteerExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/TeamExperience.php';
if (TeamExperience::handles($route)) {
    TeamExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ModerationExperience.php';
if (ModerationExperience::handles($route)) {
    ModerationExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/CommunityManagementExperience.php';
if (CommunityManagementExperience::handles($route)) {
    CommunityManagementExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/CommunityExperience.php';
if (CommunityExperience::handles($route)) {
    CommunityExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ProductionLifecycleExperience.php';
if (ProductionLifecycleExperience::handles($route)) {
    ProductionLifecycleExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ProductionWorkspaceExperience.php';
if (ProductionWorkspaceExperience::handles($route)) {
    ProductionWorkspaceExperience::render($detectedBasePath);
}

require_once __DIR__ . '/src/ProductionGroupExperience.php';
if (ProductionGroupExperience::handles($route)) {
    ProductionGroupExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ProductionPeopleExperience.php';
if (ProductionPeopleExperience::handles($route)) {
    ProductionPeopleExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/AttendanceExperience.php';
if (AttendanceExperience::handles($route)) {
    AttendanceExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ScheduleCreateExperience.php';
if (ScheduleCreateExperience::handles($route)) {
    ScheduleCreateExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ScheduleNoticeExperience.php';
if (ScheduleNoticeExperience::handles($route)) {
    ScheduleNoticeExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ResourceExperience.php';
if (ResourceExperience::handles($route)) {
    ResourceExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/PlaybillExperience.php';
if (PlaybillExperience::handles($route)) {
    PlaybillExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/ProductionExperience.php';
if (ProductionExperience::handles($route)) {
    ProductionExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/CommunicationExperience.php';
if (CommunicationExperience::handles($route)) {
    CommunicationExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/SafeguardingExperience.php';
if (SafeguardingExperience::handles($route)) {
    SafeguardingExperience::render($route, $detectedBasePath);
}

require_once __DIR__ . '/src/VisualPass3.php';
if (VisualPass3::handles($route)) {
    $data = require __DIR__ . '/src/mock-data.php';
    VisualPass3::render($route, $detectedBasePath, $data);
}

require_once __DIR__ . '/src/VisualPass.php';
if (VisualPass::handles($route)) {
    $data = require __DIR__ . '/src/mock-data.php';
    VisualPass::render($route, $detectedBasePath, $data);
}

require __DIR__ . '/index.php';
