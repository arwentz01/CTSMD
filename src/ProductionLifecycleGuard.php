<?php

declare(strict_types=1);

/** Server-side guard for writes to operational production resources. */
final class ProductionLifecycleGuard
{
    public static function assertWritable(PDO $db,int $productionId):void
    {
        if($productionId<1)throw new RuntimeException('Select a production before making changes.');
        $s=$db->prepare('SELECT lifecycle_stage FROM productions WHERE id=:id LIMIT 1');
        $s->execute(['id'=>$productionId]);
        $stage=$s->fetchColumn();
        if($stage===false)throw new RuntimeException('Production not found.');
        if(in_array((string)$stage,['completed','archived'],true))
            throw new RuntimeException('This production is completed or archived. Operational changes are closed; historical records are retained.');
    }
}
