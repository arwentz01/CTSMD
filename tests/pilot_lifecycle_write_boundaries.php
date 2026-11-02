<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/src/';
$cases=[
 'CastingRosterService.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,$productionId,true);',
  "'casting.removed_with_production_membership'",
 ],
 'VolunteerShiftManagementExperience.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,(int)$production[\'id\']);',
  'ProductionLifecycleGuard::assertWritable($db,(int)$signup[\'production_id\']);',
 ],
 'VolunteerExperience.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,(int)$shift[\'production_id\']);',
  "p.lifecycle_stage IN ('upcoming','planning','in_production','performing')",
  'if ($shift[\'production_id\'] !== null) ProductionLifecycleGuard::assertWritable($db,(int)$shift[\'production_id\']);',
 ],
 'VolunteerApprovalExperience.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,(int)$shift[\'production_id\']);',
  'ProductionLifecycleGuard::assertWritable($db,(int)$r[\'production_id\']);',
 ],
 'CommunityManagementExperience.php'=>[
  'if($productionId!==null)ProductionLifecycleGuard::assertWritable($db,(int)$productionId);',
 ],
 'CommunityExperience.php'=>[
  "fr.relationship_type IN ('parent','guardian')",
  "['upcoming','planning','in_production','performing']",
  'ProductionLifecycleGuard::assertWritable($db,(int)$lockedPost[\'production_id\']);',
 ],
 'FormExperience.php'=>[
  "require_once __DIR__ . '/ProductionLifecycleGuard.php';",
  'ProductionLifecycleGuard::assertWritable($db, (int)$assignment[\'production_id\']);',
  'ProductionLifecycleGuard::assertWritable($db, (int)$submission[\'production_id\']);',
 ],
 'ProductionArchiveService.php'=>[
  'if (!self::activeViewer($db,$viewerId)) return [];',
  'if ($productionId < 1 || !self::activeViewer($db,(int)$viewer[\'id\'])) return false;',
  "account_status<>'disabled'",

  "JOIN users student ON student.id=fr.student_user_id AND student.active=1 AND student.account_status='active'",
  "fr.relationship_type IN ('parent','guardian')",
 ],
 'ProductionArchivalWorkflow.php'=>[
  "pending_volunteer_approvals",
  "draft_schedule_notices",
  "Archival blocked by unresolved casting offers or volunteer approvals.",

  "lifecycle_stage='archived'",
  "production.archived",
  "FOR UPDATE",
  "Only completed productions can be archived.",
 ],
 'MobileApi.php'=>[
  "fa.status<>'completed'",
  "fa.production_id IS NULL OR EXISTS",
  "vs.production_id IS NULL OR EXISTS",
  'ProductionLifecycleGuard::assertWritable($db,(int)$lockedPost[\'production_id\']);',
 ],
 'FamilyDashboardService.php'=>[
  "p.lifecycle_stage IN ('upcoming','planning','in_production','performing')",
  "fa.production_id IS NULL",
  "vs.production_id IS NULL",
 ],
 'HomeDashboardService.php'=>[
  "fa.production_id IS NULL",
  "vs.production_id IS NULL",
 ],
 'CalendarService.php'=>[
  "p.lifecycle_stage IN ('upcoming','planning','in_production','performing')",
 ],
 'ProductionCompletionService.php'=>[
  "pending_casting_offers",
  "unmarked_attendance",
  "draft_notices",
  "incomplete_production_tasks",
  "Completion blocked by unresolved casting offers.",
  "lifecycle_stage='completed'",
  "production.completed",
  "FOR UPDATE",
  "Production management permission is required.",
 ],
 'ProductionLifecycleExperience.php'=>[
  "complete_production",
  "archive_production",
  "archiveReadiness",
  "archiveBlocked",
  "Closed productions cannot be reactivated through legacy controls.",
  "acknowledge_outstanding",
  'Completion, archival, and reopening require a separate authorized lifecycle confirmation workflow.',
 ],
 'ProductionLifecycleAutomation.php'=>[
  "FROM productions WHERE lifecycle_stage='in_production' FOR UPDATE",
  'if($stage===\'in_production\'&&$start!==\'\'&&$time>=$start&&!$finished)$target=\'performing\';',
 ],
 'MailService.php'=>[
  "Volunteer shift access revoked or production closed.",
  "shift-(?:reminder|eligibility)",

  "Form or family access revoked before delivery.",
  "form-due-",
  "fr.relationship_type IN ('parent','guardian')",
 ],
 'PushService.php'=>[
  "Notification access revoked before delivery.",
  "ProductionNotificationAccess::visible",
 ],
 'PushEventBridgeService.php'=>[
  "ProductionNotificationAccess::visible",
  "n.source_type,n.source_id",
 ],
 'NotificationReminderService.php'=>[
  "fa.production_id IS NULL OR EXISTS",
  "vs.production_id IS NULL OR EXISTS",
  "fr.relationship_type IN ('parent','guardian')",
  "p.lifecycle_stage IN ('upcoming','planning','in_production','performing')",
 ],
 'ProductionNotificationAccess.php'=>[
  "relationship_type IN ('parent','guardian')",
 ],
 'ProductionArchiveService.php'=>[
  "fr.relationship_type IN ('parent','guardian')",
 ],
 'FamilyFormService.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,(int)$context[\'production_id\']);',
 ],
];
foreach($cases as $file=>$needles){
 $source=file_get_contents($root.$file);
 if($source===false)throw new RuntimeException('Cannot read '.$file);
 foreach($needles as $needle){
  if(!str_contains($source,$needle))throw new RuntimeException($file.' is missing lifecycle guard: '.$needle);
 }
 echo "PASS: lifecycle guards present in ".$file."\n";
}
