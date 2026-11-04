<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Database.php';
if(getenv('CTSMD_COMMUNITY_TEST_DB')!=='1'||getenv('DB_HOST')!=='127.0.0.1'||!str_starts_with((string)getenv('DB_DATABASE'),'ctsmd_rc1_')){echo "SKIP: isolated local test database required.\n";exit;}
$db=Database::connect(dirname(__DIR__));$userIds=[];$channelIds=[];$production=0;$tokens=[];
$insert=static function(string $sql,array $values)use($db):int{$s=$db->prepare($sql);$s->execute($values);return (int)$db->lastInsertId();};
try{
 foreach(['admin','member','outsider'] as $name){$id=$insert("INSERT INTO users(first_name,last_name,email,initials,display_role,active,account_status,organization_membership_status) VALUES (?, 'Feed API', ?, 'FA','Member',1,'active','approved')",[$name,$name.'.'.bin2hex(random_bytes(6)).'@example.invalid']);$userIds[$name]=$id;$role=$name==='admin'?'administrator':'member';$s=$db->prepare('INSERT INTO auth_user_roles(user_id,role_id) SELECT ?,id FROM auth_roles WHERE code=? AND active=1');$s->execute([$id,$role]);if(!$s->rowCount())throw new RuntimeException('Missing test role');$raw=bin2hex(random_bytes(32));$insert('INSERT INTO auth_mobile_tokens(user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))',[$id,hash('sha256',$raw)]);$tokens[$name]=$raw;}
 $production=$insert("INSERT INTO productions(title,is_active,lifecycle_stage) VALUES ('Feed API test',1,'performing')",[]);
 $insert("INSERT INTO production_memberships(production_id,user_id,audience_type,status) VALUES (?,?,'staff','active')",[$production,$userIds['member']]);
 $channelIds[]=$insert("INSERT INTO channels(name,channel_type,read_scope,post_scope,include_in_feed) VALUES ('API organization test','general','all_members','staff',0)",[]);
 $channelIds[]=$insert("INSERT INTO channels(name,channel_type,read_scope,post_scope,production_id,include_in_feed) VALUES ('API production test','production','production_members','staff',?,0)",[$production]);
 $channelIds[]=$insert("INSERT INTO channels(name,channel_type,read_scope,post_scope,include_in_feed) VALUES ('API staff test','general','staff','staff',0)",[]);
 foreach($channelIds as $i=>$channel)$insert("INSERT INTO channel_posts(channel_id,author_user_id,body,created_at) VALUES (?,? ,?,'2099-01-01 00:00:00')",[$channel,$userIds['admin'],'API fixture '.$i]);
 $get=static function(string $token):array{$context=stream_context_create(['http'=>['header'=>"Authorization: Bearer $token\r\nAccept: application/json\r\n",'ignore_errors'=>true,'timeout'=>10]]);$body=file_get_contents('http://127.0.0.1:8099/api/mobile/community-feed',false,$context);$json=json_decode((string)$body,true);if(!is_array($json)||!isset($json['posts']))throw new RuntimeException('API did not return a feed');return array_values(array_filter($json['posts'],fn($p)=>str_starts_with($p['body'],'API fixture ')));};
 foreach(['admin'=>3,'member'=>2,'outsider'=>1] as $name=>$expected){$posts=$get($tokens[$name]);if(count($posts)!==$expected)throw new RuntimeException("$name API visibility failed: ".count($posts));echo "PASS: $name bearer API sees $expected authorized channel posts\n";}
}finally{
 foreach($channelIds as $id){$db->prepare('DELETE FROM channel_posts WHERE channel_id=?')->execute([$id]);$db->prepare('DELETE FROM channels WHERE id=?')->execute([$id]);}
 if($production){$db->prepare('DELETE FROM production_memberships WHERE production_id=?')->execute([$production]);$db->prepare('DELETE FROM productions WHERE id=?')->execute([$production]);}
 foreach($userIds as $id){$db->prepare('DELETE FROM auth_mobile_tokens WHERE user_id=?')->execute([$id]);$db->prepare('DELETE FROM auth_user_roles WHERE user_id=?')->execute([$id]);$db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
}
