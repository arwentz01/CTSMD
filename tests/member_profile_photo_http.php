<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/my-theatre-personas.php';
require_once dirname(__DIR__).'/src/MemberProfilePhotoService.php';

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))){
    throw new RuntimeException('Profile photo HTTP acceptance requires a disposable loopback database.');
}
if(!extension_loaded('gd'))throw new RuntimeException('GD extension required for profile photo HTTP acceptance.');
$root=dirname(__DIR__);$db=Database::connect($root);$fixture=null;$process=null;$pipes=[];
$storage=sys_get_temp_dir().'/ctsmd-avatar-http-'.bin2hex(random_bytes(8));$checks=0;$cookies=[];
$ok=static function(bool $pass,string $description)use(&$checks):void{
    if(!$pass)throw new RuntimeException('FAIL '.$description);
    ++$checks;echo 'PASS '.$description."\n";
};
$makeImage=static function(int $red):string{
    $im=imagecreatetruecolor(128,128);
    imagefill($im,0,0,imagecolorallocate($im,$red,40,90));
    ob_start();imagepng($im);$bytes=(string)ob_get_clean();imagedestroy($im);
    return $bytes;
};
try{
    $fixture=myTheatreFixture($db);$users=$fixture['users'];
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if(!$listener)throw new RuntimeException('No loopback port available.');
    $address=stream_socket_get_name($listener,false);fclose($listener);
    $port=(int)substr(strrchr($address,':'),1);$base='http://127.0.0.1:'.$port;
    $env=getenv();$env['APP_URL']=$base;$env['APP_DEBUG']='false';$env['STORAGE_PATH']=$storage;$env['MAIL_TRANSPORT']='log';
    $process=proc_open([PHP_BINARY,'-d','display_errors=0','-S','127.0.0.1:'.$port,'-t',$root,$root.'/tests/fixtures/rc1-router.php'],
        [['pipe','r'],['file','/dev/null','a'],['file','/dev/null','a']],$pipes,$root,$env);
    if(!is_resource($process))throw new RuntimeException('Could not start isolated HTTP server.');
    fclose($pipes[0]);foreach(range(1,50) as $_){$probe=@fsockopen('127.0.0.1',$port);if($probe){fclose($probe);break;}usleep(100000);}

    $request=static function(string $actor,string $path,string $method='GET',?string $body=null,?string $type=null)use($base,&$cookies):array{
        $headers="Accept: text/html\r\n";
        if(!empty($cookies[$actor]))$headers.='Cookie: '.implode('; ',$cookies[$actor])."\r\n";
        if($type)$headers.='Content-Type: '.$type."\r\n";
        $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body??'','ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]);
        $response=file_get_contents($base.$path,false,$ctx);$headersOut=$http_response_header??[];
        preg_match('/\\s(\\d{3})\\s/',$headersOut[0]??'',$status);
        foreach($headersOut as $h)if(preg_match('/^Set-Cookie:\\s*([^=]+)=([^;]*)/i',$h,$m))$cookies[$actor][$m[1]]=$m[1].'='.$m[2];
        return ['status'=>(int)($status[1]??0),'body'=>(string)$response,'headers'=>$headersOut];
    };
    $csrf=static function(string $html):string{
        if(!preg_match('/name="csrf_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token not found.');
        return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
    };
    foreach(['GuardianOne','Member','StudentSingle'] as $name){
        $page=$request($name,'/login');$ok($page['status']===200,'Login form available to '.$name);
        $body=http_build_query(['csrf_token'=>$csrf($page['body']),'email'=>strtolower($name).'.'.$fixture['suffix'].'@rc1.invalid','password'=>$fixture['password']]);
        $login=$request($name,'/login','POST',$body,'application/x-www-form-urlencoded');
        $ok($login['status']===303,'Authenticated '.$name.' fixture');
    }
    $account=$request('GuardianOne','/account');
    $ok($account['status']===200,'Account photo editor is available');
    $token=$csrf($account['body']);
    $upload=static function(string $actor,string $csrfToken,string $filename,string $type,string $bytes)use($request):array{
        $boundary='ctsmdPhoto'.bin2hex(random_bytes(10));$data='';
        foreach(['csrf_token'=>$csrfToken,'action'=>'avatar'] as $field=>$value){
            $data.='--'.$boundary."\r\n".'Content-Disposition: form-data; name="'.$field.'"'."\r\n\r\n".$value."\r\n";
        }
        $data.='--'.$boundary."\r\n".'Content-Disposition: form-data; name="avatar"; filename="'.$filename.'"'."\r\n".'Content-Type: '.$type."\r\n\r\n".$bytes."\r\n";
        $data.='--'.$boundary."--\r\n";
        return $request($actor,'/account','POST',$data,'multipart/form-data; boundary='.$boundary);
    };
    $response=$upload('GuardianOne',$token,'portrait.png','image/png',$makeImage(30));
    $ok($response['status']===303,'Valid image upload redirects successfully');
    $userId=(int)$users['GuardianOne'];$photo=MemberProfilePhotoService::row($db,$userId);
    $ok(is_array($photo)&&$photo['avatar_small_key']!=='','Profile image references stored in database');
    $oldPaths=array_map(static fn(string $field):string=>$storage.'/'.(string)$photo[$field],['avatar_large_key','avatar_medium_key','avatar_small_key']);
    foreach($oldPaths as $path)$ok(is_file($path)&&filesize($path)>0,'Optimized private thumbnail exists: '.basename($path));
    $self=$request('GuardianOne','/account/avatar?id='.$userId.'&size=large');
    $ok($self['status']===200&&str_starts_with($self['body'],"\\xff\\xd8"),'Owner can stream optimized JPEG');
    $other=$request('Member','/account/avatar?id='.$userId.'&size=small');
    $ok($other['status']===404,'Unrelated member is denied owner avatar');
    $child=$request('StudentSingle','/account/avatar?id='.$userId.'&size=small');
    $ok($child['status']===200,'Actively linked child may view their guardian photo');
    $ok($request('GuardianOne','/account/avatar?id='.$userId.'&size=arbitrary')['status']===404,'Unsupported avatar size is denied');
    $response=$upload('GuardianOne',$token,'updated.png','image/png',$makeImage(190));
    $ok($response['status']===303,'Replacing a profile photo succeeds');
    $replaced=MemberProfilePhotoService::row($db,$userId);
    $ok(is_array($replaced)&&$replaced['avatar_large_key']!==$photo['avatar_large_key'],'Replacement publishes new thumbnail keys');
    foreach($oldPaths as $path)$ok(!file_exists($path),'Replaced thumbnail is not retained');
    $before=(string)$replaced['avatar_large_key'];
    $response=$upload('GuardianOne',$token,'not-image.png','image/png','This is not an image');
    $ok($response['status']===303,'Invalid photo request returns to account with error');
    $after=MemberProfilePhotoService::row($db,$userId);
    $ok($after!==null&&$before===$after['avatar_large_key'],'Invalid upload does not change current avatar');
    echo "PASS $checks private profile photo HTTP checks.\n";
}finally{
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    if($fixture)retireMyTheatreFixture($db,$fixture);
    if(is_dir($storage)){
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $entry){if($entry->isDir())@rmdir($entry->getPathname());else @unlink($entry->getPathname());}
        @rmdir($storage);
    }
}
