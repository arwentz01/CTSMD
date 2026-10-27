<?php
declare(strict_types=1);

/**
 * No-database video visibility regression checks.
 * Run: php tests/pilot_video_privacy.php
 */
require_once dirname(__DIR__).'/src/RehearsalVideoService.php';

final class VideoTestPDO extends PDO
{
    public array $sql=[];
    public function __construct() {}
    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        $this->sql[]=$query;
        return new VideoTestStatement();
    }
}
final class VideoTestStatement extends PDOStatement
{
    public function __construct() {}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return false;}
}
function videoAssert(bool $ok,string $label):void
{
    if(!$ok)throw new RuntimeException('Video privacy regression: '.$label);
    echo "PASS: ".$label."\n";
}
$db=new VideoTestPDO();
$user=['id'=>77,'role'=>'student'];
$base=['id'=>4,'status'=>'active','production_id'=>5,'group_id'=>null,'audience_scope'=>'production'];
videoAssert(RehearsalVideoService::canView($db,$user,$base+[])===false,'production video requires current production membership');
$platform=$base;$platform['audience_scope']='platform';$platform['production_id']=null;
videoAssert(RehearsalVideoService::canView($db,$user,$platform)===true,'eligible authenticated viewer may see CTSMD-wide video');
$platform['status']='archived';
videoAssert(RehearsalVideoService::canView($db,$user,$platform)===false,'archived platform media is denied');
$group=$base;$group['audience_scope']='group';
videoAssert(RehearsalVideoService::canView($db,$user,$group)===false,'deleted group must not turn video into production-wide content');
$group['group_id']=123;
videoAssert(RehearsalVideoService::canView($db,$user,$group)===false,'inactive group denies playback');
videoAssert((bool)array_filter($db->sql,static fn(string $q):bool=>str_contains($q,'production_groups')&&str_contains($q,'active=1')),'group existence and activity checked');
$valid=RehearsalVideoService::absolute('rehearsal-videos/5-a-christmas-carol/2026/20261027-123000-dance-a1b2c3d4e5f6.mp4');
videoAssert(str_contains($valid,'/storage/private/rehearsal-videos/'),'videos resolve into private storage');
videoAssert(str_contains(RehearsalVideoService::absolute('rehearsal-videos/ctsmd-platform/2026/20261027-123000-orientation-a1b2c3d4e5f6.mp4'),'/ctsmd-platform/'),'organization video path is supported');
foreach(['../../.env','rehearsal-videos/ctsmd-platform/2026/../../.env','rehearsal-videos/5-a/2026/video.php'] as $unsafe){
    try{RehearsalVideoService::absolute($unsafe);throw new RuntimeException('Unsafe path accepted: '.$unsafe);}
    catch(RuntimeException $e){if(str_starts_with($e->getMessage(),'Unsafe path accepted'))throw $e;}
}
videoAssert(true,'path traversal and disallowed extensions rejected');

$limits=RehearsalVideoService::hostingLimits();
videoAssert(isset($limits['effective_bytes'])&&is_int($limits['effective_bytes'])&&$limits['effective_bytes']>=0,'hosting diagnostics report effective byte limits');
$logAudit=new ReflectionMethod(RehearsalVideoService::class,'audit');
$logAudit->invoke(null,$db,77,'rehearsal.video_uploaded',15,null);
videoAssert(true,'CTSMD-wide uploads support a null production ID in audit events');
