<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/CommunityExperience.php';
if(getenv('CTSMD_COMMUNITY_TEST_DB')!=='1'||getenv('DB_HOST')!=='127.0.0.1'||!str_starts_with((string)getenv('DB_DATABASE'),'ctsmd_rc1_')){echo "SKIP: set CTSMD_COMMUNITY_TEST_DB=1 for an isolated test database.\n";exit;}
$db=Database::connect(dirname(__DIR__));$db->beginTransaction();
try {
 $insert=static function(string $sql,array $values)use($db):int{$s=$db->prepare($sql);$s->execute($values);return (int)$db->lastInsertId();};
 $users=[];foreach(['admin','member','outsider'] as $name){$id=$insert("INSERT INTO users(first_name,last_name,email,initials,display_role,active,account_status,organization_membership_status) VALUES (?, 'Feed Test', ?, 'FT', 'Member',1,'active','approved')",[$name,$name.'.'.bin2hex(random_bytes(6)).'@example.invalid']);$users[$name]=['id'=>$id,'roles'=>$name==='admin'?['administrator']:['member'],'organization_membership_status'=>'approved'];}
 $production=$insert("INSERT INTO productions(title,is_active,lifecycle_stage) VALUES ('Feed test',1,'performing')",[]);
 $insert("INSERT INTO production_memberships(production_id,user_id,audience_type,status) VALUES (?,?,'staff','active')",[$production,$users['member']['id']]);
 $general=$insert("INSERT INTO channels(name,channel_type,read_scope,post_scope,include_in_feed) VALUES ('Feed organization test','general','all_members','staff',0)",[]);
 $restricted=$insert("INSERT INTO channels(name,channel_type,read_scope,post_scope,production_id,include_in_feed) VALUES ('Feed production test','production','production_members','staff',?,0)",[$production]);
 $staff=$insert("INSERT INTO channels(name,channel_type,read_scope,post_scope,include_in_feed) VALUES ('Feed staff test','general','staff','staff',0)",[]);
 $all=[];for($i=0;$i<75;$i++){$channel=[$general,$restricted,$staff][$i%3];$id=$insert("INSERT INTO channel_posts(channel_id,author_user_id,body,created_at) VALUES (?,?,?,'2099-01-01 00:00:00')",[$channel,$users['admin']['id'],'Fixture '.$i]);$all[$id]=$channel;}
 $fetch=static function(array $user)use($db):array{$all=[];$cursor=null;do{$result=CommunityExperience::activityFeed($db,$user,0,$cursor);foreach($result['posts'] as $post){if(str_starts_with($post['body'],'Fixture '))$all[]=$post;}$cursor=$result['next_cursor'];}while($cursor);return $all;};
 $admin=$fetch($users['admin']);$member=$fetch($users['member']);$outsider=$fetch($users['outsider']);
 $check=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "PASS: $message\n";};
 $check(count($admin)===75&&count(array_unique(array_column($admin,'id')))===75,'administrator sees unique posts across all three channels');
 $check(count($member)===50&&count(array_filter($member,fn($p)=>(int)$p['channel_id']===$restricted))===25,'production member sees organization and production posts');
 $check(count($outsider)===25&&count(array_filter($outsider,fn($p)=>(int)$p['channel_id']!==$general))===0,'nonmember cannot retrieve production or staff posts');
 $ids=array_map('intval',array_column($admin,'id'));$sorted=$ids;rsort($sorted);$check($ids===$sorted,'same-timestamp posts use globally descending ID order');
 $first=CommunityExperience::activityFeed($db,$users['admin']);$new=$insert("INSERT INTO channel_posts(channel_id,author_user_id,body,created_at) VALUES (?,?,'New arrival','2099-01-02 00:00:00')",[$general,$users['admin']['id']]);$second=CommunityExperience::activityFeed($db,$users['admin'],0,$first['next_cursor']);$ids1=array_column($first['posts'],'id');$ids2=array_column($second['posts'],'id');$check(count(array_intersect($ids1,$ids2))===0&&!in_array($new,$ids2),'cursor remains stable when a newer post arrives');
 $db->rollBack();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
