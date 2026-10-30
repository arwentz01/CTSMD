<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/my-theatre-personas.php';
require_once dirname(__DIR__).'/src/FamilyFormService.php';
$db=Database::connect(dirname(__DIR__));$fixture=null;$checks=[];$cookies=[];$tokens=[];$process=null;$pipes=[];$root=dirname(__DIR__);$testStorage=sys_get_temp_dir().'/ctsmd-member-http-'.bin2hex(random_bytes(6));
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=$label;echo "PASS $label\n";};
$csrf=static function(string $html,?string $field=null,?string $value=null):string{
 $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
 $query='//form'.($field?"[.//*[@name='$field'".($value!==null?" and @value='$value'":'').']]':'')."//input[@name='csrf_token']/@value";
 $node=$xpath->query($query)->item(0);if(!$node)throw new RuntimeException('Expected CSRF field absent.');return $node->nodeValue;
};
try{
 $fixture=myTheatreFixture($db);extract($fixture);
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No loopback port.');$address=stream_socket_get_name($socket,false);fclose($socket);$port=(int)substr(strrchr($address,':'),1);$base='http://127.0.0.1:'.$port;
 $env=getenv();$env['MAIL_TRANSPORT']='log';$env['APP_DEBUG']='false';$env['APP_URL']=$base;$env['STORAGE_PATH']=$testStorage;
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
  $login=$request($label,'/login');$result=$request($label,'/login','POST',['csrf_token'=>$csrf($login['body']),'email'=>strtolower($label).'.'.$suffix.'@rc1.invalid','password'=>$password]);
  if($label==='Disabled'){$assert($request($label,'/my-theatre')['status']===303,'Disabled account cannot open protected workspace');continue;}
  $assert($result['status']===303,$label.' real web authentication');
  foreach(['/my-theatre','/calendar?personal=1&view=agenda','/forms','/notifications','/cast','/resources'] as $path){$response=$request($label,$path);$assert($response['status']===200&&str_contains($response['body'],'</html>')&&!preg_match('/Fatal error|SQLSTATE|Warning:/',$response['body']),$label.' complete '.$path);}
 }
 $r=$request('GuardianTwo','/calendar?personal=1&event='.$events['ChildCall']);$assert($r['status']===200&&str_contains($r['body'],'My Theatre ChildCall'),'Authorized exact group event detail');
 foreach(['GuardianOne','FormerGuardian','Member'] as $who){$r=$request($who,'/calendar?personal=1&event='.$events['ChildCall']);$assert($r['status']===404&&!str_contains($r['body'],'My Theatre ChildCall'),$who.' group event direct ID denied');}
 $r=$request('GuardianOne','/my-theatre?context='.$users['StudentB']);$assert($r['status']===404&&!str_contains($r['body'],'My Theatre AllB'),'Forged household context denied');
 $r=$request('GuardianOne','/forms/view?id='.$forms['ClosedForm']);$assert($r['status']===200&&str_contains($r['body'],'Recorded response')&&!str_contains($r['body'],'class="ff-form ops-form"'),'Closed form renders history without completion controls');
 $index=$request('GuardianOne','/forms');
 // A real CSRF token from another editable assignment must not reopen this one.
 $edit=$request('GuardianOne','/forms/view?id='.$forms['SingleForm']);$token=$csrf($edit['body']);
 $before=(int)$db->query('SELECT COUNT(*) FROM form_submissions WHERE assignment_id='.(int)$forms['ClosedForm'])->fetchColumn();
 $r=$request('GuardianOne','/forms/view?id='.$forms['ClosedForm'],'POST',['csrf_token'=>$token,'assignment_id'=>$forms['ClosedForm'],'acknowledgment'=>'1']);
 $assert($r['status']===404&&(int)$db->query('SELECT COUNT(*) FROM form_submissions WHERE assignment_id='.(int)$forms['ClosedForm'])->fetchColumn()===$before,'Closed definition POST rejected without changing history');
 $r=$request('GuardianTwo','/forms/view?id='.$forms['ClosedProductionForm']);$assert($r['status']===200&&str_contains($r['body'],'This production is closed.'),'Closed production assignment is read-only');
 $r=$request('GuardianOne','/forms/view?id='.$forms['OtherFamilyForm']);$assert($r['status']===404,'Another household form denied');
 // Exercise legacy assignments addressed to a guardian but concerning a child.
 $db->prepare('UPDATE form_assignments SET user_id=? WHERE id=?')->execute([$users['FormerGuardian'],$forms['SingleForm']]);
 $assert(FamilyFormService::assignmentForViewer($db,$users['FormerGuardian'],$forms['SingleForm'],true)===null,'Revoked direct-assigned guardian loses child form access');
 $assert(FamilyFormService::assignmentForViewer($db,$users['GuardianOne'],$forms['SingleForm'],true)!==null,'Current guardian retains child assignment regardless of legacy recipient');
 $r=$request('FormerGuardian','/forms/view?id='.$forms['SingleForm']);$assert($r['status']===404,'Revoked legacy-recipient form HTTP denied');
 $r=$request('GuardianOne','/resources/view?id='.$resource);$assert($r['status']===200&&str_contains($r['body'],'Authorized resource text'),'Current guardian without duplicate roster row reads authorized resource');
 foreach(['FormerGuardian','Member','Volunteer'] as $who){$r=$request($who,'/resources/view?id='.$resource);$assert(!str_contains($r['body'],'Authorized resource text'),$who.' private resource text denied');}
 $notice=$request('StudentSingle','/notifications');$assert(!str_contains($notice['body'],'value="accepted"')&&!str_contains($notice['body'],'value="declined"'),'Student has no independent offer response controls');
 $r=$request('StudentSingle','/notifications','POST',['csrf_token'=>$csrf($notice['body']),'action'=>'respond_cast_offer','offer_id'=>$offers['StudentSingle'],'decision'=>'accepted']);
 $assert($db->query('SELECT status FROM production_cast_offers WHERE id='.(int)$offers['StudentSingle'])->fetchColumn()==='pending','Forged Student response leaves offer pending');
 $form=$request('GuardianTwo','/forms/view?id='.$forms['StudentForm']);$r=$request('GuardianTwo','/forms/view?id='.$forms['StudentForm'],'POST',['csrf_token'=>$csrf($form['body']),'assignment_id'=>$forms['StudentForm'],'acknowledgment'=>'1']);$assert($r['status']===303&&$db->query('SELECT status FROM form_assignments WHERE id='.(int)$forms['StudentForm'])->fetchColumn()==='requires_review','Authorized guardian completes Student form through original web POST');
 $profile=$request('Adult','/student-profile');$r=$request('Adult','/student-profile','POST',['csrf_token'=>$csrf($profile['body'],'action','save_profile'),'action'=>'save_profile','student_id'=>$users['Adult'],'date_of_birth'=>'1980-01-01','preferred_name'=>'Verified stage name','short_bio'=>'An existing performer profile edited intentionally.','special_skills'=>'Singing','photographer_credit'=>'']);$assert($r['status']===303,'Adult profile existing save handler accepts intentional edit');$assert(str_contains($request('Adult','/student-profile')['body'],'Verified stage name'),'Saved profile appears in information summary');
 $shiftPage=$request('Volunteer','/volunteer/shift?id='.$shift);$r=$request('Volunteer','/volunteer/shift?id='.$shift,'POST',['csrf_token'=>$csrf($shiftPage['body']),'shift_id'=>$shift,'action'=>'cancel']);$assert($r['status']===303&&$db->query('SELECT status FROM volunteer_shift_signups WHERE shift_id='.(int)$shift.' AND user_id='.(int)$users['Volunteer'])->fetchColumn()==='cancelled','Volunteer cancels own future commitment through real web POST');
 $shiftPage=$request('Volunteer','/volunteer/shift?id='.$shift);$r=$request('Volunteer','/volunteer/shift?id='.$shift,'POST',['csrf_token'=>$csrf($shiftPage['body']),'shift_id'=>$shift,'action'=>'signup']);$assert($r['status']===303&&$db->query('SELECT status FROM volunteer_shift_signups WHERE shift_id='.(int)$shift.' AND user_id='.(int)$users['Volunteer'])->fetchColumn()==='signed_up','Volunteer rechecks eligibility and reserves available slot through real POST');
 require_once dirname(__DIR__).'/src/ProductionChecklistAssignmentService.php';$db->prepare("INSERT INTO family_relationships(guardian_user_id,student_user_id,relationship_type,status) VALUES(?,?,'caregiver','active')")->execute([$users['Member'],$users['StudentMulti']]);$denied=false;try{ProductionChecklistAssignmentService::complete($db,$users['Member'],$taskAssignment);}catch(RuntimeException){$denied=true;}$assert($denied,'Caregiver cannot complete guardian-only Student checklist assignment');
 echo 'PASS '.count($checks)." authenticated My Theatre HTTP assertions.\n";
}finally{if(is_resource($process))proc_terminate($process);if($db->inTransaction())$db->rollBack();if($fixture)retireMyTheatreFixture($db,$fixture);}
