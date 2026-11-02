<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/PlaybillPublicationBuilder.php';

$checks=0;$assert=static function(bool $condition,string $message)use(&$checks):void{++$checks;if(!$condition)throw new RuntimeException('FAIL: '.$message);};
$cast=[];for($i=1;$i<=53;++$i)$cast[]=['user_id'=>$i,'display_name'=>'Student '.$i,'role_title'=>'Role '.$i,'biography'=>str_repeat('A complete approved biography sentence. ',($i%5)+3),'headshot_stored_file_id'=>null,'photographer_credit'=>null];
$staff=[];for($i=1;$i<=37;++$i)$staff[]=['name'=>'Team Member '.$i,'participation_role'=>'Department '.$i];
$sections=[['id'=>4,'heading'=>'Director note','section_type'=>'director_note','body'=>str_repeat("A thoughtful paragraph about the production and its company. ",160)."\n\n".str_repeat('A second paragraph remains complete. ',120),'sort_order'=>10]];
$sponsors=[['id'=>1,'name'=>'Premium','tier'=>'premium','placement_format'=>'full_page'],['id'=>2,'name'=>'Regular A','tier'=>'regular','placement_format'=>'card'],['id'=>3,'name'=>'Regular B','tier'=>'regular','placement_format'=>'card']];
$pb=['display_title'=>'Pagination Test','production_title'=>'Pagination Test','website_url'=>'https://example.test','upcoming_url'=>null,'donate_url'=>null];
$pages=PlaybillPublicationBuilder::build($pb,$sections,$cast,$staff,$sponsors);
$assert($pages[0]['type']==='cover','Cover is always page one.');
$assert($pages[1]['type']==='contents','There is one canonical contents page after the cover.');
$assert(count(array_filter($pages,static fn(array $p):bool=>$p['type']==='contents'))===1,'Contents page is not duplicated.');
$creditIds=[];$bioIds=[];foreach($pages as $page){if($page['type']==='cast-credits')foreach($page['items'] as $m)$creditIds[]=$m['user_id'];if($page['type']==='cast-bios')foreach($page['items'] as $m)$bioIds[]=$m['user_id'];if($page['type']==='editorial')$assert(mb_strlen($page['body'])<=2600,'Editorial continuation stays within the page text budget.');}
$assert($creditIds===range(1,53),'Long cast credits paginate once and in order.');
$assert($bioIds===range(1,53),'Long biographies paginate once and in order.');
$assert(count(array_filter($pages,static fn(array $p):bool=>$p['type']==='team'))===2,'Long production team paginates.');
$assert(count(array_filter($pages,static fn(array $p):bool=>$p['type']==='editorial'))>1,'Long editorial copy creates continuation pages.');
$assert(count(array_filter($pages,static fn(array $p):bool=>$p['type']==='sponsors'))===2,'Premium and regular sponsors receive deliberate pages.');
$assert(count(array_filter($pages[1]['items'],static fn(array $item):bool=>str_starts_with($item['title'],'Our sponsors')))===1,'Sponsor contents entry is not duplicated.');
$assert($pages[array_key_last($pages)]['type']==='closing','Configured engagement links create the closing page.');
foreach($pages[1]['items'] as $item)$assert($item['page']>=3&&$item['page']<=count($pages),'Every contents entry targets an existing publication page.');
echo "Playbill pagination checks passed: {$checks}; pages built: ".count($pages)."\n";
