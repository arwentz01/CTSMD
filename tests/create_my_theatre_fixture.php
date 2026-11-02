<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/my-theatre-personas.php';
$db=Database::connect(dirname(__DIR__));$fixture=myTheatreFixture($db);unset($fixture['password']);$fixture['isolated']=true;$path=(string)(getenv('CTSMD_MEMBER_FIXTURE')?:sys_get_temp_dir().'/ctsmd-my-theatre-fixture.json');
if(!str_starts_with($path,sys_get_temp_dir().'/'))throw new RuntimeException('Write synthetic fixture metadata only to temporary storage.');
file_put_contents($path,json_encode($fixture,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($path,0600);echo "Created isolated synthetic member fixture: $path\n";
