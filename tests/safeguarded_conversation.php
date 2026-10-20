<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/SafeguardedConversationPolicy.php';

$db=Database::connect(dirname(__DIR__));
$db->beginTransaction();
$checks=0;
$suffix=bin2hex(random_bytes(5));

$assert=static function(bool $ok,string $message)use(&$checks):void{
    if(!$ok)throw new RuntimeException($message);
    $checks++;
};
$user=static function(PDO $db,string $label,string $role,string $suffix):int{
    $email=strtolower(str_replace(' ','-',$label)).'.'.$suffix.'@safeguarding.test';
    $s=$db->prepare("INSERT INTO users (first_name,last_name,email,initials,display_role,active,account_status,organization_membership_status,email_verified_at) VALUES (:first,:last,:email,:initials,:role,1,'active','approved',CURRENT_TIMESTAMP)");
    $parts=explode(' ',$label,2);$first=$parts[0];$last=$parts[1]??'Test';
    $s->execute(['first'=>$first,'last'=>$last,'email'=>$email,'initials'=>strtoupper(substr($first,0,1).substr($last,0,1)),'role'=>$role]);
    return(int)$db->lastInsertId();
};
$grant=static function(PDO $db,int $userId,string $role):void{
    $s=$db->prepare("INSERT INTO auth_user_roles (user_id,role_id) SELECT :user,id FROM auth_roles WHERE code=:role AND active=1");
    $s->execute(['user'=>$userId,'role'=>$role]);
    if($s->rowCount()!==1)throw new RuntimeException('Missing role fixture: '.$role);
};
$participant=static function(PDO $db,int $conversationId,int $userId,string $role,int $required=0):void{
    $s=$db->prepare("INSERT INTO conversation_participants (conversation_id,user_id,participant_role,guardian_required) VALUES (:conversation,:user,:role,:required)");
    $s->execute(['conversation'=>$conversationId,'user'=>$userId,'role'=>$role,'required'=>$required]);
};
$conversation=static function(PDO $db,string $type,string $subject):int{
    $s=$db->prepare("INSERT INTO conversations (subject,conversation_type,created_at) VALUES (:subject,:type,CURRENT_TIMESTAMP)");
    $s->execute(['subject'=>$subject,'type'=>$type]);return(int)$db->lastInsertId();
};
$message=static function(PDO $db,int $conversationId,int $senderId,string $body):int{
    $s=$db->prepare("INSERT INTO messages (conversation_id,sender_user_id,body,created_at) VALUES (:conversation,:sender,:body,CURRENT_TIMESTAMP)");
    $s->execute(['conversation'=>$conversationId,'sender'=>$senderId,'body'=>$body]);return(int)$db->lastInsertId();
};

