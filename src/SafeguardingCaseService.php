<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';

final class SafeguardingCaseService
{
    private const STATUSES=['open','in_review','action_required','monitoring','resolved','closed'];
    private const PRIORITIES=['low','medium','high','urgent'];

    public static function queue(PDO $db):array
    {
        return $db->query("SELECT sc.*,p.title production_title,CONCAT(subject.first_name,' ',subject.last_name) subject_name,CONCAT(reporter.first_name,' ',reporter.last_name) reporter_name,CONCAT(assignee.first_name,' ',assignee.last_name) assignee_name FROM safeguarding_cases sc LEFT JOIN productions p ON p.id=sc.production_id LEFT JOIN users subject ON subject.id=sc.subject_user_id JOIN users reporter ON reporter.id=sc.reported_by_user_id LEFT JOIN users assignee ON assignee.id=sc.assigned_to_user_id ORDER BY FIELD(sc.status,'action_required','open','in_review','monitoring','resolved','closed'),FIELD(sc.priority,'urgent','high','medium','low'),sc.updated_at DESC,sc.id DESC")->fetchAll();
    }

    public static function openCount(PDO $db):int
    {
        return (int)$db->query("SELECT COUNT(*) FROM safeguarding_cases WHERE status IN ('open','in_review','action_required','monitoring')")->fetchColumn();
    }

    public static function caseById(PDO $db,int $id):?array
    {
        if($id<1)return null;
        $s=$db->prepare("SELECT sc.*,p.title production_title,CONCAT(subject.first_name,' ',subject.last_name) subject_name,CONCAT(reporter.first_name,' ',reporter.last_name) reporter_name,CONCAT(assignee.first_name,' ',assignee.last_name) assignee_name FROM safeguarding_cases sc LEFT JOIN productions p ON p.id=sc.production_id LEFT JOIN users subject ON subject.id=sc.subject_user_id JOIN users reporter ON reporter.id=sc.reported_by_user_id LEFT JOIN users assignee ON assignee.id=sc.assigned_to_user_id WHERE sc.id=:id LIMIT 1");
        $s->execute(['id'=>$id]);$case=$s->fetch();if(!$case)return null;
        $e=$db->prepare("SELECT sce.*,CONCAT(u.first_name,' ',u.last_name) actor_name,u.initials FROM safeguarding_case_events sce JOIN users u ON u.id=sce.created_by_user_id WHERE sce.case_id=:case ORDER BY sce.created_at,sce.id");
        $e->execute(['case'=>$id]);$case['events']=$e->fetchAll();return$case;
    }

