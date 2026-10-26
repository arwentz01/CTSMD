<?php
declare(strict_types=1);

require_once __DIR__.'/ProductionLifecycleGuard.php';

final class CastingRoleService {
    public static function roles(PDO $db,int $productionId):array {
        $s=$db->prepare("SELECT id,title,category,sort_order FROM production_cast_roles WHERE production_id=:p AND active=1 ORDER BY sort_order,FIELD(category,'principal','supporting','minor','ensemble','other'),title,id");
        $s->execute(['p'=>$productionId]);return $s->fetchAll();
    }
    public static function create(PDO $db,int $productionId,string $title,string $category,int $order):void {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        $title=trim($title);
        if($title===''||mb_strlen($title)>190)throw new RuntimeException('Enter a role name under 191 characters.');
        if(!in_array($category,['principal','supporting','minor','ensemble','other'],true))throw new RuntimeException('Choose a valid role category.');
        $s=$db->prepare("INSERT INTO production_cast_roles (production_id,title,category,sort_order) VALUES (:p,:title,:category,:sort) ON DUPLICATE KEY UPDATE category=VALUES(category),sort_order=VALUES(sort_order),active=1");
        $s->execute(['p'=>$productionId,'title'=>$title,'category'=>$category,'sort'=>$order]);
    }
    public static function assigned(PDO $db,int $productionId,int $creditId,bool $manual=false):array {
        $column=$manual?'a.manual_credit_id':'a.casting_record_id';
        $s=$db->prepare("SELECT r.id,r.title FROM production_cast_role_assignments a JOIN production_cast_roles r ON r.id=a.role_id AND r.production_id=:p AND r.active=1 WHERE $column=:id ORDER BY r.sort_order,r.title");
        $s->execute(['p'=>$productionId,'id'=>$creditId]);return $s->fetchAll();
    }
    public static function set(PDO $db,int $productionId,int $recordId,array $roleIds,bool $manual=false):void {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        $ids=array_values(array_unique(array_map('intval',$roleIds)));
        if(count($ids)>40||in_array(0,$ids,true)||array_filter($ids,static fn(int $id):bool=>$id<0))throw new RuntimeException('Choose valid casting roles.');
        $table=$manual?'manual_participant_credits':'production_casting_records';
        $check=$db->prepare("SELECT id FROM $table WHERE id=:id AND production_id=:p".($manual?' AND active=1':'')." LIMIT 1");
        $check->execute(['id'=>$recordId,'p'=>$productionId]);if(!$check->fetchColumn())throw new RuntimeException('Casting participant not found in this production.');
        if($ids){$placeholders=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("SELECT COUNT(*) FROM production_cast_roles WHERE production_id=? AND active=1 AND id IN ($placeholders)");$q->execute(array_merge([$productionId],$ids));if((int)$q->fetchColumn()!==count($ids))throw new RuntimeException('An assigned role is not in this production.');}
        $column=$manual?'manual_credit_id':'casting_record_id';
        $db->beginTransaction();
        try {
            $delete=$db->prepare("DELETE FROM production_cast_role_assignments WHERE $column=:id");$delete->execute(['id'=>$recordId]);
            $insert=$db->prepare("INSERT INTO production_cast_role_assignments (role_id,$column) VALUES (:role,:id)");
            foreach($ids as $id)$insert->execute(['role'=>$id,'id'=>$recordId]);
            $names=array_column(self::assigned($db,$productionId,$recordId,$manual),'title');
            // Retain one compatible display value for existing roster, email, and playbill consumers.
            $display=$names?implode(' / ',$names):null;
            if($display!==null&&mb_strlen($display)>190)throw new RuntimeException('Selected role names exceed the casting display limit.');
            $db->prepare("UPDATE $table SET role_title=:role WHERE id=:id")->execute(['role'=>$display,'id'=>$recordId]);
            if(!$manual){
                $db->prepare("UPDATE production_memberships pm JOIN production_casting_records cr ON cr.production_membership_id=pm.id SET pm.participation_role=:role WHERE cr.id=:id AND cr.production_id=:production AND pm.audience_type='student'")->execute(['role'=>$display,'id'=>$recordId,'production'=>$productionId]);
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
