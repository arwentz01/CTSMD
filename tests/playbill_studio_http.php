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

 foreach(['Admin','StaffPerformer','StaffOnly','Member','Adult','GuardianOne','FormerGuardian'] as $who){$login=$request($who,'/login');$result=$request($who,'/login','POST',['csrf_token'=>$csrf($login['body']),'email'=>strtolower($who).'.'.$suffix.'@rc1.invalid','password'=>$password]);$assert($result['status']===303,$who.' real authentication');}
 $path='/admin/playbill?production='.$productions['A'];$page=$request('Admin',$path.'&tab=cover');
 $assert($page['status']===200&&str_contains($page['body'],'Playbill Studio'),'Studio real route renders');
 $post=['csrf_token'=>$csrf($page['body'],'action','save_playbill'),'action'=>'save_playbill','production_id'=>$productions['A'],'playbill_id'=>0,'display_title'=>'Studio HTTP program','public_slug'=>'studio-http-'.$suffix,'subtitle'=>'A synthetic program','cover_note'=>'Saved cover','studio_tab'=>'cover'];
 $assert($request('Admin',$path,'POST',$post)['status']===303,'Existing cover POST creates program');
 $pb=(int)$db->query('SELECT id FROM playbills WHERE production_id='.(int)$productions['A'])->fetchColumn();
 foreach(['overview','cover','company','bios','content','sponsors','publish'] as $tab){$page=$request('Admin',$path.'&tab='.$tab);$assert($page['status']===200&&str_contains($page['body'],'</html>')&&!preg_match('/Fatal error|Warning:|SQLSTATE/',$page['body']),'Complete Studio tab '.$tab);}
 $assert($request('StaffPerformer',$path)['status']===200,'Assigned production Staff editor can open Studio');
 $assert($request('StaffPerformer','/admin/playbill?production='.$productions['B'])['status']===403,'Foreign production Staff editor denied');
 $assert($request('Member',$path)['status']===403,'Ordinary member editor denied');
 $assert($request('StaffOnly',$path.'&preview=1')['status']===403,'Draft preview cannot bypass production Staff scope');
 $alias=$request('Admin','/admin/playbill/media?production='.$productions['A']);$assert($alias['status']===200&&str_contains($alias['body'],'Playbill Studio'),'Legacy media URL delegates to same Studio');
 $preview=$request('Admin',$path.'&preview=1');$assert($preview['status']===200&&str_contains($preview['body'],'Private preview')&&str_contains($preview['body'],'pbr-reader'),'Private saved preview uses actual audience renderer');
 $slug='studio-http-'.$suffix;
 foreach(['/playbill?slug=','/playbill/qr?slug=','/playbill/sign?slug=','/playbill/asset?kind=artwork&slug='] as $endpoint)$assert($request('Guest',$endpoint.$slug)['status']===404,'Draft public endpoint denied '.$endpoint);
 $q=$db->prepare('SELECT id FROM playbill_cast_profiles WHERE playbill_id=? AND user_id=?');$q->execute([$pb,$users['Adult']]);$profile=(int)$q->fetchColumn();
 $page=$request('Admin',$path.'&tab=bios');$castBody=['csrf_token'=>$csrf($page['body'],'action','save_cast'),'action'=>'save_cast','production_id'=>$productions['A'],'playbill_id'=>$pb,'user_id'=>$users['Adult'],'display_name'=>'Approved adult name','role_title'=>'Lead','biography'=>'Saved editorial bio','display_order'=>10,'visible'=>1,'studio_tab'=>'bios'];
 $assert($request('Admin',$path,'POST',$castBody)['status']===303,'Studio saves production-specific biography');
 $consent=$request('Adult','/playbill/consent?profile='.$profile);if($consent['status']!==200)throw new RuntimeException('Consent response '.$consent['status'].' '.substr(strip_tags($consent['body']),0,500));$assert($consent['status']===200&&str_contains($consent['body'],'Saved editorial bio'),'Account holder reviews exact private snapshot');
 $assert($request('Member','/playbill/consent?profile='.$profile)['status']===404,'Unrelated member cannot inspect private consent');
 $assert($request('Adult','/playbill/consent?profile='.$profile,'POST',['csrf_token'=>$csrf($consent['body'],'decision'),'decision'=>'approved'])['status']===303,'Account holder approves through existing consent service');
 $q->execute([$pb,$users['StudentSingle']]);$studentProfile=(int)$q->fetchColumn();
 $assert($request('GuardianOne','/playbill/consent?profile='.$studentProfile)['status']===200,'Current guardian can review child snapshot');
 $assert($request('FormerGuardian','/playbill/consent?profile='.$studentProfile)['status']===404,'Former guardian cannot review child snapshot');
 // Real uploaded sponsor bytes remain private until explicit publication.
 $sponsorPage=$request('Admin',$path.'&tab=sponsors');$upload=tempnam(sys_get_temp_dir(),'studio-pixel-');$pixel=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jBasAAAAASUVORK5CYII=');file_put_contents($upload,$pixel);
 $curl=curl_init($base.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Cookie: '.implode('; ',$cookies['Admin'])],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['csrf_token'=>$csrf($sponsorPage['body'],'action','save_sponsor'),'production_id'=>$productions['A'],'playbill_id'=>$pb,'action'=>'save_sponsor','sponsor_name'=>'Studio sponsor '.$suffix,'tier'=>'premium','logo'=>new CURLFile($upload,'image/png','fixture.png')]]);curl_exec($curl);$uploadStatus=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);unlink($upload);
 $sponsor=(int)$db->query('SELECT sponsor_id FROM playbill_sponsors WHERE playbill_id='.$pb)->fetchColumn();$assert($uploadStatus===303&&$sponsor>0,'Sponsor upload uses existing storage and attachment POST');
 $asset='/playbill/asset?slug='.$slug.'&kind=sponsor&id='.$sponsor;
 $assert($request('Guest',$asset)['status']===404,'Draft sponsor bytes are private');
 $image=$request('Admin',$path.'&preview_asset=1&kind=sponsor&id='.$sponsor);$assert($image['status']===200&&$image['body']===$pixel,'Authorized preview receives exact sponsor bytes');
 $assert($request('StaffOnly',$path.'&preview_asset=1&kind=sponsor&id='.$sponsor)['status']===403,'Foreign Staff cannot retrieve draft sponsor image');
 // Select the publication form's CSRF rather than the header logout token.
 $page=$request('Admin',$path.'&tab=publish');$token=$csrf($page['body'],'studio_tab','publish');
 // The preview tab contains a single editing form after header navigation.
 preg_match('/name="csrf_token" value="([^"]+)"[^<]*>\s*<input type="hidden" name="production_id"/',$page['body'],$match);$token=html_entity_decode($match[1]??$token);
 $body=['csrf_token'=>$token,'production_id'=>$productions['A'],'playbill_id'=>$pb,'studio_tab'=>'publish','action'=>'publish'];$assert($request('Admin',$path,'POST',$body)['status']===303,'Publish action submits');
 $public=$request('Guest','/playbill?slug='.$slug);$assert($public['status']===200&&str_contains($public['body'],'Approved adult name')&&str_contains($public['body'],'Saved editorial bio'),'Anonymous public program contains approved saved content');
 $assert($request('Guest','/playbill/qr?slug='.$slug)['status']===200,'Published QR available without account');
 $image=$request('Guest',$asset);$assert($image['status']===200&&$image['body']===$pixel,'Published attached sponsor image is audience accessible');
 $assert($request('Guest','/playbill/asset?slug='.$slug.'&kind=sponsor&id='.($sponsor+1000000))['status']===404,'Unattached sponsor ID cannot bypass publication attachment');
 $body['action']='unpublish';$assert($request('Admin',$path,'POST',$body)['status']===303,'Unpublish action submits');
 foreach(['/playbill?slug=','/playbill/qr?slug=','/playbill/sign?slug='] as $endpoint)$assert($request('Guest',$endpoint.$slug)['status']===404,'Unpublish revokes audience endpoint '.$endpoint);
 $assert($request('Guest',$asset)['status']===404,'Unpublish revokes sponsor image access');
 echo 'PASS '.count($checks)." Playbill Studio authenticated HTTP assertions.\n";
}finally{if(is_resource($process))proc_terminate($process);if($db->inTransaction())$db->rollBack();if($fixture)retireMyTheatreFixture($db,$fixture);if(is_dir($testStorage)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testStorage,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry){$entry->isDir()?rmdir($entry->getPathname()):unlink($entry->getPathname());}rmdir($testStorage);}}
