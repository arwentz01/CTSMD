<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/MemberProfilePhotoService.php';

$root=dirname(__DIR__);
$source=file_get_contents($root.'/src/MemberProfilePhotoService.php');
$messages=file_get_contents($root.'/src/CommunicationExperience.php');
$community=file_get_contents($root.'/src/CommunityExperience.php');
$native=file_get_contents($root.'/src/MobileApi.php');
$front=file_get_contents($root.'/front.php');
$migration=file_get_contents($root.'/database/migrations/129_member_profile_photos.sql');
foreach([
    'Private image renditions'=>'profile-thumbnails/',
    'Private streaming authorization'=>'self::canView($db,$viewer,$subjectId)',
    'Strict image content validation'=>'getimagesize($path)',
    'Image resolution limit'=>'MAX_PIXELS',
    'Optimized thumbnail sizes'=>"'large'=>512,'medium'=>96,'small'=>40",
    'Authenticated original file storage'=>'StorageService::store($db,$projectRoot,$actorId,$upload)',
    'No public source URL'=>'Content-Disposition: inline',
] as $name=>$expected){
    if(!str_contains($source,$expected))throw new RuntimeException('Missing photo contract: '.$name);
}
foreach([
    'Guarded user avatar ownership'=>'FOREIGN KEY (user_id) REFERENCES users(id)',
    'Persistent private stored file'=>'REFERENCES stored_files(id)',
] as $name=>$expected){
    if(!str_contains($migration,$expected))throw new RuntimeException('Missing migration contract: '.$name);
}
foreach([
    'Recipient lookup minimum length'=>'mb_strlen($query)<2',
    'Recipient lookup limit'=>'LIMIT 25',
    'Server-side student contact restriction'=>"Students can only start private conversations with authorized CTSMD staff.",
    'Safeguarded participant validation'=>'SafeguardedConversationPolicy::enforceForSend',
    'Recipient search API'=>'/messages/recipient-search',
    'Prefilled recipients validated'=>'eligibleForNewMessage($db,$user',
] as $name=>$expected){
    if(!str_contains($messages,$expected))throw new RuntimeException('Missing message contract: '.$name);
}
if(!str_contains($community,'eligibleForNewMessage($db,$user'))throw new RuntimeException('Community shortcuts must check recipient eligibility');
if(!str_contains($native,'addMessageEligibility'))throw new RuntimeException('Mobile Community shortcuts must check recipient eligibility');
if(!str_contains($front,"'/account/avatar'"))throw new RuntimeException('Private avatar route not registered');
if(!str_contains($front,"'/messages/recipient-search'"))throw new RuntimeException('Recipient search route not registered');

if(extension_loaded('gd')){
    $image=imagecreatetruecolor(96,96);
    $tmp=tempnam(sys_get_temp_dir(),'ctsmd-photo-');
    try{
        imagefilledrectangle($image,0,0,95,95,imagecolorallocate($image,15,30,70));
        imagepng($image,$tmp);
        $info=MemberProfilePhotoService::validateImage($tmp,(int)filesize($tmp));
        if($info['mime']!=='image/png'||$info['width']!==96||$info['height']!==96)throw new RuntimeException('Image validation returned incorrect dimensions');
        $caught=false;try{MemberProfilePhotoService::validateImage($tmp,6291457);}catch(RuntimeException $e){$caught=true;}
        if(!$caught)throw new RuntimeException('Oversized image was accepted');
        file_put_contents($tmp,'not an image');
        $caught=false;try{MemberProfilePhotoService::validateImage($tmp,(int)filesize($tmp));}catch(RuntimeException $e){$caught=true;}
        if(!$caught)throw new RuntimeException('Invalid image bytes were accepted');
    }finally{imagedestroy($image);@unlink($tmp);}
}
echo "PASS: private photo contracts, safeguarded recipient lookup and image validation.\n";
