<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/ProductionContext.php';

final class ProductionReadinessService
{
    public static function build(PDO $db,array $user):array
    {
        if(!AccessPolicy::canManageProduction($user))throw new RuntimeException('Production readiness is restricted to production staff.');
        $production=ProductionContext::selected($db,$user);if(!$production)return ['production'=>null];
        $id=(int)$production['id'];

        $signals=[];
        $students=self::count($db,"SELECT COUNT(DISTINCT pm.user_id) FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=? AND pm.audience_type='student' AND pm.status='active'",[$id]);
        $guardiansMissing=self::count($db,"SELECT COUNT(*) FROM production_memberships pm JOIN users student ON student.id=pm.user_id AND student.active=1 AND student.account_status<>'disabled' WHERE pm.production_id=? AND pm.audience_type='student' AND pm.status='active' AND NOT EXISTS (SELECT 1 FROM family_relationships fr JOIN production_memberships gm ON gm.production_id=pm.production_id AND gm.user_id=fr.guardian_user_id AND gm.audience_type='guardian' AND gm.status='active' JOIN users guardian ON guardian.id=gm.user_id AND guardian.active=1 AND guardian.account_status<>'disabled' WHERE fr.student_user_id=pm.user_id AND fr.status='active')",[$id]);
        $signals[]=['key'=>'roster','label'=>'Roster & guardians','count'=>$guardiansMissing,'status'=>$guardiansMissing?'attention':'ready','detail'=>$students.' active student'.($students===1?'':'s').($guardiansMissing?' · '.$guardiansMissing.' missing production guardian coverage':' · guardian coverage complete'),'href'=>'/production/people'];

        $forms=self::count($db,"SELECT COUNT(*) FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 JOIN users subject ON subject.id=COALESCE(fa.subject_user_id,fa.user_id) AND subject.active=1 AND subject.account_status<>'disabled' WHERE fa.production_id=? AND fa.status<>'completed'",[$id]);
        $signals[]=['key'=>'forms','label'=>'Forms','count'=>$forms,'status'=>$forms?'attention':'ready','detail'=>$forms?$forms.' assignment'.($forms===1?'':'s').' still open':'No open production form assignments','href'=>'/admin/forms/manage'];

        $openSlots=self::openVolunteerSlots($db,$id);
        $signals[]=['key'=>'volunteer','label'=>'Volunteer coverage','count'=>$openSlots,'status'=>$openSlots?'attention':'ready','detail'=>$openSlots?$openSlots.' upcoming volunteer slot'.($openSlots===1?'':'s').' still uncovered':'Upcoming volunteer shifts are covered','href'=>'/admin/volunteer-shifts'];

        $futureCalls=self::count($db,"SELECT COUNT(*) FROM schedule_items WHERE production_id=? AND status='active' AND starts_at>=NOW()",[$id]);
        $signals[]=['key'=>'schedule','label'=>'Schedule','count'=>$futureCalls,'status'=>$futureCalls?'ready':'attention','detail'=>$futureCalls?$futureCalls.' upcoming active schedule item'.($futureCalls===1?'':'s'):'No upcoming active schedule items','href'=>'/schedule'];

        $draftNotices=self::count($db,"SELECT COUNT(*) FROM schedule_change_notices WHERE production_id=? AND status='draft'",[$id]);
        $signals[]=['key'=>'notices','label'=>'Production updates','count'=>$draftNotices,'status'=>$draftNotices?'attention':'ready','detail'=>$draftNotices?$draftNotices.' draft notice'.($draftNotices===1?'':'s').' waiting for review':'No draft schedule-change notices','href'=>'/production/notices'];

        $playbill=self::playbill($db,$id);$playbillReady=$playbill&&$playbill['status']==='current'&&!empty($playbill['published_at']);
        $signals[]=['key'=>'playbill','label'=>'Playbill','count'=>$playbillReady?0:1,'status'=>$playbillReady?'ready':'attention','detail'=>$playbillReady?'Current Playbill is published':($playbill?'Playbill exists but is not published/current':'No Playbill has been created'),'href'=>'/playbills'];

        $resourceCount=self::count($db,"SELECT COUNT(*) FROM production_resources WHERE production_id=? AND status='active'",[$id]);
        $fileCount=self::count($db,"SELECT COUNT(*) FROM production_files WHERE production_id=? AND status='active'",[$id]);
        $signals[]=['key'=>'materials','label'=>'Resources & files','count'=>0,'status'=>'neutral','detail'=>$resourceCount.' resource'.($resourceCount===1?'':'s').' · '.$fileCount.' file'.($fileCount===1?'':'s'),'href'=>'/resources'];

        $items=self::checklist($db,$id);$done=0;foreach($items as $item)if($item['status']==='done')$done++;
        $overdue=0;$now=new DateTimeImmutable('now');foreach($items as $item)if($item['status']!=='done'&&!empty($item['due_at'])&&new DateTimeImmutable($item['due_at'])<$now)$overdue++;
        $automatedAttention=0;foreach($signals as $signal)if($signal['status']==='attention')$automatedAttention++;

        return [
            'production'=>$production,
            'signals'=>$signals,
            'checklist'=>$items,
            'staff'=>self::staff($db,$id),
            'summary'=>[
                'automated_attention'=>$automatedAttention,
                'checklist_total'=>count($items),
                'checklist_done'=>$done,
                'checklist_open'=>count($items)-$done,
                'overdue'=>$overdue,
                'readiness_percent'=>count($items)?(int)round(($done/count($items))*100):null,
            ],
        ];
    }

