<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/PlaybillPublicationBuilder.php';

$service=file_get_contents(dirname(__DIR__).'/src/ManualParticipantService.php');
if($service===false)throw new RuntimeException('Manual participant service is unavailable.');
foreach(['INSERT INTO users','INSERT INTO production_memberships','auth_mobile_tokens'] as $forbidden){
    if(stripos($service,$forbidden)!==false)throw new RuntimeException('Manual participant service must not create authentication or membership records: '.$forbidden);
}

$company=[
    ['display_name'=>'Alex Example','role_title'=>'Lead','biography'=>'Performer bio','participant_type'=>'cast'],
    ['display_name'=>'Casey Example','role_title'=>'Lighting Designer','biography'=>'Designer bio','participant_type'=>'creative'],
];
$pages=PlaybillPublicationBuilder::build(['display_title'=>'Test','production_title'=>'Test'],[],$company,[],[]);
$credits=array_values(array_filter($pages,static fn(array $page):bool=>$page['type']==='cast-credits'));
$bios=array_values(array_filter($pages,static fn(array $page):bool=>$page['type']==='cast-bios'));
if(count($credits)!==1||count($credits[0]['items'])!==1||$credits[0]['items'][0]['display_name']!=='Alex Example')throw new RuntimeException('Creative participants leaked into cast character credits.');
$bioNames=[];foreach($bios as $page)foreach($page['items'] as $person)$bioNames[]=$person['display_name'];
if($bioNames!==['Alex Example','Casey Example'])throw new RuntimeException('Approved company biographies were not retained.');

echo "Manual participant boundary tests passed.\n";
