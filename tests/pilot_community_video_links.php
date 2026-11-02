<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/CommunityExperience.php';
require_once dirname(__DIR__).'/src/RehearsalVideoExperience.php';
function expectLink(bool $ok,string $name):void{
 if(!$ok)throw new RuntimeException('Community video link: '.$name);
 echo "PASS: ".$name."\n";
}
$body="Please rehearse [Belle Dance](https://ctsmd.me/videos/view?id=42) tonight.";
$html=CommunityExperience::renderPostBody($body);
expectLink(str_contains($html,'<a class="community-video-link" href="/videos/view?id=42">Belle Dance</a>'),'video link has clean clickable title');
expectLink(!str_contains($html,'https://ctsmd.me/videos/view'),'the long URL is not shown in Community');
expectLink(CommunityExperience::renderPostBody('[Blocking](/videos/view?id=7)')==='<a class="community-video-link" href="/videos/view?id=7">Blocking</a>','relative CTSMD video links work');
$unsafe=CommunityExperience::renderPostBody('[Click](https://example.com/videos/view?id=1) <script>alert(1)</script>');
expectLink(!str_contains($unsafe,'<a class=')&&str_contains($unsafe,'&lt;script&gt;'),'external links and HTML remain non-executable');
expectLink(RehearsalVideoExperience::handles('/videos/view'),'video detail route is recognized');

$pdo=new class extends PDO {public function __construct() {}};
$staff=['id'=>1,'role'=>'administrator','roles'=>['administrator'],'permissions'=>['production.manage']];
$student=['id'=>2,'role'=>'student','roles'=>['student'],'permissions'=>[]];
$restricted=['status'=>'active','audience_scope'=>'group','production_id'=>7,'group_id'=>123];
expectLink(RehearsalVideoExperience::canOpenVideo($pdo,$staff,$restricted),'authorized staff can open group video without separate student roster membership');
expectLink(!RehearsalVideoExperience::canOpenVideo($pdo,$staff,['status'=>'archived']+$restricted),'archived video remains unavailable from shared link');
expectLink(RehearsalVideoExperience::canOpenVideo($pdo,$student,['status'=>'active','audience_scope'=>'platform']+$restricted),'student can open active organization-wide video');
expectLink(!RehearsalVideoExperience::canOpenVideo($pdo,$student,['status'=>'archived','audience_scope'=>'platform']+$restricted),'student cannot open archived shared video');
