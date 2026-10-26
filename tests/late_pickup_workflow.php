<?php

declare(strict_types=1);

$root=dirname(__DIR__);$migration=file_get_contents($root.'/database/migrations/084_late_pickup_workflow.sql');$service=file_get_contents($root.'/src/LatePickupService.php');$mail=file_get_contents($root.'/src/MailService.php');$attendance=file_get_contents($root.'/src/AttendanceExperience.php');$experience=file_get_contents($root.'/src/LatePickupExperience.php');
if($migration===false||$service===false||$mail===false||$attendance===false||$experience===false)throw new RuntimeException('Late pickup sources are unavailable.');
$checks=[
 'student/event duplicate suppression'=>str_contains($migration,'UNIQUE KEY uq_late_pickup_student_event'),
 'occurrences are never hard deleted'=>!preg_match('/DELETE\s+FROM\s+late_pickup_occurrences/i',$service),
 'corrections and reversals are revised'=>str_contains($service,"'corrected'")&&str_contains($service,"'voided'")&&str_contains($service,'late_pickup_occurrence_revisions'),
 'threshold ignores reversed records'=>str_contains($service,'voided_at IS NULL AND late_minutes>:grace'),
 'cross-production occurrence key stored'=>str_contains($migration,'production_id BIGINT UNSIGNED NOT NULL'),
 'closed/cancelled writes use lifecycle guard'=>str_contains($service,'assertScheduleItemWritable')&&str_contains($service,"status']!=='active'"),
 'approval is separate from send'=>str_contains($service,'public static function approve')&&str_contains($service,'public static function send'),
 'send is idempotent per guardian and revision'=>str_contains($service,"late-pickup-notice-")&&str_contains($migration,'uq_late_pickup_notice_guardian'),
 'current guardians checked at send'=>str_contains($service,"fr.status='active'")&&str_contains($service,"account_status='active'"),
 'guardian relationship rechecked by worker'=>str_contains($mail,'Guardian relationship is no longer active.'),
 'attendance checkout is separate from status'=>str_contains($attendance,"pickup_at[")&&str_contains($attendance,'arrived_at'),
 'draft generation does not queue email'=>!preg_match('/private static function createDraft.*?MailService::queue/s',$service),
 'review workspace requires production management'=>str_contains($experience,'AccessPolicy::canManageProduction($user)'),
 'attendance pickup write requires production management'=>str_contains($attendance,"if(!AccessPolicy::canManageProduction(\$user))throw new RuntimeException('Only production staff can take attendance.')"),
];
foreach($checks as $label=>$passed)if(!$passed)throw new RuntimeException('FAIL: '.$label);
echo 'Late pickup workflow contract checks passed: '.count($checks)."\n";
