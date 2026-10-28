<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';

/** A separate, audited archival event; no production history is deleted. */
final class ProductionArchivalWorkflow
{
    public static function archive(PDO $db,array $actor,int $productionId,string $confirmation,string $note):void
    {
        if(!AccessPolicy::canManageProduction($actor))throw new RuntimeException('Production management permission is required.');
        if($productionId<1)throw new RuntimeException('Choose a production.');
        if(mb_strlen($note)>500)throw new RuntimeException('Archive note must be 500 characters or fewer.');
        $db->beginTransaction();
        try{
            $s=$db->prepare('SELECT id,title,lifecycle_stage,is_active,status FROM productions WHERE id=:id FOR UPDATE');
            $s->execute(['id'=>$productionId]);
            $row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('Production not found.');
            if($row['lifecycle_stage']!=='completed')throw new RuntimeException('Only completed productions can be archived.');
            if(!hash_equals((string)$row['title'],trim($confirmation)))throw new RuntimeException('Type the exact production title to confirm archival.');
            $update=$db->prepare("UPDATE productions SET lifecycle_stage='archived',is_active=0,status='archived',deactivated_at=COALESCE(deactivated_at,CURRENT_TIMESTAMP),lifecycle_automation_paused=1 WHERE id=:id AND lifecycle_stage='completed'");
            $update->execute(['id'=>$productionId]);
            if($update->rowCount()!==1)throw new RuntimeException('Production changed; reload before archival.');
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'production.archived','production',:id,:summary,:metadata)");
            $audit->execute(['actor'=>(int)$actor['id'],'id'=>$productionId,'summary'=>'Archived completed production without deleting historical records.','metadata'=>json_encode(['from'=>'completed','to'=>'archived','note'=>$note,'previous_is_active'=>(bool)$row['is_active'],'previous_legacy_status'=>$row['status']],JSON_THROW_ON_ERROR)]);
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }
}
