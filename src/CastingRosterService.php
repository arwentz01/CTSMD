<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';
require_once __DIR__.'/TheatreHistoryService.php';
require_once __DIR__.'/CastingCommunicationService.php';
final class CastingRosterService
{
    public static function remove(PDO $db, array $actor, int $productionId, int $membershipId): void
    {
        if ($membershipId < 1) {
            throw new RuntimeException('That membership could not be found.');
        }

        $ownsTransaction=!$db->inTransaction();
        if($ownsTransaction)$db->beginTransaction();
        try {
            ProductionLifecycleGuard::assertWritable($db,$productionId,true);
            ProductionParticipantEligibility::assertManager($db,(int)$actor['id'],$productionId);
            $stmt = $db->prepare("SELECT pm.id, pm.user_id, pm.audience_type, pm.participation_role, pm.status, CONCAT(u.first_name, ' ', u.last_name) AS name FROM production_memberships pm JOIN users u ON u.id = pm.user_id WHERE pm.id = :id AND pm.production_id = :production_id FOR UPDATE");
            $stmt->execute(['id' => $membershipId, 'production_id' => $productionId]);
            $membership = $stmt->fetch();
            if (!$membership || $membership['status'] !== 'active') {
                throw new RuntimeException('That active production membership could not be found.');
            }

            if(!in_array($membership['audience_type'],['student','adult'],true))throw new RuntimeException('Only cast responsibilities can be removed through Casting.');
            $update = $db->prepare("UPDATE production_memberships SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute(['id' => $membershipId]);
            $autoRetiredGuardians = [];
            $removedCasting = [];
            if ($membership['audience_type'] === 'student' || $membership['audience_type'] === 'adult') {
                $db->prepare("UPDATE production_participant_functions SET status='inactive',updated_at=NOW() WHERE production_id=? AND user_id=? AND function_type='cast'")->execute([$productionId,(int)$membership['user_id']]);
                $casting=$db->prepare("SELECT cr.id,cr.casting_status,cr.role_title,cr.participation_track,cr.staff_notes FROM production_casting_records cr WHERE cr.production_id=:production AND cr.user_id=:student FOR UPDATE");
                $casting->execute(['production'=>$productionId,'student'=>(int)$membership['user_id']]);
                $removedCasting=$casting->fetchAll();
                if($removedCasting){
                    // Preserve the decision/offer details in the immutable audit before
                    // removing the active casting record and its dependent role links.
                    $offers=$db->prepare("SELECT o.id,o.status,o.role_snapshot,o.offered_at,o.responded_at,o.responded_by_user_id FROM production_cast_offers o JOIN production_casting_records cr ON cr.id=o.casting_record_id WHERE cr.production_id=:production AND cr.user_id=:student FOR UPDATE");
                    $offers->execute(['production'=>$productionId,'student'=>(int)$membership['user_id']]);
                    $removedOffers=$offers->fetchAll();
                    self::audit($db,(int)$actor['id'],'casting.removed_with_production_membership','production',$productionId,'Retained casting history and withdrew participant during production roster removal.',[
                        'student_user_id'=>(int)$membership['user_id'],
                        'casting_records'=>$removedCasting,
                        'offer_history'=>$removedOffers,
                    ]);
                    // Retain casting, accepted offers and role assignments as history.
                    $db->prepare("UPDATE production_cast_offers o JOIN production_casting_records cr ON cr.id=o.casting_record_id SET o.status='cancelled',o.responded_at=NOW() WHERE cr.production_id=? AND cr.user_id=? AND o.status='pending'")->execute([$productionId,(int)$membership['user_id']]);
                    $delete=$db->prepare("UPDATE production_casting_records SET casting_status='withdrawn',updated_at=CURRENT_TIMESTAMP WHERE production_id=:production AND user_id=:student");
                    $delete->execute(['production'=>$productionId,'student'=>(int)$membership['user_id']]);
                    CastingCommunicationService::refreshPublishedAfterRosterRemoval($db,$productionId);
                }

                TheatreHistoryService::closeStudentCredit($db, $productionId, (int)$membership['user_id']);
                if($membership['audience_type']==='student')$autoRetiredGuardians = self::retireOrphanGuardians($db, $productionId, (int)$membership['user_id']);
            }

            self::audit($db, (int)$actor['id'], 'production.membership_removed', 'production', $productionId, 'Deactivated a production membership.', [
                'membership_id' => $membershipId,
                'user_id' => (int)$membership['user_id'],
                'audience_type' => $membership['audience_type'],
                'participation_role' => $membership['participation_role'],
                'auto_retired_guardian_user_ids' => $autoRetiredGuardians,
                'removed_casting_record_ids' => array_map('intval',array_column($removedCasting,'id')),
            ]);

            if($ownsTransaction)$db->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction&&$db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The production membership could not be removed.');
        }
    }

    private static function retireOrphanGuardians(PDO $db, int $productionId, int $studentId): array
    {
        $guardianStmt = $db->prepare("SELECT DISTINCT gpm.user_id FROM family_relationships fr JOIN production_memberships gpm ON gpm.user_id=fr.guardian_user_id AND gpm.production_id=:production_id AND gpm.audience_type='guardian' AND gpm.status='active' JOIN users guardian ON guardian.id=gpm.user_id AND guardian.active=1 AND guardian.account_status<>'disabled' WHERE fr.student_user_id=:student_id AND fr.status='active' FOR UPDATE");
        $guardianStmt->execute(['production_id' => $productionId, 'student_id' => $studentId]);
        $guardianIds = array_map('intval', $guardianStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$guardianIds) return [];

        $remainingStudent = $db->prepare("SELECT spm.id FROM family_relationships fr JOIN production_memberships spm ON spm.user_id=fr.student_user_id AND spm.production_id=:production_id AND spm.audience_type='student' AND spm.status='active' JOIN users student ON student.id=spm.user_id AND student.active=1 AND student.account_status<>'disabled' WHERE fr.guardian_user_id=:guardian_id AND fr.status='active' LIMIT 1 FOR UPDATE");
        $retire = $db->prepare("UPDATE production_memberships SET status='inactive',updated_at=CURRENT_TIMESTAMP WHERE production_id=:production_id AND user_id=:guardian_id AND audience_type='guardian' AND status='active'");
        $retired = [];
        foreach ($guardianIds as $guardianId) {
            $remainingStudent->execute(['production_id' => $productionId, 'guardian_id' => $guardianId]);
            if ($remainingStudent->fetchColumn()) continue;
            $retire->execute(['production_id' => $productionId, 'guardian_id' => $guardianId]);
            if ($retire->rowCount() > 0) $retired[] = $guardianId;
        }
        return $retired;
    }

    private static function audit(PDO $db, int $actorId, string $eventType, string $subjectType, int $subjectId, string $summary, array $metadata): void
    {
        $stmt = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
        $stmt->execute([
            'actor' => $actorId,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

}
