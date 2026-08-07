<?php

declare(strict_types=1);

final class TheatreHistoryService
{
    public static function syncStudentCredit(PDO $db,int $productionId,int $userId,?int $verifiedByUserId=null):void
    {
        $s=$db->prepare("SELECT pm.id membership_id,pm.participation_role,pm.created_at,p.title,p.season,cr.role_title casting_role,cr.participation_track
            FROM production_memberships pm
            JOIN productions p ON p.id=pm.production_id
            LEFT JOIN production_casting_records cr ON cr.production_id=pm.production_id AND cr.user_id=pm.user_id
            WHERE pm.production_id=:production AND pm.user_id=:user AND pm.audience_type='student' LIMIT 1");
        $s->execute(['production'=>$productionId,'user'=>$userId]);$row=$s->fetch();
        if(!$row)return;
        $g=$db->prepare("SELECT pg.name FROM production_group_members pgm JOIN production_groups pg ON pg.id=pgm.group_id WHERE pgm.production_membership_id=:membership AND pgm.status='active' AND pg.active=1 ORDER BY pg.sort_order,pg.name");
        $g->execute(['membership'=>(int)$row['membership_id']]);$groups=array_map(static fn(array $r):string=>(string)$r['name'],$g->fetchAll());
        $role=trim((string)($row['casting_role']??''));if($role==='')$role=trim((string)($row['participation_role']??''));
        $track=trim((string)($row['participation_track']??''));
        $stmt=$db->prepare("INSERT INTO theatre_history_credits (user_id,production_id,source_membership_id,credit_kind,production_title,season_label,role_title,participation_track,groups_snapshot,verification_status,verified_by_user_id,verified_at,participation_started_at,participation_ended_at)
            VALUES (:user,:production,:membership,'performance',:title,:season,:role,:track,:groups,'verified',:verifier,CURRENT_TIMESTAMP,:started,NULL)
            ON DUPLICATE KEY UPDATE source_membership_id=VALUES(source_membership_id),production_title=VALUES(production_title),season_label=VALUES(season_label),role_title=VALUES(role_title),participation_track=VALUES(participation_track),groups_snapshot=VALUES(groups_snapshot),verification_status='verified',verified_by_user_id=COALESCE(VALUES(verified_by_user_id),verified_by_user_id),verified_at=CURRENT_TIMESTAMP,participation_ended_at=NULL,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute(['user'=>$userId,'production'=>$productionId,'membership'=>(int)$row['membership_id'],'title'=>$row['title'],'season'=>$row['season']?:null,'role'=>$role!==''?$role:null,'track'=>$track!==''?$track:null,'groups'=>$groups?implode('||',$groups):null,'verifier'=>$verifiedByUserId,'started'=>$row['created_at']]);
    }

    public static function closeStudentCredit(PDO $db,int $productionId,int $userId):void
    {
        $s=$db->prepare("UPDATE theatre_history_credits SET participation_ended_at=COALESCE(participation_ended_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE production_id=:production AND user_id=:user AND credit_kind='performance' AND verification_status='verified'");
        $s->execute(['production'=>$productionId,'user'=>$userId]);
    }

    public static function subjectsForViewer(PDO $db,array $viewer):array
    {
        $viewerId=(int)$viewer['id'];$subjects=[];
        if(self::isStudent($db,$viewerId))$subjects[$viewerId]=['id'=>$viewerId,'name'=>(string)$viewer['name'],'relationship'=>'Self'];
        $s=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,fr.relationship_type FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id WHERE fr.guardian_user_id=:guardian AND fr.status='active' ORDER BY u.last_name,u.first_name");
        $s->execute(['guardian'=>$viewerId]);foreach($s->fetchAll() as $row)$subjects[(int)$row['id']]=['id'=>(int)$row['id'],'name'=>(string)$row['name'],'relationship'=>ucfirst((string)$row['relationship_type'])];
        return array_values($subjects);
    }

    public static function creditsForSubject(PDO $db,int $subjectId):array
    {
        $s=$db->prepare("SELECT thc.*,p.is_active,p.status production_status FROM theatre_history_credits thc LEFT JOIN productions p ON p.id=thc.production_id WHERE thc.user_id=:user AND thc.verification_status='verified' ORDER BY COALESCE(thc.season_label,'') DESC,thc.verified_at DESC,thc.production_title");
        $s->execute(['user'=>$subjectId]);$rows=$s->fetchAll();
        foreach($rows as &$row){$snapshot=trim((string)($row['groups_snapshot']??''));$row['groups']=$snapshot===''?[]:array_values(array_filter(explode('||',$snapshot)));}$row=null;
        return $rows;
    }

    public static function canViewerSeeSubject(PDO $db,int $viewerId,int $subjectId):bool
    {
        if($viewerId===$subjectId&&self::isStudent($db,$viewerId))return true;
        $s=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:viewer AND student_user_id=:student AND status='active' LIMIT 1");$s->execute(['viewer'=>$viewerId,'student'=>$subjectId]);return(bool)$s->fetchColumn();
    }

    private static function isStudent(PDO $db,int $userId):bool
    {
        $s=$db->prepare("SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=:user AND r.code='student' LIMIT 1");$s->execute(['user'=>$userId]);return(bool)$s->fetchColumn();
    }
}
