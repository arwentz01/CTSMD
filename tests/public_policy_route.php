<?php
declare(strict_types=1);
// Static routing regression; integration verification remains required after deployment.
$front=file_get_contents(dirname(__DIR__).'/front.php');
$legacy=file_get_contents(dirname(__DIR__).'/src/PrivacyExperience.php');
$public=file_get_contents(dirname(__DIR__).'/src/PublicPolicyExperience.php');
$checks=[
    'privacy route uses maintained policy'=>str_contains($front,"PublicPolicyExperience::render(\$route, \$detectedBasePath)"),
    'legacy privacy forwards to maintained policy'=>str_contains($legacy,"PublicPolicyExperience::render('/privacy',"),
    'no public draft warning in legacy privacy'=>!str_contains($legacy,'Draft for review'),
    'deletion request page remains available'=>str_contains($public,"'/account-deletion'"),
    'privacy policy has no public draft warning'=>!str_contains($public,'Draft for review'),
];
foreach($checks as $label=>$ok){if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS '.$label.PHP_EOL;}
