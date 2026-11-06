<?php
declare(strict_types=1);
// Regression checks for non-Commerce, user-visible copy; run alongside PHP lint
// and screen-level browser testing before deployment.
$root=dirname(__DIR__);
$checks=[
    'src/CastingExperience.php'=>[
        'absent'=>['The save endpoint returned HTTP','The server returned HTTP'],
        'present'=>['We couldn’t save your changes. Try again.'],
    ],
    'src/VolunteerDevelopmentExperience.php'=>[
        'absent'=>['<label>Stable code<input','<h4>Current definitions</h4>'],
        'present'=>['Requirement identifier','Volunteer requirements'],
    ],
    'src/RegistrationOperationsExperience.php'=>[
        'absent'=>['<label>Public slug<input'],
        'present'=>['<label>Web address name<input'],
    ],
    'src/FormManagementExperience.php'=>[
        'absent'=>['Definition scope<select','Completion mode<select'],
        'present'=>['Where this form can be used','How members complete this form'],
    ],
    'src/DynamicFormExperience.php'=>[
        'absent'=>['Linked resources stay canonical'],
        'present'=>['you can update a handbook or policy once'],
    ],
    'src/PushExperience.php'=>[
        'absent'=>['<section class="push-diagnostics ops-panel">','>Service-worker notification</button>'],
        'present'=>['<details class="push-diagnostics ops-panel">','Having trouble with notifications?'],
    ],
    'src/LatePickupExperience.php'=>[
        'absent'=>['Save settings and recompute','Queued does not prove delivery.'],
        'present'=>['Save settings and update reviews','has not necessarily been delivered'],
    ],
    'src/ModerationExperience.php'=>[
        'absent'=>['Canonical term<input'],
        'present'=>['Term to moderate<input'],
    ],
    'src/ScheduleCreateExperience.php'=>[
        'absent'=>['same resolved production/group audience'],
        'present'=>['review it before sending'],
    ],
    'src/ProductionDayExperience.php'=>[
        'absent'=>['<span>attendance marks</span>','<span>readiness tasks open</span>'],
        'present'=>['<span>attendance records</span>','<span>unfinished tasks</span>'],
    ],
    'src/VolunteerServiceRecordExperience.php'=>[
        'absent'=>["CTSMD's internal verified service records"],
        'present'=>['confirmed by CTSMD staff'],
    ],
    'mobile/app/(tabs)/more.tsx'=>[
        'absent'=>['Profile and publication consent · website','Production operations and preparation · website'],
        'present'=>['Manage your performer details and consent','Manage production details · opens in browser'],
    ],
];
$count=0;
foreach($checks as $file=>$groups){
    $content=file_get_contents($root.'/'.$file);
    if($content===false)throw new RuntimeException('Cannot read '.$file);
    foreach($groups['absent'] as $phrase){if(str_contains($content,$phrase))throw new RuntimeException('Old UI wording remains: '.$file.' / '.$phrase);$count++;}
    foreach($groups['present'] as $phrase){if(!str_contains($content,$phrase))throw new RuntimeException('Expected UI wording missing: '.$file.' / '.$phrase);$count++;}
}
echo "PASS $count non-Commerce UI copy assertions\n";
