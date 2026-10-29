<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';

/** Two distinct authorized reviewers; never self-reported DOB-only conversion. */
final class ParticipantTransitionService
{
    private static function reviewer(PDO $db,int $actor,bool $safeguarding):void
    {
        Auth::clearRequestCache($actor);
        $q=$db->prepare("SELECT id FROM users WHERE id=? AND active=1 AND account_status='active' FOR UPDATE");$q->execute([$actor]);
        $permissions=Auth::permissions($db,$actor);
        if(!$q->fetchColumn()||!in_array('accounts.manage',$permissions,true)||($safeguarding&&!in_array('safeguarding.manage',$permissions,true)))throw new RuntimeException('Current account administration and the required safeguarding review permission are required.');
    }

    private static function reason(string $reason):string
    {
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>1000)throw new RuntimeException('Record a review reason (up to 1,000 characters).');return $reason;
    }

    public static function request(PDO $db,int $actor,int $subject,string $reason):int
    {
        $reason=self::reason($reason);$db->beginTransaction();
        try{
            self::reviewer($db,$actor,false);
            $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$subject]);
            Auth::clearRequestCache($subject);
            if(!$q->fetchColumn()||!IdentityRolePolicy::isStudent($db,$subject))throw new RuntimeException('Select a current Student.');
            $q=$db->prepare("SELECT id FROM participant_transition_reviews WHERE user_id=? AND status='pending' FOR UPDATE");$q->execute([$subject]);
            if($q->fetchColumn())throw new RuntimeException('This Student already has a pending review.');
            $db->prepare('INSERT INTO participant_transition_reviews(user_id,requested_by_user_id,reason) VALUES(?,?,?)')->execute([$subject,$actor,$reason]);$id=(int)$db->lastInsertId();
            ProductionParticipantEligibility::audit($db,$actor,$subject,'transition_requested',['review_id'=>$id,'reason'=>$reason]);$db->commit();return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function decide(PDO $db,int $actor,int $reviewId,bool $approve,string $reason):void
    {
        $reason=self::reason($reason);$db->beginTransaction();
        try{
            self::reviewer($db,$actor,true);
            $q=$db->prepare('SELECT * FROM participant_transition_reviews WHERE id=? FOR UPDATE');$q->execute([$reviewId]);$review=$q->fetch();
            if(!$review||$review['status']!=='pending'||(int)$review['requested_by_user_id']===$actor)throw new RuntimeException('A different authorized reviewer must decide a pending request.');
            if($approve)self::reviewer($db,(int)$review['requested_by_user_id'],false);
            $subject=(int)$review['user_id'];$q=$db->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$subject]);$person=$q->fetch();
            Auth::clearRequestCache($subject);
            if(!$person||!IdentityRolePolicy::isStudent($db,$subject))throw new RuntimeException('The Student identity changed; request a fresh review.');
            if($approve){
                if(!(int)$person['active']||$person['account_status']!=='active'||!$person['email_verified_at']||!$person['password_hash']||!$person['date_of_birth']||PersonEligibility::ageOn($person['date_of_birth'],date('Y-m-d'))<18)throw new RuntimeException('Verify age 18+, independent active sign-in and verified email before approval.');
                // Freeze every participant's existing safeguarded history. No prior
                // guardian becomes entitled to this person's future Adult messages.
                $q=$db->prepare("SELECT c.id,(SELECT COALESCE(MAX(m.id),0) FROM messages m WHERE m.conversation_id=c.id) cutoff FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=? WHERE c.conversation_type='safeguarded' FOR UPDATE");$q->execute([$subject]);
                foreach($q->fetchAll() as $thread)$db->prepare('UPDATE conversation_participants SET access_revoked_at=COALESCE(access_revoked_at,NOW()),history_through_message_id=COALESCE(history_through_message_id,?) WHERE conversation_id=?')->execute([(int)$thread['cutoff'],(int)$thread['id']]);
                // The last-guardian trigger recognizes only an approved two-reviewer
                // transition after Student identity is retired. No temporary account
                // disable or unrelated volunteer cancellation is used.
                $db->prepare("UPDATE participant_transition_reviews SET status='approved',reviewed_by_user_id=?,reviewed_at=NOW(),review_reason=? WHERE id=?")->execute([$actor,$reason,$reviewId]);
                $db->prepare('UPDATE users SET adult_participant=1,identity_revision=identity_revision+1 WHERE id=?')->execute([$subject]);
                $db->prepare("DELETE ur FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.code='student'")->execute([$subject]);
                $db->prepare("UPDATE family_relationships SET status='inactive',updated_at=NOW() WHERE student_user_id=? AND status='active'")->execute([$subject]);
                $db->prepare("UPDATE production_memberships SET status='inactive',updated_at=NOW() WHERE user_id=? AND audience_type='student' AND status='active'")->execute([$subject]);
                $db->prepare("UPDATE production_participant_functions SET status='inactive',updated_at=NOW() WHERE user_id=? AND status='active'")->execute([$subject]);
                $db->prepare("UPDATE theatre_history_credits SET participation_ended_at=COALESCE(participation_ended_at,NOW()) WHERE user_id=? AND (credit_kind='performance' OR credit_kind LIKE 'cast-%' OR credit_kind LIKE 'crew-%' OR credit_kind LIKE 'creative-%')")->execute([$subject]);
                // Membership reenrollment is explicit: no category or age exception
                // can carry across classification into an ineligible production.
                $db->prepare('UPDATE auth_mobile_tokens SET revoked_at=COALESCE(revoked_at,NOW()) WHERE user_id=?')->execute([$subject]);
                $db->prepare("UPDATE push_subscriptions SET status='revoked' WHERE user_id=?")->execute([$subject]);
                Auth::clearRequestCache($subject);
            }
            $db->prepare('UPDATE participant_transition_reviews SET status=?,reviewed_by_user_id=?,reviewed_at=NOW(),review_reason=? WHERE id=?')->execute([$approve?'approved':'rejected',$actor,$reason,$reviewId]);
            ProductionParticipantEligibility::audit($db,$actor,$subject,$approve?'transition_approved':'transition_rejected',['review_id'=>$reviewId,'requester'=>(int)$review['requested_by_user_id'],'reason'=>$reason,'account_preserved'=>true,'reenrollment'=>'explicit']);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
