<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/my-theatre-personas.php';
require_once dirname(__DIR__).'/src/MemberProfilePhotoService.php';
require_once dirname(__DIR__).'/src/CommunicationExperience.php';
if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))){
    throw new RuntimeException('Profile image authorization requires a disposable local rc1-test database.');
}
$db=Database::connect(dirname(__DIR__));$fixture=null;$checks=0;
$ok=static function(bool $passed,string $message)use(&$checks):void{
    if(!$passed)throw new RuntimeException($message);
    ++$checks;echo 'PASS '.$message."\n";
};
try{
    $fixture=myTheatreFixture($db);$u=$fixture['users'];
    $actor=static fn(string $name):array=>['id'=>$u[$name],'roles'=>$fixture['roles'][$name],'permissions'=>[]];
    $ok(MemberProfilePhotoService::canView($db,$actor('StudentSingle'),$u['StudentSingle']),'A member can view their own photo');
    $ok(CommunicationExperience::eligibleForNewMessage($db,$actor('Member'),$u['StudentSingle'])!==null,
        'Eligible adult can reach student through guardian-inclusive messaging');
    $ok(CommunicationExperience::eligibleForNewMessage($db,$actor('StudentSingle'),$u['Member'])!==null,
        'Eligible student can contact adult with guardian protections');
    $ok(CommunicationExperience::eligibleForNewMessage($db,$actor('StudentSingle'),$u['StudentB'])===null,
        'Student-to-student shortcut is never offered');
    $results=CommunicationExperience::searchRecipients($db,$actor('Member'),'Theatre StudentSingle');
    $ok(in_array($u['StudentSingle'],array_map('intval',array_column($results,'id')),true),
        'Native recipient search finds eligible student under existing safeguarding rules');

    $ok(MemberProfilePhotoService::canView($db,$actor('GuardianOne'),$u['StudentSingle']),'An active parent or guardian can view their linked student');
    $ok(MemberProfilePhotoService::canView($db,$actor('StudentSingle'),$u['GuardianOne']),'An active student can view their guardian');
    $ok(!MemberProfilePhotoService::canView($db,$actor('FormerGuardian'),$u['StudentSingle']),'Former guardian has no photo permission from inactive relationship');
    $ok(!MemberProfilePhotoService::canView($db,$actor('Member'),$u['Volunteer']),'Unrelated members cannot access each others photos');
    $thread=CommunicationExperience::mobileCreateConversation($db,$actor('Member'),$u['Volunteer'],'','Hello');
    $ok($thread>0&&MemberProfilePhotoService::canView($db,$actor('Member'),$u['Volunteer']),'Active conversation permits participant image access');
    $db->prepare('UPDATE conversation_participants SET access_revoked_at=NOW() WHERE conversation_id=? AND user_id=?')->execute([$thread,$u['Member']]);
    $ok(!MemberProfilePhotoService::canView($db,$actor('Member'),$u['Volunteer']),'Revoked conversation access no longer reveals participant photo');
    $ok(!MemberProfilePhotoService::canView($db,$actor('Member'),$u['Disabled']),'Inactive member photo remains unavailable');
    $ok($db->query("SHOW TABLES LIKE 'member_profile_photos'")->fetchColumn()!==false,'Private profile photo migration applied');
    echo "PASS $checks real database photo authorization assertions.\n";
}finally{if($fixture)retireMyTheatreFixture($db,$fixture);}
