<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/src/';
$cases=[
 'ProductionPeopleExperience.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,$productionId);',
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
 'ProductionLifecycleExperience.php'=>[
  'Completion, archival, and reopening require a separate authorized lifecycle confirmation workflow.',
 ],
 'ProductionLifecycleAutomation.php'=>[
  "FROM productions WHERE lifecycle_stage='in_production' FOR UPDATE",
  'if($stage===\'in_production\'&&$start!==\'\'&&$time>=$start&&!$finished)$target=\'performing\';',
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
