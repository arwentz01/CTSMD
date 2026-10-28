<?php
declare(strict_types=1);
/** Real local HTTP + migrated MySQL. No supplied URL, live personas, reset or worker. */
require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/ReleaseCandidate.php';
require_once dirname(__DIR__).'/src/CalendarService.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE')))throw new RuntimeException('Disposable loopback rc1-test database required. No live target supported.');
$root=dirname(__DIR__);$db=Database::connect($root);
if(!ReleaseCandidate::inspect($db,$root)['schema_matches_declared_contracts'])throw new RuntimeException('Migrate a fresh disposable schema with rc1_bootstrap.php first.');
$suffix=bin2hex(random_bytes(6));$password=bin2hex(random_bytes(24));$users=[];$tokens=[];$cookies=[];$checks=[];$process=null;$pipes=[];
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks[]=$label;};
$insert=static function(string $table,array $values)use($db):int{
 $columns=array_keys($values);$q=$db->prepare('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');$q->execute(array_values($values));return(int)$db->lastInsertId();
};
$csrf=static function(string $html,?string $field=null,?string $value=null):string{
 $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
 $query='//form'.($field?"[.//input[@name='$field'".($value!==null?" and @value='$value'":'').']]':'')."//input[@name='csrf_token']/@value";
 $node=$xpath->query($query)->item(0);if(!$node)throw new RuntimeException('Expected CSRF field absent.');return $node->nodeValue;
};
try{
 // Two students make household aggregation and unrelated-production denial distinct.
 foreach(['Administrator'=>'administrator','Staff'=>'production_staff','Guardian'=>'member','FormerGuardian'=>'member','Student'=>'student','StudentB'=>'student','Caregiver'=>'member','Volunteer'=>'volunteer'] as $label=>$role){
  $id=$insert('users',['first_name'=>'RC1','last_name'=>$label,'email'=>strtolower($label).'.'.$suffix.'@rc1.invalid','initials'=>'RC','display_role'=>$label,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'account_status'=>'active','active'=>1,'organization_membership_status'=>'approved','email_verified_at'=>date('Y-m-d H:i:s')]);$users[$label]=$id;
  $db->prepare('INSERT INTO auth_user_roles(user_id,role_id) SELECT :user,id FROM auth_roles WHERE code=:role')->execute(['user'=>$id,'role'=>$role]);
 }
 foreach([['Guardian','Student','guardian'],['Guardian','StudentB','guardian'],['FormerGuardian','Student','guardian'],['Caregiver','Student','caregiver']] as [$adult,$child,$type])$insert('family_relationships',['guardian_user_id'=>$users[$adult],'student_user_id'=>$users[$child],'relationship_type'=>$type,'is_primary'=>$adult==='Guardian'?1:0,'status'=>'active']);
 $productions=[];$events=[];$assignments=[];$forms=[];
 foreach(['A'=>'Student','B'=>'StudentB'] as $label=>$student){
  $p=$insert('productions',['title'=>'RC1 '.$suffix.' '.$label,'status'=>'current','season'=>'RC1 disposable','is_active'=>1,'lifecycle_stage'=>'in_production']);$productions[$label]=$p;
  $insert('production_memberships',['production_id'=>$p,'user_id'=>$users[$student],'audience_type'=>'student','status'=>'active']);
  $events[$label]=$insert('schedule_items',['production_id'=>$p,'title'=>'RC1 call '.$label,'starts_at'=>date('Y-m-d H:i:s',time()+86400),'ends_at'=>date('Y-m-d H:i:s',time()+90000),'location'=>'Fixture rehearsal','visibility'=>'family','audience_mode'=>'production','item_type'=>'rehearsal','status'=>'active']);
  $f=$insert('forms',['production_id'=>$p,'title'=>'RC1 agreement '.$label,'form_type'=>'agreement','instructions'=>'Fixture only','completion_mode'=>'acknowledgment','review_required'=>1,'active'=>1,'created_by_user_id'=>$users['Staff']]);$forms[$label]=$f;
  $assignments[$label]=$insert('form_assignments',['form_id'=>$f,'production_id'=>$p,'user_id'=>$users[$student],'subject_user_id'=>$users[$student],'status'=>'missing','assigned_by_user_id'=>$users['Staff']]);
 }
 $insert('volunteer_profiles',['user_id'=>$users['Volunteer'],'active'=>1]);$shifts=[];
 foreach($productions as $label=>$p)$shifts[$label]=$insert('volunteer_shifts',['production_id'=>$p,'title'=>'RC1 shift '.$label,'category'=>'Front of house','starts_at'=>date('Y-m-d H:i:s',time()+86400),'ends_at'=>date('Y-m-d H:i:s',time()+90000),'location'=>'Fixture lobby','required_slots'=>2,'approval_required'=>0]);
 $thread=$insert('conversations',['subject'=>'RC1 historical boundary','conversation_type'=>'safeguarded']);
 foreach(['Student'=>'student','Guardian'=>'guardian','FormerGuardian'=>'guardian','Staff'=>'adult'] as $label=>$role)$insert('conversation_participants',['conversation_id'=>$thread,'user_id'=>$users[$label],'participant_role'=>$role,'guardian_required'=>$role==='guardian'?1:0]);
 $message=$insert('messages',['conversation_id'=>$thread,'sender_user_id'=>$users['Staff'],'body'=>'RC1 historical fixture','created_at'=>date('Y-m-d H:i:s')]);
 // Local server cannot bind or route to a remote deployment. Credentials remain memory-only.
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No loopback port.');$address=stream_socket_get_name($socket,false);fclose($socket);$port=(int)substr(strrchr($address,':'),1);$base='http://127.0.0.1:'.$port;
 $env=getenv();$env['MAIL_TRANSPORT']='log';$env['APP_DEBUG']='false';$env['APP_URL']=$base;
 $process=proc_open([PHP_BINARY,'-d','display_errors=0','-S','127.0.0.1:'.$port,'-t',$root,$root.'/tests/fixtures/rc1-router.php'],[['pipe','r'],['file','/dev/null','a'],['file','/dev/null','a']],$pipes,$root,$env);
 if(!is_resource($process))throw new RuntimeException('Cannot start test server.');fclose($pipes[0]);
 for($attempt=0;$attempt<50;$attempt++){if($probe=@fsockopen('127.0.0.1',$port)){fclose($probe);break;}usleep(100000);}
 $request=static function(string $persona,string $path,string $method='GET',?array $body=null,bool $api=false)use($base,&$cookies,&$tokens):array{
  $headers="Accept: ".($api?'application/json':'text/html')."\r\n";
  if($api&&isset($tokens[$persona]))$headers.='Authorization: Bearer '.$tokens[$persona]."\r\n";
  if(!$api&&!empty($cookies[$persona]))$headers.='Cookie: '.implode('; ',$cookies[$persona])."\r\n";
  if($body!==null)$headers.='Content-Type: '.($api?'application/json':'application/x-www-form-urlencoded')."\r\n";
  $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body!==null?($api?json_encode($body):http_build_query($body)):'','ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]);
  $raw=file_get_contents($base.$path,false,$context);$responseHeaders=$http_response_header??[];preg_match('/\s(\d{3})\s/',$responseHeaders[0]??'',$status);
  if(!$api)foreach($responseHeaders as $header)if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$header,$match))$cookies[$persona][$match[1]]=$match[1].'='.$match[2];
  return ['status'=>(int)($status[1]??0),'body'=>(string)$raw,'json'=>json_decode((string)$raw,true)];
 };
 foreach($users as $label=>$id){
  $login=$request($label,'/login');$assert($login['status']===200,$label.' web login page');
  $result=$request($label,'/login','POST',['csrf_token'=>$csrf($login['body']),'email'=>strtolower($label).'.'.$suffix.'@rc1.invalid','password'=>$password]);$assert($result['status']===303,$label.' real web authentication');
  $result=$request($label,'/api/mobile/login','POST',['email'=>strtolower($label).'.'.$suffix.'@rc1.invalid','password'=>$password],true);$assert($result['status']===200&&!empty($result['json']['token']),$label.' native authentication');$tokens[$label]=$result['json']['token'];
  $assert($request($label,'/api/mobile/me','GET',null,true)['status']===200,$label.' current native identity');
 }
 // Staff is intentionally broad under existing organization-wide policy; no invented scope.
 foreach(['Administrator','Staff','Guardian','FormerGuardian','Student','Caregiver','Volunteer'] as $label){
  foreach(['/admin/accounts','/admin/forms','/safeguarding'] as $route){$allowed=$label==='Administrator'||($label==='Staff'&&$route==='/admin/forms');$assert($request($label,$route)['status']===($allowed?200:403),$label.' direct route '.$route);}
 }
 $getEvent=static fn(string $label,string $production)=>$request($label,'/api/mobile/event?id='.$events[$production],'GET',null,true);
 foreach(['Administrator','Staff','Guardian','Student'] as $label)$assert($getEvent($label,'A')['status']===200,$label.' authorized production A schedule');
 $assert($getEvent('Guardian','B')['status']===200,'Guardian sees second linked production');
 foreach(['Student','FormerGuardian','Volunteer'] as $label)$assert($getEvent($label,'B')['status']===404,$label.' cannot obtain unrelated production B event');
 $assert($getEvent('Caregiver','A')['status']===403,'Caregiver cannot use general event endpoint');
 foreach(['/api/mobile/messages','/api/mobile/channels','/api/mobile/daily-tasks','/api/mobile/form-detail?id='.$assignments['A']] as $route)$assert($request('Caregiver',$route,'GET',null,true)['status']===403,'Caregiver mobile boundary '.$route);
 $family=$request('Caregiver','/api/mobile/my-family','GET',null,true)['json'];$child=$family['students'][0]??[];
 $assert(isset($child['first_name'])&&!isset($child['id'])&&!isset($child['name']),'Caregiver family data minimization');
 $calls=$request('Caregiver','/api/mobile/caregiver-schedule','GET',null,true);$assert($calls['status']===200,'Caregiver scoped logistics endpoint');
 $assert(count($calls['json']['calls']??[])===1&&count($calls['json']['calls'][0])===6,'Caregiver schedule contains only the six logistics fields');
 $assert($request('Guardian','/forms/view?id='.$assignments['B'])['status']===200,'Web guardian form access across two productions');
 $assert($request('Student','/forms/view?id='.$assignments['B'])['status']===404,'Student cannot read unrelated web form');
 $assert($request('Caregiver','/forms/view?id='.$assignments['A'])['status']===404,'Caregiver cannot read child web forms');
 $assert($request('Anonymous','/api/mobile/me','GET',null,true)['status']===401,'Anonymous native identity denied');
 $assert($request('Guardian','/api/mobile/form-detail?id='.$assignments['A'],'GET',null,true)['status']===404,'Native guardian-on-child form gap fails closed');
 $assert($request('Student','/api/mobile/form-detail?id='.$assignments['A'],'GET',null,true)['status']===200,'Student own native form detail');
 // True relationship revocation with saved web cookies and bearer, not just a UI flag.
 $db->prepare("UPDATE family_relationships SET status='inactive' WHERE guardian_user_id=:adult AND student_user_id=:child")->execute(['adult'=>$users['FormerGuardian'],'child'=>$users['Student']]);
 $insert('messages',['conversation_id'=>$thread,'sender_user_id'=>$users['Staff'],'body'=>'RC1 later hidden fixture','created_at'=>date('Y-m-d H:i:s')]);
 $history=$request('FormerGuardian','/api/mobile/message-thread?id='.$thread,'GET',null,true);
 $assert($history['status']===200&&count($history['json']['messages']??[])===1,'Former guardian retains only fixed historical message');
 $assert(($history['json']['conversation']['send_allowed']??true)===false,'Former guardian native send paused');
 $assert($request('FormerGuardian','/api/mobile/message-send','POST',['conversation_id'=>$thread,'body'=>'Forbidden'],true)['status']===400,'Former guardian direct native send denied');
 $assert($getEvent('FormerGuardian','A')['status']===404,'Saved former-guardian bearer loses future schedule');
 $assert($request('FormerGuardian','/forms/view?id='.$assignments['A'])['status']===404,'Saved former-guardian web session loses child form');
 $assert($request('Volunteer','/api/mobile/message-thread?id='.$thread,'GET',null,true)['status']===404,'Unrelated adult cannot read safeguarded thread');
 $forms['Review']=$insert('forms',['production_id'=>$productions['A'],'title'=>'RC1 review fixture','form_type'=>'agreement','instructions'=>'Review fixture','completion_mode'=>'acknowledgment','review_required'=>1,'active'=>1,'created_by_user_id'=>$users['Staff']]);
 $assignments['Review']=$insert('form_assignments',['form_id'=>$forms['Review'],'production_id'=>$productions['A'],'user_id'=>$users['Student'],'subject_user_id'=>$users['Student'],'status'=>'missing','assigned_by_user_id'=>$users['Staff']]);
 $assert($request('Student','/api/mobile/form-submit','POST',['assignment_id'=>$assignments['Review'],'acknowledgment'=>true],true)['status']===200,'Active review fixture native submit succeeds');
 $reviewId=(int)$db->query('SELECT id FROM form_submissions WHERE assignment_id='.(int)$assignments['Review'])->fetchColumn();
 $review=$request('Staff','/admin/forms/review?id='.$reviewId);$reviewCsrf=$csrf($review['body'],'submission_id');
 // Capture genuine CSRF before closure, then submit stale forms directly after closure.
 $active=$request('Student','/forms/view?id='.$assignments['A']);$studentCsrf=$csrf($active['body'],'assignment_id');
 $manage=$request('Staff','/admin/forms/manage');$manageCsrf=$csrf($manage['body'],'action','toggle_active');
 $builder=$request('Staff','/admin/forms/builder?id='.$forms['A']);$builderCsrf=$csrf($builder['body'],'action','save_field');
 $fingerprint=static function()use($db,$forms,$assignments):string{
  $out=[];foreach(['forms'=>'id IN ('.implode(',',$forms).')','form_assignments'=>'id IN ('.implode(',',$assignments).')','form_submissions'=>'assignment_id IN ('.implode(',',$assignments).')','form_fields'=>'form_id IN ('.implode(',',$forms).')','audit_events'=>'1=1','app_notifications'=>'1=1','email_queue'=>'1=1','push_queue'=>'1=1'] as $table=>$where)$out[$table]=$db->query('SELECT * FROM '.$table.' WHERE '.$where.' ORDER BY id')->fetchAll();
  return hash('sha256',json_encode($out));
 };
 foreach(['completed','archived'] as $stage){
  $db->prepare('UPDATE productions SET lifecycle_stage=:stage,is_active=:active WHERE id=:id')->execute(['stage'=>$stage,'active'=>$stage==='archived'?0:1,'id'=>$productions['A']]);$before=$fingerprint();
  $post=$request('Student','/forms/view?id='.$assignments['A'],'POST',['assignment_id'=>$assignments['A'],'csrf_token'=>$studentCsrf,'acknowledgment'=>1]);$assert($post['status']===303,$stage.' closed web submit returns controlled redirect');
  $assert($request('Student','/api/mobile/form-submit','POST',['assignment_id'=>$assignments['A'],'acknowledgment'=>true],true)['status']===400,$stage.' direct native submit denied');
  $assert($request('Staff','/admin/forms/manage','POST',['csrf_token'=>$manageCsrf,'action'=>'toggle_active','form_id'=>$forms['A']])['status']===303,$stage.' staff toggle controlled rejection');
  $assert($request('Staff','/admin/forms/manage/edit?id='.$forms['A'],'POST',['csrf_token'=>$manageCsrf,'action'=>'save_form','form_id'=>$forms['A'],'scope'=>'organization','title'=>'Forbidden scope escape','form_type'=>'agreement','instructions'=>'Fixture only','completion_mode'=>'acknowledgment'])['status']===303,$stage.' staff definition scope escape controlled rejection');
  $assert($request('Staff','/admin/forms/builder?id='.$forms['A'],'POST',['csrf_token'=>$builderCsrf,'action'=>'save_field','form_id'=>$forms['A'],'label'=>'Forbidden field','field_type'=>'short_text'])['status']===303,$stage.' staff builder controlled rejection');
  $assert($request('Staff','/admin/forms/review?id='.$reviewId,'POST',['csrf_token'=>$reviewCsrf,'submission_id'=>$reviewId,'decision'=>'approve','reviewer_note'=>'Must not change closed history'])['status']===303,$stage.' staff review controlled rejection');
  $assert($fingerprint()===$before,$stage.' web/native/staff POSTs preserve forms and history');
  $assert($request('Volunteer','/api/mobile/volunteer-signup','POST',['shift_id'=>$shifts['A']],true)['status']===400,$stage.' native volunteer signup denied');
  $assert($getEvent('Guardian','B')['status']===200,$stage.' production A leaves B schedule accessible');
 }
 $assert((int)$db->query('SELECT COUNT(*) FROM messages WHERE conversation_id='.(int)$thread)->fetchColumn()===2,'Production closure preserves conversation history');
 // A valid second-production form can still be submitted; count proves negative tests were not inert.
 $assert($request('StudentB','/api/mobile/form-submit','POST',['assignment_id'=>$assignments['B'],'acknowledgment'=>true],true)['status']===200,'Active production B native submit succeeds');
 $assert((int)$db->query('SELECT COUNT(*) FROM form_submissions WHERE assignment_id='.(int)$assignments['B'])->fetchColumn()===1,'Active submission history is persisted');
 $assert((int)$db->query('SELECT submitted_for_user_id FROM form_submissions WHERE assignment_id='.(int)$assignments['B'])->fetchColumn()===$users['StudentB'],'Native submission records authorized assignment subject');
 $structured=$insert('forms',['production_id'=>$productions['B'],'title'=>'RC1 required answers','form_type'=>'agreement','instructions'=>'Website required','completion_mode'=>'acknowledgment','review_required'=>1,'active'=>1,'created_by_user_id'=>$users['Administrator']]);
 $structuredAssignment=$insert('form_assignments',['form_id'=>$structured,'production_id'=>$productions['B'],'user_id'=>$users['StudentB'],'subject_user_id'=>$users['StudentB'],'status'=>'missing','assigned_by_user_id'=>$users['Administrator']]);
 $insert('form_fields',['form_id'=>$structured,'field_key'=>'answer','label'=>'Required answer','field_type'=>'short_text','required'=>1,'active'=>1]);
 $assert(($request('StudentB','/api/mobile/form-detail?id='.$structuredAssignment,'GET',null,true)['json']['form']['requires_web']??false)===true,'Structured native form explicitly requires web');
 $assert($request('StudentB','/api/mobile/form-submit','POST',['assignment_id'=>$structuredAssignment,'acknowledgment'=>true],true)['status']===400,'Native acknowledgment cannot bypass required structured answers');
 $assert((int)$db->query('SELECT COUNT(*) FROM form_submissions WHERE assignment_id='.(int)$structuredAssignment)->fetchColumn()===0,'Rejected structured form creates no submission');
 $assert($request('Volunteer','/api/mobile/volunteer-signup','POST',['shift_id'=>$shifts['B']],true)['status']===200,'Volunteer signup in active production B succeeds');
 $assert($request('Volunteer','/api/mobile/volunteer-cancel','POST',['shift_id'=>$shifts['B']],true)['status']===200,'Volunteer can cancel own future B signup');
 $assert($db->query('SELECT status FROM volunteer_shift_signups WHERE shift_id='.(int)$shifts['B'].' AND user_id='.(int)$users['Volunteer'])->fetchColumn()==='cancelled','Volunteer cancellation preserves status history');
 $db->prepare('DELETE FROM auth_user_roles WHERE user_id=:user')->execute(['user'=>$users['Staff']]);
 $assert($request('Staff','/admin/forms')['status']===403,'Saved staff web session loses removed permission');
 $assert($getEvent('Staff','B')['status']===404,'Saved staff bearer no longer grants staff production access');
 $db->prepare("UPDATE family_relationships SET status='inactive' WHERE guardian_user_id=:user")->execute(['user'=>$users['Caregiver']]);
 $assert(($request('Caregiver','/api/mobile/caregiver-schedule','GET',null,true)['json']['calls']??null)===[],'Saved caregiver bearer loses calls after revocation');
 $volunteerAccount=$request('Volunteer','/account');$logoutCsrf=$csrf($volunteerAccount['body']);
 $assert($request('Volunteer','/logout','POST',['csrf_token'=>$logoutCsrf])['status']===303,'Web logout accepted');
 $assert($request('Volunteer','/account')['status']===303,'Logged-out web cookie cannot access account');
 $db->prepare("UPDATE users SET account_status='disabled' WHERE id=:id")->execute(['id'=>$users['Student']]);
 $assert($request('Student','/api/mobile/me','GET',null,true)['status']===401,'Disabled account saved bearer denied');
 $assert($request('Student','/forms')['status']===303,'Disabled account saved web cookie denied');
 $assert($request('Volunteer','/api/mobile/logout','POST',[],true)['status']===200,'Native logout accepted');
 $assert($request('Volunteer','/api/mobile/me','GET',null,true)['status']===401,'Logged-out bearer cannot be replayed');
 $assert((int)$db->query("SELECT COUNT(*) FROM email_delivery_log")->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM push_delivery_log')->fetchColumn()===0,'No delivery worker or outbound attempt');
 echo json_encode(['result'=>'PASS','checks'=>count($checks),'personas'=>['Administrator','Staff','Guardian','FormerGuardian','Student','Caregiver','Volunteer'],'productions'=>2,'coverage'=>$checks,'limitations'=>['Local HTTP and MySQL, not hosted Apache/TLS or device verification.','Staff policy is currently organization-wide.','Native guardian child forms remain unavailable, explicitly tested fail-closed.','Volunteer capacity/approval races and full closure concurrency require additional acceptance.']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}finally{
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 // Keep isolated fixture history for diagnosis, but make every fake login unusable.
 foreach(['Student','StudentB','Guardian','FormerGuardian','Caregiver','Volunteer','Staff','Administrator'] as $label)if(isset($users[$label])){
  $db->prepare('UPDATE auth_mobile_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE user_id=:id')->execute(['id'=>$users[$label]]);
  $db->prepare("UPDATE users SET active=0,account_status='disabled' WHERE id=:id")->execute(['id'=>$users[$label]]);
 }
 if($users){$ids=implode(',',array_map('intval',array_values($users)));if((int)$db->query("SELECT COUNT(*) FROM users WHERE id IN ($ids) AND active=1")->fetchColumn()!==0||(int)$db->query("SELECT COUNT(*) FROM auth_mobile_tokens WHERE user_id IN ($ids) AND revoked_at IS NULL")->fetchColumn()!==0)throw new RuntimeException('Fixture credential cleanup failed.');}
 fwrite(STDERR,"RC1 fixture accounts disabled and bearer tokens revoked; independently counted zero active fixture credentials. Isolated history retained.\n");
}
