<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';

/**
 * Readiness is recalculated under the production row lock before completion.
 * Outstanding forms and work are warnings, not silently deleted or auto-resolved.
 */
final class ProductionCompletionService
{
    public static function readiness(PDO $db,int $productionId):array
    {
        if($productionId<1)throw new RuntimeException('Choose a production.');
        $checks=[
            'future_schedule'=>["SELECT COUNT(*) FROM schedule_items WHERE production_id=:id AND starts_at>NOW()",'warning','Future schedule entries remain in history'],
            'pending_forms'=>["SELECT COUNT(*) FROM form_assignments WHERE production_id=:id AND status<>'completed'",'warning','Incomplete form assignments are preserved'],
            'pending_casting_offers'=>["SELECT COUNT(*) FROM production_cast_offers WHERE production_id=:id AND status='pending'",'blocker','Pending casting offers must be resolved before closure'],
            'unmarked_attendance'=>["SELECT COUNT(*) FROM attendance_records a JOIN schedule_items si ON si.id=a.schedule_item_id WHERE si.production_id=:id AND a.status='unmarked'",'warning','Unmarked attendance records remain in history'],
            'draft_notices'=>["SELECT COUNT(*) FROM schedule_change_notices WHERE production_id=:id AND status='draft'",'warning','Draft schedule notices will not be published after closure'],
            'pending_volunteer_approvals'=>["SELECT COUNT(*) FROM volunteer_shift_approval_requests r JOIN volunteer_shifts s ON s.id=r.shift_id WHERE s.production_id=:id AND r.status='pending'",'warning','Pending volunteer requests are retained but cannot be approved after closure'],
        ];
        $items=[];
        foreach($checks as $key=>[$sql,$severity,$description]){
            $stmt=$db->prepare($sql);
            $stmt->execute(['id'=>$productionId]);
            $items[$key]=['count'=>(int)$stmt->fetchColumn(),'severity'=>$severity,'description'=>$description];
        }
        return $items;
    }

    public static function complete(PDO $db,array $actor,int $productionId,string $confirmation,string $note):array
    {
        if(!AccessPolicy::canManageProduction($actor))throw new RuntimeException('Production management permission is required.');
        if($productionId<1)throw new RuntimeException('Choose a production.');
        if(mb_strlen($note)>500)throw new RuntimeException('Completion note must be 500 characters or fewer.');
        $db->beginTransaction();
        try{
            $stmt=$db->prepare('SELECT id,title,lifecycle_stage FROM productions WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$productionId]);
            $production=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$production)throw new RuntimeException('Production not found.');
            if(!in_array($production['lifecycle_stage'],['in_production','performing'],true))
                throw new RuntimeException('Only an in-production or performing show may be completed.');
            if(!hash_equals((string)$production['title'],trim($confirmation)))
                throw new RuntimeException('Type the exact production title to confirm completion.');
            $items=self::readiness($db,$productionId);
            foreach($items as $item){
                if($item['severity']==='blocker' && $item['count']>0)
                    throw new RuntimeException('Completion blocked by unresolved casting offers. Resolve or revoke outstanding offers first.');
            }
            $update=$db->prepare("UPDATE productions SET lifecycle_stage='completed',completed_at=CURRENT_TIMESTAMP,lifecycle_automation_paused=1 WHERE id=:id AND lifecycle_stage=:stage");
            $update->execute(['id'=>$productionId,'stage'=>$production['lifecycle_stage']]);
            if($update->rowCount()!==1)throw new RuntimeException('Production changed; review readiness again.');
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'production.completed','production',:id,:summary,:metadata)");
            $audit->execute(['actor'=>(int)$actor['id'],'id'=>$productionId,'summary'=>'Completed production with retained historical records.','metadata'=>json_encode(['from'=>$production['lifecycle_stage'],'to'=>'completed','note'=>$note,'readiness'=>$items],JSON_THROW_ON_ERROR)]);
            $db->commit();
            return $items;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }
}
