<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/src/';
$cases=[
 'ProductionPeopleExperience.php'=>[
  'ProductionLifecycleGuard::assertWritable($db,$productionId);',
  "'casting.removed_with_production_membership'",
 ],
 'VolunteerShiftManagementExperience.php'=>[
  "ProductionLifecycleGuard::assertWritable($db,(int)$production['id']);",
  "ProductionLifecycleGuard::assertWritable($db,(int)$signup['production_id']);",
 ],
 'FamilyFormService.php'=>[
  "ProductionLifecycleGuard::assertWritable($db,(int)$context['production_id']);",
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
