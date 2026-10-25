<?php

declare(strict_types=1);

/**
 * Date-driven stage transitions. The legacy is_active gate intentionally remains
 * unchanged until the lifecycle authorization migration is completed.
 */
final class ProductionLifecycleAutomation
{
    public static function applyDue(PDO $db, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $time=$now->format('Y-m-d H:i:s');
        $counts=['performing'=>0,'completed'=>0,'archived'=>0];
        $db->beginTransaction();
        try {
            $rows=$db->query("SELECT id,lifecycle_stage,performance_starts_at,performance_ends_at,archive_after_at
              FROM productions WHERE lifecycle_stage IN ('in_production','performing','completed') FOR UPDATE")->fetchAll();
            foreach($rows as $p){
                $stage=(string)$p['lifecycle_stage'];
                $target=null;
                // Performance dates are entered as calendar dates. The last day
                // remains Performing until the following midnight.
                $end=(string)($p['performance_ends_at']??'');
                $start=(string)($p['performance_starts_at']??'');
                $finished=$end!==''&&$time>=date('Y-m-d H:i:s',strtotime(substr($end,0,10).' +1 day'));
                $archive=(string)($p['archive_after_at']??'');
                if($archive===''&&$end!=='')$archive=date('Y-m-d H:i:s',strtotime(substr($end,0,10).' +31 days'));
                if($stage==='completed'&&$archive!==''&&$time>=$archive)$target='archived';
                elseif($stage==='performing'&&$finished)$target='completed';
                elseif($stage==='in_production'&&$start!==''&&$time>=$start)$target=$finished?'completed':'performing';
                if($target===null)continue;
                $update=$db->prepare("UPDATE productions SET lifecycle_stage=:stage,completed_at=CASE WHEN :completed=1 THEN COALESCE(completed_at,CURRENT_TIMESTAMP) ELSE completed_at END WHERE id=:id");
                $update->execute(['stage'=>$target,'completed'=>in_array($target,['completed','archived'],true)?1:0,'id'=>$p['id']]);
                $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'production.lifecycle_automatic','production',:id,:summary,:metadata)");
                $audit->execute(['id'=>$p['id'],'summary'=>'Automatic lifecycle transition to '.$target.'.','metadata'=>json_encode(['from'=>$stage,'to'=>$target,'evaluated_at'=>$time],JSON_THROW_ON_ERROR)]);
                $counts[$target]++;
            }
            $db->commit();
            return $counts;
        } catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }
}
