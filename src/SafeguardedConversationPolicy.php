<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

final class SafeguardedConversationPolicy
{
    public static function eligibleGuardians(PDO $db, int $studentId): array
    {
        $stmt = $db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role
            FROM family_relationships fr
            JOIN users u ON u.id=fr.guardian_user_id
                AND u.active=1
                AND u.account_status='active'
            WHERE fr.student_user_id=:student
                AND fr.status='active'
                AND fr.relationship_type IN ('parent','guardian')
            ORDER BY fr.is_primary DESC,fr.id,u.id");
        $stmt->execute(['student'=>$studentId]);
        return $stmt->fetchAll();
    }

    public static function findReusableConversation(PDO $db, int $studentId, int $staffId, array $guardianIds): ?int
    {
        $guardianIds = array_values(array_unique(array_map('intval',$guardianIds)));
        sort($guardianIds);
        if ($studentId < 1 || $staffId < 1 || !$guardianIds) return null;

        $candidateStmt = $db->prepare("SELECT DISTINCT c.id
            FROM conversations c
            JOIN conversation_participants student_cp
              ON student_cp.conversation_id=c.id
             AND student_cp.user_id=:student
             AND student_cp.participant_role='student'
             AND student_cp.access_revoked_at IS NULL
            JOIN conversation_participants staff_cp
              ON staff_cp.conversation_id=c.id
             AND staff_cp.user_id=:staff
             AND staff_cp.participant_role='adult'
             AND staff_cp.access_revoked_at IS NULL
            WHERE c.conversation_type='safeguarded'
            ORDER BY c.id DESC");
        $candidateStmt->execute(['student'=>$studentId,'staff'=>$staffId]);
        $expectedIds = array_merge([$studentId,$staffId],$guardianIds);
        sort($expectedIds);

        foreach ($candidateStmt->fetchAll(PDO::FETCH_COLUMN) as $candidateId) {
            $participants = self::participants($db,(int)$candidateId,false);
            $liveIds = [];
            $candidateGuardians = [];
            $valid = true;
            foreach ($participants as $participant) {
                if (!(bool)$participant['active']
                    || $participant['account_status'] !== 'active'
                    || $participant['access_revoked_at'] !== null) {
                    $valid = false;
                    break;
                }
                $userId = (int)$participant['user_id'];
                $liveIds[] = $userId;
                if ($participant['participant_role'] === 'guardian') $candidateGuardians[] = $userId;
            }
            sort($liveIds);
            sort($candidateGuardians);
            if ($valid && $liveIds === $expectedIds && $candidateGuardians === $guardianIds) {
                return (int)$candidateId;
            }
        }
        return null;
    }

    public static function enforceForSend(PDO $db, int $conversationId, int $senderId): array
    {
        if ($conversationId < 1 || $senderId < 1) {
            throw new RuntimeException('That conversation is unavailable.');
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();

        try {
            $conversationStmt = $db->prepare('SELECT id,conversation_type FROM conversations WHERE id=:id FOR UPDATE');
            $conversationStmt->execute(['id'=>$conversationId]);
            $conversation = $conversationStmt->fetch();
            if (!$conversation) throw new RuntimeException('That conversation no longer exists.');

            $participants = self::participants($db,$conversationId,true);
            $studentRoleIds = self::studentRoleIds($db,$participants);
            if (count($studentRoleIds) > 1) {
                throw new RuntimeException('Student-to-student messaging is not available.');
            }
            $currentStudents = self::currentStudents($db,$participants);
            if ($studentRoleIds && !$currentStudents) {
                throw new RuntimeException('Messaging is paused because the Student account is unavailable.');
            }

            if (!$currentStudents) {
                if ((string)$conversation['conversation_type'] === 'safeguarded') {
                    throw new RuntimeException('Messaging is paused because this safeguarded conversation no longer has an available Student.');
                }
                self::assertLiveSender($participants,$senderId);
                if ($ownsTransaction) $db->commit();
                return ['conversation_type'=>'direct','student_id'=>null,'guardian_ids'=>[]];
            }

            $studentId = $currentStudents[0];
            $studentParticipants = array_values(array_filter($participants,static fn(array $row):bool=>(int)$row['user_id']===$studentId));
            if (!$studentParticipants) throw new RuntimeException('The current Student is not a conversation participant.');

            $db->prepare("UPDATE conversations SET conversation_type='safeguarded' WHERE id=:id")->execute(['id'=>$conversationId]);
            $db->prepare("UPDATE conversation_participants SET participant_role='student',guardian_required=0 WHERE conversation_id=:conversation AND user_id=:student AND access_revoked_at IS NULL")
                ->execute(['conversation'=>$conversationId,'student'=>$studentId]);

            $guardians = self::eligibleGuardians($db,$studentId);
            if (!$guardians) {
                throw new RuntimeException('Messaging is paused because the Student has no active available guardian.');
            }
            $guardianIds = array_values(array_unique(array_map(static fn(array $row):int=>(int)$row['id'],$guardians)));
            $insert = $db->prepare("INSERT INTO conversation_participants
                (conversation_id,user_id,participant_role,guardian_required,access_revoked_at,history_through_message_id)
                VALUES (:conversation,:user,'guardian',1,NULL,NULL)
                ON DUPLICATE KEY UPDATE participant_role='guardian',guardian_required=1");
            foreach ($guardianIds as $guardianId) {
                if ($guardianId === $studentId) continue;
                $insert->execute(['conversation'=>$conversationId,'user'=>$guardianId]);
            }

            $cutoff = self::latestMessageId($db,$conversationId);
            $guardianRows = $db->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id=:conversation AND participant_role='guardian' FOR UPDATE");
            $guardianRows->execute(['conversation'=>$conversationId]);
            $revoke = $db->prepare("UPDATE conversation_participants
                SET access_revoked_at=COALESCE(access_revoked_at,CURRENT_TIMESTAMP),
                    history_through_message_id=COALESCE(history_through_message_id,:cutoff)
                WHERE conversation_id=:conversation AND user_id=:user");
            foreach ($guardianRows->fetchAll(PDO::FETCH_COLUMN) as $guardianId) {
                if (!in_array((int)$guardianId,$guardianIds,true)) {
                    $revoke->execute(['cutoff'=>$cutoff,'conversation'=>$conversationId,'user'=>(int)$guardianId]);
                }
            }

            $participants = self::participants($db,$conversationId,true);
            self::assertLiveSender($participants,$senderId);

            $active = array_values(array_filter($participants,static fn(array $row):bool=>
                (bool)$row['active']
                && $row['account_status']==='active'
                && $row['access_revoked_at']===null
            ));
            $liveStudentIds = self::currentStudents($db,$active);
            if ($liveStudentIds !== [$studentId]) {
                throw new RuntimeException('Messaging is paused because the Student identity requires review.');
            }

            $staffCurrent = false;
            $liveGuardianIds = [];
            foreach ($active as $participant) {
                $roles = Auth::roles($db,(int)$participant['user_id']);
                if ($participant['participant_role']==='adult'
                    && (in_array('production_staff',$roles,true)||in_array('administrator',$roles,true))) {
                    $staffCurrent = true;
                }
                if ($participant['participant_role']==='guardian') {
                    $liveGuardianIds[] = (int)$participant['user_id'];
                }
            }
            if (!$staffCurrent) {
                throw new RuntimeException('Messaging is paused because this conversation no longer includes current CTSMD staff.');
            }
            sort($guardianIds);
            $liveGuardianIds = array_values(array_unique($liveGuardianIds));
            sort($liveGuardianIds);
            if ($guardianIds !== $liveGuardianIds) {
                throw new RuntimeException('Messaging is paused until every active guardian is included.');
            }

            if ($ownsTransaction) $db->commit();
            return ['conversation_type'=>'safeguarded','student_id'=>$studentId,'guardian_ids'=>$guardianIds];
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e instanceof RuntimeException ? $e : new RuntimeException('The safeguarding policy could not validate this conversation.');
        }
    }

    public static function canReceiveLive(PDO $db, int $conversationId, int $userId): bool
    {
        if ($conversationId < 1 || $userId < 1) return false;
        $conversationStmt = $db->prepare('SELECT conversation_type FROM conversations WHERE id=:id LIMIT 1');
        $conversationStmt->execute(['id'=>$conversationId]);
        $type = (string)($conversationStmt->fetchColumn() ?: '');
        if ($type === '') return false;

        $participants = self::participants($db,$conversationId,false);
        $recipient = null;
        foreach ($participants as $participant) {
            if ((int)$participant['user_id'] === $userId) $recipient = $participant;
        }
        if (!$recipient
            || !(bool)$recipient['active']
            || $recipient['account_status']!=='active'
            || $recipient['access_revoked_at']!==null) return false;

        $studentRoleIds = self::studentRoleIds($db,$participants);
        $students = self::currentStudents($db,array_values(array_filter($participants,static fn(array $row):bool=>
            (bool)$row['active'] && $row['account_status']==='active' && $row['access_revoked_at']===null
        )));
        if ($type === 'direct') return !$studentRoleIds;
        if (count($students)!==1) return false;

        $studentId = $students[0];
        if ($recipient['participant_role']==='student') return $userId===$studentId;
        if ($recipient['participant_role']==='guardian') {
            $eligible = array_map(static fn(array $row):int=>(int)$row['id'],self::eligibleGuardians($db,$studentId));
            return in_array($userId,$eligible,true);
        }
        $roles = Auth::roles($db,$userId);
        return in_array('production_staff',$roles,true)||in_array('administrator',$roles,true);
    }

    private static function participants(PDO $db,int $conversationId,bool $lock):array
    {
        $sql = "SELECT cp.user_id,cp.participant_role,cp.guardian_required,cp.access_revoked_at,cp.history_through_message_id,u.active,u.account_status
            FROM conversation_participants cp
            JOIN users u ON u.id=cp.user_id
            WHERE cp.conversation_id=:conversation".($lock?' FOR UPDATE':'');
        $stmt=$db->prepare($sql);$stmt->execute(['conversation'=>$conversationId]);
        return $stmt->fetchAll();
    }

    private static function studentRoleIds(PDO $db,array $participants):array
    {
        $students=[];
        foreach($participants as $participant){
            if(in_array('student',Auth::roles($db,(int)$participant['user_id']),true))$students[]=(int)$participant['user_id'];
        }
        $students=array_values(array_unique($students));
        sort($students);
        return $students;
    }

    private static function currentStudents(PDO $db,array $participants):array
    {
        $students=[];
        foreach($participants as $participant){
            if(!(bool)$participant['active']||$participant['account_status']!=='active'||$participant['access_revoked_at']!==null)continue;
            if(in_array('student',Auth::roles($db,(int)$participant['user_id']),true))$students[]=(int)$participant['user_id'];
        }
        $students=array_values(array_unique($students));
        sort($students);
        return $students;
    }

    private static function assertLiveSender(array $participants,int $senderId):void
    {
        foreach($participants as $participant){
            if((int)$participant['user_id']!==$senderId)continue;
            if((bool)$participant['active']&&$participant['account_status']==='active'&&$participant['access_revoked_at']===null)return;
            break;
        }
        throw new RuntimeException('You have historical access only and cannot send new messages in this conversation.');
    }

    private static function latestMessageId(PDO $db,int $conversationId):int
    {
        $stmt=$db->prepare('SELECT COALESCE(MAX(id),0) FROM messages WHERE conversation_id=:conversation');
        $stmt->execute(['conversation'=>$conversationId]);
        return (int)$stmt->fetchColumn();
    }
}
