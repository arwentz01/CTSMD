<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/OrganizationBranding.php';
require_once dirname(__DIR__).'/src/HomeExperience.php';
// CLI-only HTML rendering exercises the actual template without a browser or database.
if(($argv[1]??'')==='--render'){
 Auth::startSession();
 $approved=($argv[2]??'')==='approved';putenv('ORGANIZATION_NAME=Another Theatre');putenv('ORGANIZATION_PRIMARY_COLOR=#f0d040');
 $viewer=['id'=>991,'first_name'=>'Alex','last_name'=>'Member','name'=>'Alex Member','initials'=>'AM','email'=>'alex@example.test','role'=>'Member','roles'=>['member'],'permissions'=>[],'organization_membership_status'=>$approved?'approved':'pending'];
 $cache=new ReflectionProperty(AppNavigation::class,'sidebarContextCache');$cache->setValue(null,['991:member:archive-own'=>['productionOptions'=>[],'selectedProduction'=>null,'hasFamily'=>false,'hasActiveProduction'=>$approved,'hasArchive'=>false,'unread'=>['community'=>0,'messages'=>0]]]);
 $show=['id'=>2,'title'=>'A different production','season'=>'Next season','artwork_role'=>'banner'];
 $data=['membership'=>['approved'=>$approved],'productions'=>$approved?[$show]:[],'artworks'=>$approved?[$show]:[],'attention'=>[],'next_event'=>null];
 (new ReflectionMethod(HomeExperience::class,'page'))->invoke(null,'/connect',$viewer,$data,OrganizationBranding::fromEnvironment());
}
$keys=['NAME','LOGO','PRIMARY_COLOR','ACCENT_COLOR','TAGLINE','WELCOME','URL'];$saved=[];foreach($keys as $key){$key='ORGANIZATION_'.$key;$saved[$key]=getenv($key);putenv($key);}
$ok=static function(bool $v,string $label):void{if(!$v)throw new RuntimeException($label);};
try{
 $first=OrganizationBranding::fromEnvironment();$ok($first['name']==='CTSMD'&&$first['primary']==='#8b233b','Default organization identity uses existing warm colors.');
 foreach(['NAME'=>'Another Theatre','LOGO'=>'/company/logo.svg','PRIMARY_COLOR'=>'#f0d040','ACCENT_COLOR'=>'#184433','TAGLINE'=>'A different company','WELCOME'=>'Our shared rehearsal room.','URL'=>'https://theatre.example.org'] as $k=>$v)putenv('ORGANIZATION_'.$k.'='.$v);
 $second=OrganizationBranding::fromEnvironment();$ok($second['name']==='Another Theatre'&&$second['logo']==='/company/logo.svg'&&$second['welcome']==='Our shared rehearsal room.'&&$second['website']==='https://theatre.example.org','Second organization supplies identity without changing homepage source.');
 $ok($second['on_primary']==='#000000'&&str_contains(OrganizationBranding::css($second),'--organization-primary:#f0d040;'),'Light brand background gets readable control text.');
 putenv('ORGANIZATION_PRIMARY_COLOR=red;display:none');putenv('ORGANIZATION_LOGO=javascript:alert(1)');putenv('ORGANIZATION_URL=//other.example.org');$bad=OrganizationBranding::fromEnvironment();$ok($bad['primary']===$first['primary']&&$bad['logo']===''&&$bad['website']==='','Invalid colors and unsafe URLs are rejected.');
 $source=file_get_contents(dirname(__DIR__).'/src/HomeExperience.php');$ok(!str_contains($source,'CTSMD')&&!str_contains($source,'Southern Maryland'),'Reusable homepage has no organization-specific copy.');
 $ok(str_contains($source,'HomeDashboardService::build($db,$user)')&&str_contains($source,'Auth::currentUser($db)')&&str_contains($source,'production_artwork=1'),'Existing authentication, authorized data and artwork delivery remain in use.');
}finally{foreach($saved as $k=>$v)putenv($v===false?$k:$k.'='.$v);}
final class HomeArtStatement extends PDOStatement {
 public array $ids=[];
 public function __construct(private array $rows){}
 public function execute(?array $params=null):bool{$this->ids=$params;return true;}
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
}
final class HomeArtPDO extends PDO {
 public ?HomeArtStatement $statement=null;public string $sql='';
 public function __construct(private array $rows){}
 public function prepare(string $query,array $options=[]):PDOStatement|false{$this->sql=$query;return $this->statement=new HomeArtStatement($this->rows);}
}
$method=new ReflectionMethod(HomeExperience::class,'artworks');$db=new HomeArtPDO([]);$ok($method->invoke(null,$db,[])===[]&&$db->sql==='','No productions needs no artwork query.');
$shows=[['id'=>2,'title'=>'First'],['id'=>3,'title'=>'Second']];$db=new HomeArtPDO([['production_id'=>2,'role'=>'poster'],['production_id'=>2,'role'=>'banner'],['production_id'=>3,'role'=>'banner'],['production_id'=>999,'role'=>'poster']]);$art=$method->invoke(null,$db,$shows);
$ok($db->statement->ids===[2,3]&&array_column($art,'id')===[2,3],'Artwork lookup uses only authorized production IDs and ignores unrelated results.');
$ok($art[0]['artwork_role']==='poster'&&$art[1]['artwork_role']==='banner','Poster preference and banner-only production are retained.');
$ok(str_contains($db->sql,"v.mime_type IN ('image/jpeg','image/png','image/webp')"),'Artwork metadata is limited to supported image types.');
foreach(['approved','pending'] as $state){
 $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --render '.$state;
 $html=shell_exec($command);$ok(is_string($html)&&str_ends_with(trim($html),'</html>')&&!preg_match('/Warning:|Fatal error/',$html),'Actual homepage template renders completely.');
 $content=substr($html,strpos($html,'<div class="theatre-home"'));
 $ok(str_contains($content,'Another Theatre')&&!str_contains($content,'CTSMD')&&str_contains($content,'--organization-primary:#f0d040;'),'Second organization reaches actual homepage markup.');
 $ok(!str_contains($content,'href="/connect/admin/operations"')&&!str_contains($content,'href="/connect/production"'),'Member receives no privileged workspace links.');
 if($state==='approved')$ok(str_contains($content,'production_artwork=1&id=2&role=banner')&&str_contains($content,'href="/connect/channels"'),'Authorized artwork delivery and Community destinations retained.');
 else $ok(!str_contains($content,'production_artwork=1')&&!str_contains($content,'href="/connect/channels"')&&str_contains($content,'theatre-home-imprint'),'Pending member gets type-only fallback and permitted destinations.');
}
echo "PASS: homepage organization defaults/second-brand contract, safe identity inputs, artwork scoping and empty/banner/poster states (no browser or real database).\n";