    public static function create(PDO $db,array $actor,array $input):int
    {
        self::assertAccess($actor);
        $title=trim((string)($input['title']??''));$category=trim((string)($input['category']??''));$summary=trim((string)($input['summary']??''));
        $priority=(string)($input['priority']??'medium');$productionId=self::nullableInt($input['production_id']??null);$subjectId=self::nullableInt($input['subject_user_id']??null);$assigneeId=self::nullableInt($input['assigned_to_user_id']??null);$occurred=self::nullableDateTime($input['occurred_at']??null);
        if($title===''||mb_strlen($title)>190)throw new RuntimeException('Enter a case title up to 190 characters.');
        if($category===''||mb_strlen($category)>100)throw new RuntimeException('Enter a safeguarding category.');
        if($summary===''||mb_strlen($summary)>10000)throw new RuntimeException('Enter a concise factual summary.');
        if(!in_array($priority,self::PRIORITIES,true))throw new RuntimeException('Choose a valid priority.');
        if($assigneeId!==null&&!self::isSafeguardingUser($db,$assigneeId))throw new RuntimeException('Assign the case only to a user with safeguarding authority.');
        $db->beginTransaction();
        try{
            $s=$db->prepare("INSERT INTO safeguarding_cases (title,category,priority,status,summary,production_id,subject_user_id,reported_by_user_id,assigned_to_user_id,occurred_at) VALUES (:title,:category,:priority,'open',:summary,:production,:subject,:reporter,:assignee,:occurred)");
            $s->execute(['title'=>$title,'category'=>$category,'priority'=>$priority,'summary'=>$summary,'production'=>$productionId,'subject'=>$subjectId,'reporter'=>(int)$actor['id'],'assignee'=>$assigneeId,'occurred'=>$occurred]);
            $id=(int)$db->lastInsertId();$code='SAFE-'.date('Y').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);$u=$db->prepare('UPDATE safeguarding_cases SET case_code=:code WHERE id=:id');$u->execute(['code'=>$code,'id'=>$id]);
            self::event($db,$id,(int)$actor['id'],'created','Safeguarding case created.',null,'open');self::audit($db,(int)$actor['id'],'safeguarding.case_created',$id,'Created restricted safeguarding case.',['case_code'=>$code,'priority'=>$priority,'category'=>$category]);$db->commit();return$id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e instanceof RuntimeException?$e:new RuntimeException('The safeguarding case could not be created.');}
    }

    public static function update(PDO $db,array $actor,int $id,array $input):void
    {
        self::assertAccess($actor);$case=self::caseById($db,$id);if(!$case)throw new RuntimeException('That safeguarding case could not be found.');
        $status=(string)($input['status']??$case['status']);$priority=(string)($input['priority']??$case['priority']);$assignee=self::nullableInt($input['assigned_to_user_id']??null);$note=trim((string)($input['note']??''));
        if(!in_array($status,self::STATUSES,true)||!in_array($priority,self::PRIORITIES,true))throw new RuntimeException('Choose valid case status and priority values.');
        if($assignee!==null&&!self::isSafeguardingUser($db,$assignee))throw new RuntimeException('Assign the case only to a user with safeguarding authority.');
        if($note===''&&$status===$case['status']&&$priority===$case['priority']&&$assignee===(($case['assigned_to_user_id']!==null)?(int)$case['assigned_to_user_id']:null))throw new RuntimeException('Add a note or change the case state.');
        $db->beginTransaction();
        try{
            $resolvedAt=in_array($status,['resolved','closed'],true)?'CURRENT_TIMESTAMP':'NULL';$closedAt=$status==='closed'?'CURRENT_TIMESTAMP':'NULL';
            $sql="UPDATE safeguarding_cases SET status=:status,priority=:priority,assigned_to_user_id=:assignee,resolved_at=".$resolvedAt.",closed_at=".$closedAt." WHERE id=:id";$s=$db->prepare($sql);$s->execute(['status'=>$status,'priority'=>$priority,'assignee'=>$assignee,'id'=>$id]);
            if($status!==$case['status'])self::event($db,$id,(int)$actor['id'],$status==='resolved'||$status==='closed'?'resolution':'status_change',$note!==''?$note:'Status updated.',(string)$case['status'],$status);
            elseif($priority!==$case['priority'])self::event($db,$id,(int)$actor['id'],'priority_change',$note!==''?$note:'Priority updated.',null,null);
            elseif($assignee!==($case['assigned_to_user_id']!==null?(int)$case['assigned_to_user_id']:null))self::event($db,$id,(int)$actor['id'],'assignment',$note!==''?$note:'Case assignment updated.',null,null);
            elseif($note!=='')self::event($db,$id,(int)$actor['id'],'note',$note,null,null);
            self::audit($db,(int)$actor['id'],'safeguarding.case_updated',$id,'Updated restricted safeguarding case.',['from_status'=>$case['status'],'to_status'=>$status,'from_priority'=>$case['priority'],'to_priority'=>$priority]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e instanceof RuntimeException?$e:new RuntimeException('The safeguarding case could not be updated.');}
    }

    public static function safeguardingUsers(PDO $db):array
    {
        return $db->query("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name FROM users u JOIN auth_user_roles aur ON aur.user_id=u.id JOIN auth_roles ar ON ar.id=aur.role_id LEFT JOIN auth_role_permissions arp ON arp.role_id=ar.id LEFT JOIN auth_permissions ap ON ap.id=arp.permission_id WHERE u.active=1 AND (ar.code IN ('administrator','safeguarding') OR ap.code='safeguarding.manage') ORDER BY u.last_name,u.first_name")->fetchAll();
    }

    public static function people(PDO $db):array{return $db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role FROM users WHERE active=1 ORDER BY last_name,first_name")->fetchAll();}
    public static function productions(PDO $db):array{return $db->query("SELECT id,title,season,is_active,status FROM productions ORDER BY is_active DESC,id DESC")->fetchAll();}

    private static function assertAccess(array $actor):void{if(!AccessPolicy::canManageSafeguarding($actor))throw new RuntimeException('Safeguarding authority is required.');}
    private static function nullableInt(mixed $value):?int{$v=filter_var($value,FILTER_VALIDATE_INT);return$v&&$v>0?(int)$v:null;}
    private static function nullableDateTime(mixed $value):?string{$v=trim((string)$value);if($v==='')return null;$d=DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$v);if(!$d)throw new RuntimeException('Enter a valid incident date/time.');return$d->format('Y-m-d H:i:s');}
    private static function isSafeguardingUser(PDO $db,int $userId):bool{$s=$db->prepare("SELECT 1 FROM users u JOIN auth_user_roles aur ON aur.user_id=u.id JOIN auth_roles ar ON ar.id=aur.role_id LEFT JOIN auth_role_permissions arp ON arp.role_id=ar.id LEFT JOIN auth_permissions ap ON ap.id=arp.permission_id WHERE u.id=:user AND u.active=1 AND (ar.code IN ('administrator','safeguarding') OR ap.code='safeguarding.manage') LIMIT 1");$s->execute(['user'=>$userId]);return(bool)$s->fetchColumn();}
    private static function event(PDO $db,int $caseId,int $actorId,string $type,string $note,?string $from,?string $to):void{$s=$db->prepare('INSERT INTO safeguarding_case_events (case_id,event_type,note,status_from,status_to,created_by_user_id) VALUES (:case,:type,:note,:from_status,:to_status,:actor)');$s->execute(['case'=>$caseId,'type'=>$type,'note'=>$note,'from_status'=>$from,'to_status'=>$to,'actor'=>$actorId]);}
    private static function audit(PDO $db,int $actorId,string $event,int $caseId,string $summary,array $metadata):void{$s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,\'safeguarding_case\',:subject,:summary,:metadata)');$s->execute(['actor'=>$actorId,'event'=>$event,'subject'=>$caseId,'summary'=>$summary,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);}
}
