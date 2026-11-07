<?php
declare(strict_types=1);

// /app household action uses existing linked children from HomeDashboardService.
$source=file_get_contents(dirname(__DIR__).'/src/HomeExperience.php');
if($source===false)throw new RuntimeException('Home template unavailable.');
$expected="$"."children ? ['label'=>'Manage household','href'=>"."$"."u('/family/manage')] : ['label'=>'Set up household','href'=>"."$"."u('/family/manage')]";
if(!str_contains($source,$expected))throw new RuntimeException('Household action must say Manage household when children are linked and Set up household otherwise.');
if(!str_contains($source,"$"."children=$"."data['children']"))throw new RuntimeException('Household action must use existing linked children data.');
echo "PASS: /app household action switches between setup and manage based on linked children.\n";
