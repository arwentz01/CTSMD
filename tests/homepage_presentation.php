<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/OrganizationBranding.php';
require_once dirname(__DIR__).'/src/PublicExperience.php';
// Render actual public HTML from the CLI, never a browser or a live account.
if(($argv[1]??'')==='--render'){
 putenv('ORGANIZATION_NAME=Another Theatre');putenv('ORGANIZATION_PRIMARY_COLOR=#f0d040');
 $available=($argv[2]??'')==='published';
 $show=['id'=>2,'title'=>'A public production','season'=>'Next season','lifecycle_stage'=>'upcoming'];
 $program=['production_id'=>2,'display_title'=>'A published program','production_title'=>'A public production','public_slug'=>'public-program','subtitle'=>'','artwork_stored_file_id'=>12];
 $signup=['slug'=>'public-audition','title'=>'Open audition','summary'=>'A published opportunity.','is_full'=>false];
 (new ReflectionMethod(PublicExperience::class,'landingPage'))->invoke(null,'/connect',OrganizationBranding::fromEnvironment(),$available?[$show]:[],$available?[$signup]:[],$available?[$program]:[],$available?[$program]:[]);
}
$keys=['NAME','LOGO','PRIMARY_COLOR','ACCENT_COLOR','TAGLINE','WELCOME','URL'];$saved=[];foreach($keys as $key){$key='ORGANIZATION_'.$key;$saved[$key]=getenv($key);putenv($key);}
$ok=static function(bool $v,string $label):void{if(!$v)throw new RuntimeException($label);};
try{
 $first=OrganizationBranding::fromEnvironment();$ok($first['name']==='CTSMD'&&$first['primary']==='#8b233b','Default identity uses existing warm colors.');
 foreach(['NAME'=>'Another Theatre','LOGO'=>'/company/logo.svg','PRIMARY_COLOR'=>'#f0d040','ACCENT_COLOR'=>'#184433','TAGLINE'=>'A different company','WELCOME'=>'Our shared theatre.','URL'=>'https://theatre.example.org'] as $k=>$v)putenv('ORGANIZATION_'.$k.'='.$v);
 $second=OrganizationBranding::fromEnvironment();$ok($second['name']==='Another Theatre'&&$second['logo']==='/company/logo.svg'&&$second['welcome']==='Our shared theatre.'&&$second['website']==='https://theatre.example.org','Second organization supplies identity without source changes.');
 $ok($second['on_primary']==='#000000'&&str_contains(OrganizationBranding::css($second),'--organization-primary:#f0d040;'),'Light brand background gets readable control text.');
 putenv('ORGANIZATION_PRIMARY_COLOR=red;display:none');putenv('ORGANIZATION_LOGO=javascript:alert(1)');putenv('ORGANIZATION_URL=//other.example.org');$bad=OrganizationBranding::fromEnvironment();$ok($bad['primary']===$first['primary']&&$bad['logo']===''&&$bad['website']==='','Invalid colors and unsafe URLs are rejected.');
}finally{foreach($saved as $k=>$v)putenv($v===false?$k:$k.'='.$v);}
foreach(['published','empty'] as $state){
 $html=shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --render '.$state);
 $ok(is_string($html)&&str_ends_with(trim($html),'</html>')&&!preg_match('/Warning:|Fatal error/',$html),'Actual public template renders completely.');
 $ok(str_contains($html,'Another Theatre')&&!str_contains($html,'CTSMD')&&str_contains($html,'--organization-primary:#f0d040;'),'Actual public page is organization-aware.');
 $ok(str_contains($html,'/connect/features')&&str_contains($html,'Performers &amp; Students')&&str_contains($html,'Parents &amp; Guardians'),'Welcome page provides audience context and a features destination.');
 foreach(['/login','/join','/register','/productions'] as $route)$ok(str_contains($html,'href="/connect'.$route.'"'),'Public destination retained: '.$route);
 foreach(['/admin/','/my-theatre','/channels','/messages','production_artwork=1'] as $private)$ok(!str_contains($html,$private),'No private destinations or artwork requests on public page.');
 if($state==='published')$ok(str_contains($html,'/playbill/asset?kind=artwork&amp;slug=public-program')&&str_contains($html,'A public production')&&str_contains($html,'Open audition'),'Published artwork, production and signup rendered.');
 else $ok(!str_contains($html,'/playbill/asset')&&!str_contains($html,'stage-opportunities')&&!str_contains($html,'stage-program-gallery')&&str_contains($html,'Where the whole'),'Empty state welcomes visitors without invented content or empty sections.');
}
$source=file_get_contents(dirname(__DIR__).'/src/PublicExperience.php');
$landing=substr($source,strpos($source,'private static function landing('),strpos($source,'private static function registration(')-strpos($source,'private static function landing('));
foreach(['announcement_published_at IS NOT NULL','pb.public_enabled=1',"pb.editorial_status IN ('published','archived')",'RegistrationService::publicOpportunities','PlaybillEnhancementService::publicAsset'] as $guard)$ok(str_contains($landing,$guard),'Public content uses existing publication gate: '.$guard);
$ok(!str_contains($landing,'production_artwork')&&!str_contains($landing,'production_memberships')&&!str_contains($landing,'CTSMD'),'Landing has no member artwork query, membership data or organization-specific copy.');
echo "PASS: public homepage identity, real CLI HTML, published/empty states, public routes and publication-policy contracts (no browser or database).\n";
