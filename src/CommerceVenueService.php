<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceStore.php';
final class CommerceVenueService
{
    public static function venue(PDO $db,array $user,int $org,string $name,string $location):int
    {
        CommerceAccess::demand($db,$user,$org,'venues');$name=trim($name);if($name===''||mb_strlen($name)>190||mb_strlen($location)>500)throw new RuntimeException('Enter a venue name and location within the field limits.');
        return CommerceStore::tx($db,function()use($db,$org,$user,$name,$location){$db->prepare('INSERT INTO commerce_venues(organization_id,name,location) VALUES(?,?,?)')->execute([$org,$name,$location]);$id=(int)$db->lastInsertId();CommerceStore::audit($db,$org,(int)$user['id'],'commerce.venue_created','venue',$id);return$id;});
    }
    public static function space(PDO $db,array $user,int $org,int $venue,string $name):int
    {
        CommerceAccess::demand($db,$user,$org,'venues');CommerceStore::row($db,'venues',$org,$venue);$name=trim($name);if($name===''||mb_strlen($name)>190)throw new RuntimeException('Enter the auditorium name.');
        return CommerceStore::tx($db,function()use($db,$org,$user,$venue,$name){$db->prepare('INSERT INTO commerce_spaces(organization_id,venue_id,name) VALUES(?,?,?)')->execute([$org,$venue,$name]);$id=(int)$db->lastInsertId();$doc=['sections'=>[['key'=>'main','name'=>'Main','order'=>0]],'rows'=>[['key'=>'a','section'=>'main','name'=>'A','order'=>0]],'items'=>[['key'=>'stage','kind'=>'stage','label'=>'Stage','x'=>180,'y'=>20]]];$db->prepare('INSERT INTO commerce_layouts(organization_id,space_id,version_number,document_json) VALUES(?,?,1,?)')->execute([$org,$id,json_encode($doc)]);CommerceStore::audit($db,$org,(int)$user['id'],'commerce.space_created','space',$id);return$id;});
    }
    public static function validate(array $doc):array
    {
        if(!isset($doc['sections'],$doc['rows'],$doc['items'])||!is_array($doc['sections'])||!is_array($doc['rows'])||!is_array($doc['items'])||count($doc['items'])>1500||count($doc['sections'])>100||count($doc['rows'])>300)throw new RuntimeException('Layouts support up to 1500 items, 300 rows and 100 sections.');
        $keys=[];$sections=[];$rows=[];$labels=[];
        foreach($doc['sections'] as &$s){if(!is_array($s))throw new RuntimeException('Invalid section document.');self::key($s['key']??'');self::label($s['name']??'');$s['name']=trim($s['name']);if(isset($sections[$s['key']]))throw new RuntimeException('Section keys must be unique.');$sections[$s['key']]=true;$s=['key'=>$s['key'],'name'=>$s['name'],'order'=>(int)($s['order']??0)];}unset($s);
        foreach($doc['rows'] as &$r){if(!is_array($r))throw new RuntimeException('Invalid row document.');self::key($r['key']??'');self::label($r['name']??'');$r['name']=trim($r['name']);if(isset($rows[$r['key']])||!isset($sections[$r['section']??'']))throw new RuntimeException('Rows need unique keys and a valid section.');$rows[$r['key']]=$r['section'];$r=['key'=>$r['key'],'section'=>$r['section'],'name'=>$r['name'],'order'=>(int)($r['order']??0)];}unset($r);
        foreach($doc['items'] as &$s){if(!is_array($s))throw new RuntimeException('Invalid seat document.');self::key($s['key']??'');self::label($s['label']??'');$s['label']=trim($s['label']);if(isset($keys[$s['key']]))throw new RuntimeException('Seat/item keys must be unique.');$keys[$s['key']]=true;
            if(!in_array($s['kind']??'',['seat','aisle','space','stage','interpreter'],true))throw new RuntimeException('Choose seat, aisle, space, stage or ASL interpreter marker.');
            foreach(['x','y'] as $axis)if(!is_numeric($s[$axis]??null)||$s[$axis]<0||$s[$axis]>5000)throw new RuntimeException('Positions must be between 0 and 5000.');
            if($s['kind']==='seat'){
                if(!isset($rows[$s['row']??''])||$rows[$s['row']]!==($s['section']??''))throw new RuntimeException('Each seat needs a valid section and row.');
                $identity=$s['row'].'/'.$s['label'];if(isset($labels[$identity]))throw new RuntimeException('Seat numbers must be unique within a row.');$labels[$identity]=true;self::key($s['zone']??'');if(mb_strlen((string)($s['description']??''))>500)throw new RuntimeException('Accessible/restricted-view descriptions are limited to 500 characters.');
                $s=['key'=>$s['key'],'kind'=>'seat','label'=>$s['label'],'x'=>(float)$s['x'],'y'=>(float)$s['y'],'row'=>$s['row'],'section'=>$s['section'],'zone'=>$s['zone'],'wheelchair'=>!empty($s['wheelchair']),'companion'=>(string)($s['companion']??''),'restricted'=>!empty($s['restricted']),'description'=>(string)($s['description']??'')];
            }else{
                $width=$s['width']??($s['kind']==='stage'?280:($s['kind']==='interpreter'?160:100));$height=$s['height']??($s['kind']==='stage'?60:40);foreach([$width,$height] as $dimension)if(!is_numeric($dimension)||!is_finite((float)$dimension)||$dimension<24||$dimension>1000)throw new RuntimeException('Marker width and height must be between 24 and 1000.');$description=$s['description']??'';if(!is_string($description)||mb_strlen($description)>500)throw new RuntimeException('Marker descriptions are limited to 500 characters.');
                $s=['key'=>$s['key'],'kind'=>$s['kind'],'label'=>$s['label'],'x'=>(float)$s['x'],'y'=>(float)$s['y'],'width'=>(float)$width,'height'=>(float)$height,'description'=>$description];
            }
        }unset($s);
        foreach($doc['items'] as $s)if($s['kind']==='seat'&&$s['companion']!==''){
            $target=null;foreach($doc['items'] as $t)if($t['key']===$s['companion'])$target=$t;
            if(!$target||$target['kind']!=='seat'||empty($target['wheelchair'])||$target['key']===$s['key'])throw new RuntimeException('Companion seats must reference a wheelchair position in this layout.');
        }
        return$doc;
    }
    private static function key(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$v))throw new RuntimeException('Keys use 1–80 letters, numbers, underscores or hyphens.');}
    private static function label(mixed $v):void{if(!is_string($v)||trim($v)===''||mb_strlen($v)>100)throw new RuntimeException('Labels must contain 1–100 characters.');}
    public static function save(PDO $db,array $user,int $org,int $id,array $doc,string $expected):void
    {
        CommerceAccess::demand($db,$user,$org,'venues');$doc=self::validate($doc);
        CommerceStore::tx($db,function()use($db,$user,$org,$id,$doc,$expected){$row=CommerceStore::row($db,'layouts',$org,$id,true);if($row['status']!=='draft')throw new RuntimeException('Published layouts cannot change. Create a new version.');if(!hash_equals(hash('sha256',$row['document_json']),$expected))throw new RuntimeException('Someone else saved this layout. Reload before editing.');$db->prepare('UPDATE commerce_layouts SET document_json=? WHERE id=? AND organization_id=?')->execute([json_encode($doc,JSON_THROW_ON_ERROR),$id,$org]);CommerceStore::audit($db,$org,(int)$user['id'],'commerce.layout_saved','layout',$id);});
    }
    public static function publish(PDO $db,array $user,int $org,int $id):void
    {
        CommerceAccess::demand($db,$user,$org,'venues');CommerceStore::tx($db,function()use($db,$user,$org,$id){$r=CommerceStore::row($db,'layouts',$org,$id,true);$doc=self::validate(json_decode($r['document_json'],true,flags:JSON_THROW_ON_ERROR));if(!array_filter($doc['items'],fn($s)=>$s['kind']==='seat'))throw new RuntimeException('Add at least one seat before publishing.');if(!array_filter($doc['items'],fn($s)=>$s['kind']==='stage'))throw new RuntimeException('Add a stage indicator before publishing.');if($r['status']!=='draft')throw new RuntimeException('This version is already published.');$db->prepare("UPDATE commerce_layouts SET status='published',published_at=NOW() WHERE id=? AND organization_id=?")->execute([$id,$org]);CommerceStore::audit($db,$org,(int)$user['id'],'commerce.layout_published','layout',$id);});
    }
    public static function cloneVersion(PDO $db,array $user,int $org,int $id):int
    {
        CommerceAccess::demand($db,$user,$org,'venues');$r=CommerceStore::row($db,'layouts',$org,$id);
        return CommerceStore::tx($db,function()use($db,$user,$org,$r){CommerceStore::row($db,'spaces',$org,(int)$r['space_id'],true);$q=$db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM commerce_layouts WHERE organization_id=? AND space_id=?');$q->execute([$org,$r['space_id']]);$version=(int)$q->fetchColumn();$db->prepare('INSERT INTO commerce_layouts(organization_id,space_id,version_number,document_json) VALUES(?,?,?,?)')->execute([$org,$r['space_id'],$version,$r['document_json']]);$id=(int)$db->lastInsertId();CommerceStore::audit($db,$org,(int)$user['id'],'commerce.layout_cloned','layout',$id,['source'=>$r['id']]);return$id;});
    }
}