try{
    $student=$user($db,'Student One','Student',$suffix);$grant($db,$student,'member');$grant($db,$student,'student');
    $guardianA=$user($db,'Guardian Alpha','Parent',$suffix);$grant($db,$guardianA,'member');
    $guardianB=$user($db,'Guardian Beta','Parent',$suffix);$grant($db,$guardianB,'member');
    $staff=$user($db,'Staff Member','Production Staff',$suffix);$grant($db,$staff,'member');$grant($db,$staff,'production_staff');

    $rel=$db->prepare("INSERT INTO family_relationships (guardian_user_id,student_user_id,relationship_type,is_primary,status) VALUES (:guardian,:student,'guardian',:primary,'active')");
    $rel->execute(['guardian'=>$guardianA,'student'=>$student,'primary'=>1]);
    $rel->execute(['guardian'=>$guardianB,'student'=>$student,'primary'=>0]);

    $thread=$conversation($db,'safeguarded','Two guardian regression');
    $participant($db,$thread,$student,'student');
    $participant($db,$thread,$staff,'adult');
    $participant($db,$thread,$guardianA,'guardian',1);
    $historical=$message($db,$thread,$staff,'Historical message');

    $state=SafeguardedConversationPolicy::enforceForSend($db,$thread,$staff);
    sort($state['guardian_ids']);
    $expected=[$guardianA,$guardianB];sort($expected);
    $assert($state['guardian_ids']===$expected,'Every active guardian was not synchronized.');
    $count=$db->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id=:conversation AND participant_role='guardian' AND access_revoked_at IS NULL");
    $count->execute(['conversation'=>$thread]);$assert((int)$count->fetchColumn()===2,'Two live guardians were not retained.');

    $db->prepare("UPDATE family_relationships SET status='inactive' WHERE guardian_user_id=:guardian AND student_user_id=:student")
        ->execute(['guardian'=>$guardianA,'student'=>$student]);
    $revoked=$db->prepare("SELECT access_revoked_at,history_through_message_id FROM conversation_participants WHERE conversation_id=:conversation AND user_id=:guardian");
    $revoked->execute(['conversation'=>$thread,'guardian'=>$guardianA]);$revokedRow=$revoked->fetch();
    $assert($revokedRow&&$revokedRow['access_revoked_at']!==null,'Former guardian was not revoked.');
    $assert((int)$revokedRow['history_through_message_id']===$historical,'Historical cutoff was not captured.');

    SafeguardedConversationPolicy::enforceForSend($db,$thread,$staff);
    $future=$message($db,$thread,$staff,'Future message');
    $assert($future>$historical,'Future message fixture was not created.');
    $visibleA=$db->prepare("SELECT COUNT(*) FROM messages m JOIN conversation_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=:user WHERE m.conversation_id=:conversation AND (cp.history_through_message_id IS NULL OR m.id<=cp.history_through_message_id)");
    $visibleA->execute(['user'=>$guardianA,'conversation'=>$thread]);$assert((int)$visibleA->fetchColumn()===1,'Former guardian can see future content.');
    $visibleA->execute(['user'=>$guardianB,'conversation'=>$thread]);$assert((int)$visibleA->fetchColumn()===2,'Remaining guardian lost current content.');
    $assert(!SafeguardedConversationPolicy::canReceiveLive($db,$thread,$guardianA),'Former guardian remained a live notification recipient.');
    $assert(SafeguardedConversationPolicy::canReceiveLive($db,$thread,$guardianB),'Current guardian lost notification eligibility.');
    $assert(SafeguardedConversationPolicy::canReceiveLive($db,$thread,$staff),'Staff lost safeguarded eligibility.');

    $lastBlocked=false;
    try{$db->prepare("UPDATE family_relationships SET status='inactive' WHERE guardian_user_id=:guardian AND student_user_id=:student")
        ->execute(['guardian'=>$guardianB,'student'=>$student]);}catch(PDOException){$lastBlocked=true;}
    $assert($lastBlocked,'Removing the last eligible guardian was not blocked.');

    $guardianC=$user($db,'Guardian Gamma','Parent',$suffix);$grant($db,$guardianC,'member');
    $rel->execute(['guardian'=>$guardianC,'student'=>$student,'primary'=>0]);
    $db->prepare("UPDATE users SET account_status='disabled' WHERE id=:id")->execute(['id'=>$guardianC]);
    $assert(!SafeguardedConversationPolicy::canReceiveLive($db,$thread,$guardianC),'Disabled guardian remained eligible.');

    $newStudent=$user($db,'Role Change','Member',$suffix);$grant($db,$newStudent,'member');
    $direct=$conversation($db,'direct','Role change regression');
    $participant($db,$direct,$staff,'adult');$participant($db,$direct,$newStudent,'adult');
    $message($db,$direct,$staff,'Before role change');
    $grant($db,$newStudent,'student');
    $rel->execute(['guardian'=>$guardianB,'student'=>$newStudent,'primary'=>1]);
    $converted=SafeguardedConversationPolicy::enforceForSend($db,$direct,$staff);
    $assert($converted['conversation_type']==='safeguarded','Role change did not upgrade the conversation.');
    $assert(in_array($guardianB,$converted['guardian_ids'],true),'Role-change safeguarding did not add the guardian.');

    $otherStudent=$user($db,'Student Two','Student',$suffix);$grant($db,$otherStudent,'member');$grant($db,$otherStudent,'student');
    $studentThread=$conversation($db,'direct','Student prohibition');
    $participant($db,$studentThread,$newStudent,'adult');$participant($db,$studentThread,$otherStudent,'adult');
    $studentBlocked=false;try{SafeguardedConversationPolicy::enforceForSend($db,$studentThread,$newStudent);}catch(RuntimeException){$studentBlocked=true;}
    $assert($studentBlocked,'Student-to-student direct messaging was allowed.');

    $db->prepare("UPDATE users SET account_status='disabled' WHERE id=:id")->execute(['id'=>$newStudent]);
    $disabledBlocked=false;try{SafeguardedConversationPolicy::enforceForSend($db,$direct,$staff);}catch(RuntimeException){$disabledBlocked=true;}
    $assert($disabledBlocked,'A disabled Student remained live in messaging.');

    $history=$db->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id=:conversation");
    $history->execute(['conversation'=>$thread]);$assert((int)$history->fetchColumn()===2,'Historical message records were deleted.');

    $db->rollBack();
    echo "PASS: {$checks} safeguarding checks (real database transaction rolled back).\n";
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();
    fwrite(STDERR,'FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}