    public static function saveChecklistItem(PDO $db,array $actor,int $productionId,array $input):int
    {
        if(!AccessPolicy::canManageProduction($actor))throw new RuntimeException('You cannot manage production checklist items.');
        self::assertSelected($db,$actor,$productionId);
        $id=(int)($input['item_id']??0);$title=trim((string)($input['title']??''));$category=trim((string)($input['category']??'General'));$notes=trim((string)($input['notes']??''));$status=(string)($input['status']??'open');$assignee=filter_var($input['assigned_to_user_id']??null,FILTER_VALIDATE_INT)?:null;$due=self::dateTime((string)($input['due_at']??''));$sort=(int)($input['sort_order']??0);
        if($title===''||mb_strlen($title)>190)throw new RuntimeException('Enter a checklist item title no longer than 190 characters.');if($category===''||mb_strlen($category)>100)throw new RuntimeException('Enter a category no longer than 100 characters.');if(mb_strlen($notes)>1000)throw new RuntimeException('Keep checklist notes under 1,000 characters.');if(!in_array($status,['open','in_progress','blocked','done'],true))throw new RuntimeException('Choose a valid checklist status.');
        if($assignee&&!self::staffMember($db,$productionId,(int)$assignee))throw new RuntimeException('Assign checklist items only to active production staff.');
        $db->beginTransaction();try{
            if($id){$s=$db->prepare('SELECT * FROM production_checklist_items WHERE id=:id AND production_id=:production FOR UPDATE');$s->execute(['id'=>$id,'production'=>$productionId]);$before=$s->fetch();if(!$before)throw new RuntimeException('That checklist item no longer exists in this production.');$completedAt=$status==='done'?($before['completed_at']?:date('Y-m-d H:i:s')):null;$completedBy=$status==='done'?($before['completed_by_user_id']?:$actor['id']):null;$u=$db->prepare('UPDATE production_checklist_items SET category=:category,title=:title,notes=:notes,due_at=:due,status=:status,assigned_to_user_id=:assignee,sort_order=:sort,completed_by_user_id=:completed_by,completed_at=:completed_at WHERE id=:id');$u->execute(['category'=>$category,'title'=>$title,'notes'=>$notes?:null,'due'=>$due,'status'=>$status,'assignee'=>$assignee,'sort'=>$sort,'completed_by'=>$completedBy,'completed_at'=>$completedAt,'id'=>$id]);$event='production_checklist.updated';
            }else{$completedAt=$status==='done'?date('Y-m-d H:i:s'):null;$completedBy=$status==='done'?(int)$actor['id']:null;$s=$db->prepare('INSERT INTO production_checklist_items (production_id,category,title,notes,due_at,status,assigned_to_user_id,sort_order,created_by_user_id,completed_by_user_id,completed_at) VALUES (:production,:category,:title,:notes,:due,:status,:assignee,:sort,:creator,:completed_by,:completed_at)');$s->execute(['production'=>$productionId,'category'=>$category,'title'=>$title,'notes'=>$notes?:null,'due'=>$due,'status'=>$status,'assignee'=>$assignee,'sort'=>$sort,'creator'=>(int)$actor['id'],'completed_by'=>$completedBy,'completed_at'=>$completedAt]);$id=(int)$db->lastInsertId();$event='production_checklist.created';}
            self::audit($db,(int)$actor['id'],$event,$id,'Saved production readiness checklist item.',['production_id'=>$productionId,'status'=>$status,'category'=>$category,'assigned_to_user_id'=>$assignee]);$db->commit();return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The checklist item could not be saved.');}
    }

    public static function toggleDone(PDO $db,array $actor,int $productionId,int $id):void
    {
        if(!AccessPolicy::canManageProduction($actor))throw new RuntimeException('You cannot manage production checklist items.');self::assertSelected($db,$actor,$productionId);$db->beginTransaction();try{$s=$db->prepare('SELECT id,status FROM production_checklist_items WHERE id=:id AND production_id=:production FOR UPDATE');$s->execute(['id'=>$id,'production'=>$productionId]);$row=$s->fetch();if(!$row)throw new RuntimeException('That checklist item no longer exists.');$done=$row['status']!=='done';$db->prepare('UPDATE production_checklist_items SET status=:status,completed_by_user_id=:actor,completed_at=:completed WHERE id=:id')->execute(['status'=>$done?'done':'open','actor'=>$done?(int)$actor['id']:null,'completed'=>$done?date('Y-m-d H:i:s'):null,'id'=>$id]);self::audit($db,(int)$actor['id'],$done?'production_checklist.completed':'production_checklist.reopened',$id,$done?'Completed production checklist item.':'Reopened production checklist item.',['production_id'=>$productionId]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The checklist item could not be updated.');}
    }

    private static function checklist(PDO $db,int $productionId):array{$s=$db->prepare("SELECT pci.*,CONCAT(a.first_name,' ',a.last_name) assignee FROM production_checklist_items pci LEFT JOIN users a ON a.id=pci.assigned_to_user_id WHERE pci.production_id=:production ORDER BY pci.status='done',pci.sort_order,pci.due_at IS NULL,pci.due_at,pci.category,pci.id");$s->execute(['production'=>$productionId]);return $s->fetchAll();}
    private static function staff(PDO $db,int $productionId):array{$s=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=:production AND pm.audience_type='staff' AND pm.status='active' ORDER BY name");$s->execute(['production'=>$productionId]);return $s->fetchAll();}
    private static function staffMember(PDO $db,int $productionId,int $userId):bool{$s=$db->prepare("SELECT 1 FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=:production AND pm.user_id=:user AND pm.audience_type='staff' AND pm.status='active' LIMIT 1");$s->execute(['production'=>$productionId,'user'=>$userId]);return (bool)$s->fetchColumn();}
    private static function playbill(PDO $db,int $productionId):?array{$s=$db->prepare('SELECT id,status,published_at FROM playbills WHERE production_id=:production ORDER BY id DESC LIMIT 1');$s->execute(['production'=>$productionId]);return $s->fetch()?:null;}
    private static function openVolunteerSlots(PDO $db,int $productionId):int{$s=$db->prepare("SELECT COALESCE(SUM(GREATEST(vs.required_slots-COALESCE(x.filled,0),0)),0) FROM volunteer_shifts vs LEFT JOIN (SELECT vss.shift_id,COUNT(*) filled FROM volunteer_shift_signups vss JOIN users u ON u.id=vss.user_id AND u.active=1 AND u.account_status='active' WHERE vss.status IN ('signed_up','checked_in') GROUP BY vss.shift_id) x ON x.shift_id=vs.id WHERE vs.production_id=:production AND vs.starts_at>=NOW()");$s->execute(['production'=>$productionId]);return (int)$s->fetchColumn();}
    private static function count(PDO $db,string $sql,array $params):int{$s=$db->prepare($sql);$s->execute($params);return (int)$s->fetchColumn();}
    private static function dateTime(string $value):?string{$value=trim($value);if($value==='')return null;$ts=strtotime($value);if($ts===false)throw new RuntimeException('Enter a valid due date/time.');return date('Y-m-d H:i:s',$ts);}
    private static function assertSelected(PDO $db,array $actor,int $productionId):void{$selected=ProductionContext::selected($db,$actor);if(!$selected||(int)$selected['id']!==$productionId)throw new RuntimeException('That checklist item is not in your current Working Production.');}
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'production_checklist_item',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
}
