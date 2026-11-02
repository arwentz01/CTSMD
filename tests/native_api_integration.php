<?php
declare(strict_types=1);
// Explicit opt-in: local MAMP and disposable tokens/forms/conversation only. No outbound worker.
require_once dirname(__DIR__).'/src/Database.php';
if(getenv('CTSMD_NATIVE_INTEGRATION')!=='1'){echo "SKIP: set CTSMD_NATIVE_INTEGRATION=1 against local demo MAMP.\n";exit;}
if(getenv('APP_ENV')!=='local'||getenv('DB_PORT')!=='8889')throw new RuntimeException('Local MAMP only.');
$db=Database::connect(dirname(__DIR__));$tokens=[];$forms=[];$thread=0;$identity='phase4-test-'.bin2hex(random_bytes(8));$checks=0;
if(!in_array((string)(getenv('DB_HOST')?:'127.0.0.1'),['127.0.0.1','localhost'],true)||getenv('DB_PORT')!=='8889')throw new RuntimeException('Disposable fixtures require local MAMP.');
$assert=static function(bool $ok,string $name)use(&$checks){if(!$ok)throw new RuntimeException($name);$checks++;};
$request=static function(string $path,?string $token,string $method='GET',?array $body=null):array{
    $headers="Accept: application/json\r\n".($token?"Authorization: Bearer ".$token."\r\n":'').($body!==null?"Content-Type: application/json\r\n":'');
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body!==null?json_encode($body):'','ignore_errors'=>true,'timeout'=>15]]);
    $raw=file_get_contents('http://127.0.0.1:8093/api/mobile/'.$path,false,$context);
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);
    return [(int)($match[1]??0),json_decode((string)$raw,true)];
};
$token=static function(int $user)use($db,&$tokens):string{
    $raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);
    $db->prepare('INSERT INTO auth_mobile_tokens(user_id,token_hash,expires_at) VALUES(:user,:hash,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute(['user'=>$user,'hash'=>$hash]);$tokens[]=$hash;return $raw;
};
try{
    $parent=$token(7);$student=$token(6);$staff=$token(13);
    $assert($request('me',null)[0]===401,'Anonymous request must be denied.');
    foreach([$parent,$student,$staff] as $raw)$assert($request('me',$raw)[0]===200,'Active bearer identity should resolve.');
    foreach(['my-family','theatre','channels','messages','notifications','daily-tasks'] as $path)$assert($request($path,$parent)[0]===200,'Native read contract failed: '.$path);
    $assert($request('form-detail?id=999999999',$student)[0]===404,'Forged form ID must not expose data.');
    $assert($request('event?id=999999999',$student)[0]===404,'Forged event ID must not expose data.');
    $assert($request('push-subscription',$parent,'POST',['subscription'=>['fcmToken'=>$identity]])[1]['delivery_type']==='native_pending','Native storage must not imply delivery.');
    $request('push-subscription?identity='.rawurlencode($identity),$student,'DELETE');
    $q=$db->prepare('SELECT user_id FROM mobile_push_subscriptions WHERE identity=:identity');$q->execute(['identity'=>$identity]);
    $assert((int)$q->fetchColumn()===7,'Another user may not remove a device registration.');
    $assert($request('logout',$parent,'POST',['push_identity'=>$identity])[0]===200,'Logout should succeed.');
    $assert($request('me',$parent)[0]===401,'Logged-out bearer must be revoked.');
    $q->execute(['identity'=>$identity]);$assert($q->fetchColumn()===false,'Logout must remove its device registration.');
    $parent=$token(7);
    $closed=$db->query("SELECT id FROM productions WHERE lifecycle_stage IN ('completed','archived') LIMIT 1")->fetchColumn();
    $assert((bool)$closed,'A closed demo production is required.');
    foreach([false,true] as $structured){
        $q=$db->prepare("INSERT INTO forms(production_id,title,form_type,instructions,completion_mode,review_required,active,created_by_user_id) VALUES(:production,:title,'acknowledgment','Integration fixture only','acknowledgment',0,1,13)");
        $q->execute(['production'=>$structured?null:(int)$closed,'title'=>'Phase 4 integration '.bin2hex(random_bytes(5))]);$form=(int)$db->lastInsertId();$forms[]=$form;
        $db->prepare("INSERT INTO form_assignments(form_id,production_id,user_id,subject_user_id,status,assigned_by_user_id) VALUES(:form,:production,7,7,'missing',13)")->execute(['form'=>$form,'production'=>$structured?null:(int)$closed]);
        $assignment=(int)$db->lastInsertId();
        if($structured)$db->prepare("INSERT INTO form_fields(form_id,field_key,label,field_type,required,active) VALUES(:form,'required_answer','Required question','short_text',1,1)")->execute(['form'=>$form]);
        $detail=$request('form-detail?id='.$assignment,$parent);
        $assert($detail[0]===200,'Own assignment should remain readable.');
        $assert($structured?$detail[1]['form']['requires_web']:$detail[1]['form']['read_only'],'Unsupported/closed form state must be explicit.');
        $result=$request('form-submit',$parent,'POST',['assignment_id'=>$assignment,'acknowledgment'=>true]);
        $assert($result[0]===400,'Closed or structured legacy submission must fail on the server.');
        $count=$db->prepare('SELECT COUNT(*) FROM form_submissions WHERE assignment_id=:id');$count->execute(['id'=>$assignment]);$assert((int)$count->fetchColumn()===0,'Rejected submit must not write history.');
    }
    $db->exec("INSERT INTO conversations(subject,conversation_type) VALUES('Phase 4 cutoff fixture','safeguarded')");$thread=(int)$db->lastInsertId();
    foreach([[6,'student'],[7,'guardian'],[13,'adult']] as [$id,$role])$db->prepare('INSERT INTO conversation_participants(conversation_id,user_id,participant_role,guardian_required) VALUES(:c,:u,:r,:g)')->execute(['c'=>$thread,'u'=>$id,'r'=>$role,'g'=>$role==='guardian'?1:0]);
    $db->prepare("INSERT INTO messages(conversation_id,sender_user_id,body,created_at) VALUES(:c,13,'Historical fixture',NOW())")->execute(['c'=>$thread]);$cutoff=(int)$db->lastInsertId();
    $db->prepare("UPDATE conversation_participants SET access_revoked_at=NOW(),history_through_message_id=:cutoff WHERE conversation_id=:c AND user_id=7")->execute(['cutoff'=>$cutoff,'c'=>$thread]);
    $db->prepare("INSERT INTO messages(conversation_id,sender_user_id,body,created_at) VALUES(:c,13,'Later hidden fixture',NOW())")->execute(['c'=>$thread]);
    $history=$request('message-thread?id='.$thread,$parent);
    $assert($history[0]===200&&count($history[1]['messages'])===1,'Historical cutoff must hide newer messages.');
    $assert($history[1]['conversation']['send_allowed']===false,'Revoked participant must receive paused-send state.');
    $assert($request('message-send',$parent,'POST',['conversation_id'=>$thread,'body'=>'Must not send'])[0]===400,'Revoked participant may not send through API.');
    echo "PASS: ".$checks." local native bearer/API checks; no push delivery or native device claim.\n";
}finally{
    if($thread){$db->prepare('DELETE FROM messages WHERE conversation_id=:id')->execute(['id'=>$thread]);$db->prepare('DELETE FROM conversation_participants WHERE conversation_id=:id')->execute(['id'=>$thread]);$db->prepare('DELETE FROM conversations WHERE id=:id')->execute(['id'=>$thread]);}
    foreach($forms as $id){$db->prepare('DELETE FROM form_assignments WHERE form_id=:id')->execute(['id'=>$id]);$db->prepare('DELETE FROM form_fields WHERE form_id=:id')->execute(['id'=>$id]);$db->prepare('DELETE FROM forms WHERE id=:id')->execute(['id'=>$id]);}
    foreach($tokens as $hash)$db->prepare('DELETE FROM auth_mobile_tokens WHERE token_hash=:hash')->execute(['hash'=>$hash]);
    $db->prepare('DELETE FROM mobile_push_subscriptions WHERE identity=:identity')->execute(['identity'=>$identity]);
    echo "Disposable API fixtures and tokens cleaned.\n";
}
