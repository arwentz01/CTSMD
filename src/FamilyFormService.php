<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';

final class FamilyFormService
{
    public static function assignmentsForViewer(PDO $db,int $viewerId):array
    {
        $s=$db->prepare("SELECT fa.id,fa.form_id,fa.user_id,COALESCE(fa.subject_user_id,fa.user_id) subject_user_id,fa.production_id,fa.source_group_id,fa.status,fa.due_at,fa.completed_at,f.title,f.form_type,f.completion_mode,f.review_required,f.definition_version,p.title production_title,CONCAT(subject.first_name,' ',subject.last_name) subject_name,fs.id submission_id,fs.status submission_status,fs.submitted_by_user_id,CONCAT(submitter.first_name,' ',submitter.last_name) submitted_by_name
            FROM form_assignments fa
            JOIN forms f ON f.id=fa.form_id AND f.active=1
            JOIN users subject ON subject.id=COALESCE(fa.subject_user_id,fa.user_id)
            LEFT JOIN productions p ON p.id=fa.production_id
            LEFT JOIN form_submissions fs ON fs.assignment_id=fa.id
            LEFT JOIN users submitter ON submitter.id=fs.submitted_by_user_id
            WHERE (subject.account_status<>'disabled' OR fa.status='completed') AND (
                fa.user_id=:viewer
                OR EXISTS (SELECT 1 FROM family_relationships fr WHERE fr.guardian_user_id=:guardian AND fr.student_user_id=COALESCE(fa.subject_user_id,fa.user_id) AND fr.status='active')
            )
            ORDER BY CASE fa.status WHEN 'missing' THEN 1 WHEN 'due_soon' THEN 2 WHEN 'requires_review' THEN 3 ELSE 4 END,fa.due_at IS NULL,fa.due_at,subject.last_name,subject.first_name,fa.id");
        $s->execute(['viewer'=>$viewerId,'guardian'=>$viewerId]);return$s->fetchAll();
    }

    public static function assignmentForViewer(PDO $db,int $viewerId,int $assignmentId):?array
    {
        if($assignmentId<1)return null;
        $s=$db->prepare("SELECT fa.id,fa.user_id,COALESCE(fa.subject_user_id,fa.user_id) subject_user_id,fa.status,fa.due_at,fa.completed_at,fa.production_id,fa.source_group_id,f.id form_id,f.title,f.form_type,f.instructions,f.completion_mode,f.review_required,f.definition_version,p.title production_title,CONCAT(subject.first_name,' ',subject.last_name) subject_name,fs.id submission_id,fs.status submission_status,fs.submitted_by_user_id,fs.typed_signature,fs.response_text,fs.reviewer_note,fs.submitted_at,fs.reviewed_at,CONCAT(submitter.first_name,' ',submitter.last_name) submitted_by_name
            FROM form_assignments fa
            JOIN forms f ON f.id=fa.form_id AND f.active=1
            JOIN users subject ON subject.id=COALESCE(fa.subject_user_id,fa.user_id)
            LEFT JOIN productions p ON p.id=fa.production_id
            LEFT JOIN form_submissions fs ON fs.assignment_id=fa.id
            LEFT JOIN users submitter ON submitter.id=fs.submitted_by_user_id
            WHERE fa.id=:id AND (subject.account_status<>'disabled' OR fa.status='completed') AND (
                fa.user_id=:viewer
                OR EXISTS (SELECT 1 FROM family_relationships fr WHERE fr.guardian_user_id=:guardian AND fr.student_user_id=COALESCE(fa.subject_user_id,fa.user_id) AND fr.status='active')
            ) LIMIT 1");
        $s->execute(['id'=>$assignmentId,'viewer'=>$viewerId,'guardian'=>$viewerId]);return$s->fetch()?:null;
    }

    public static function assignGroup(PDO $db,array $actor,int $formId,int $groupId,?string $dueDate):int
    {
        if(!AccessPolicy::canManageForms($actor))throw new RuntimeException('Forms management permission is required.');
        $f=$db->prepare("SELECT f.id,f.production_id,f.title,pg.production_id group_production,pg.active group_active FROM forms f JOIN production_groups pg ON pg.id=:group WHERE f.id=:form AND f.active=1 LIMIT 1");$f->execute(['group'=>$groupId,'form'=>$formId]);$context=$f->fetch();
        if(!$context)throw new RuntimeException('Choose an active form and Production Group.');
        if(!(bool)$context['group_active'])throw new RuntimeException('That Production Group is inactive.');
        if(!$context['production_id']||(int)$context['production_id']!==(int)$context['group_production'])throw new RuntimeException('The form and Production Group must belong to the same production.');
        $due=null;if($dueDate!==null&&trim($dueDate)!==''){$d=DateTimeImmutable::createFromFormat('Y-m-d',trim($dueDate));if(!$d||$d->format('Y-m-d')!==trim($dueDate))throw new RuntimeException('Choose a valid due date.');$due=$d->format('Y-m-d 23:59:59');}
        $members=$db->prepare("SELECT DISTINCT pm.user_id FROM production_group_members pgm JOIN production_memberships pm ON pm.id=pgm.production_membership_id JOIN users student ON student.id=pm.user_id AND student.active=1 AND student.account_status<>'disabled' WHERE pgm.group_id=:group AND pgm.status='active' AND pm.status='active' AND pm.audience_type='student' ORDER BY pm.user_id");
        $members->execute(['group'=>$groupId]);$userIds=array_map(static fn(array $r):int=>(int)$r['user_id'],$members->fetchAll());if(!$userIds)throw new RuntimeException('That Production Group has no live Student members.');
        $db->beginTransaction();$count=0;
        try{
            foreach($userIds as $studentId){
                $existing=$db->prepare("SELECT id,status FROM form_assignments WHERE form_id=:form AND user_id=:user AND production_id=:production ORDER BY id DESC LIMIT 1 FOR UPDATE");$existing->execute(['form'=>$formId,'user'=>$studentId,'production'=>(int)$context['production_id']]);$row=$existing->fetch();
                if($row){$u=$db->prepare("UPDATE form_assignments SET subject_user_id=:subject,source_group_id=:group,due_at=COALESCE(:due,due_at),assigned_by_user_id=:actor WHERE id=:id");$u->execute(['subject'=>$studentId,'group'=>$groupId,'due'=>$due,'actor'=>(int)$actor['id'],'id'=>(int)$row['id']]);}
                else{$i=$db->prepare("INSERT INTO form_assignments (form_id,user_id,subject_user_id,production_id,source_group_id,status,due_at,assigned_by_user_id) VALUES (:form,:user,:subject,:production,:group,'missing',:due,:actor)");$i->execute(['form'=>$formId,'user'=>$studentId,'subject'=>$studentId,'production'=>(int)$context['production_id'],'group'=>$groupId,'due'=>$due,'actor'=>(int)$actor['id']]);}
                $count++;
            }
            self::audit($db,(int)$actor['id'],'form.group_assigned','production_group',$groupId,'Assigned production form to Production Group.',['form_id'=>$formId,'production_id'=>(int)$context['production_id'],'student_count'=>$count,'due_at'=>$due]);$db->commit();return$count;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e instanceof RuntimeException?$e:new RuntimeException('The Production Group assignments could not be created.');}
    }

    public static function groupAssignmentOptions(PDO $db):array
    {
        return $db->query("SELECT pg.id group_id,pg.name group_name,pg.production_id,f.id form_id,f.title form_title,p.title production_title,p.season FROM production_groups pg JOIN productions p ON p.id=pg.production_id AND p.is_active=1 JOIN forms f ON f.production_id=pg.production_id AND f.active=1 WHERE pg.active=1 ORDER BY p.title,f.title,pg.name")->fetchAll();
    }

    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta):void{$s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');$s->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
}
