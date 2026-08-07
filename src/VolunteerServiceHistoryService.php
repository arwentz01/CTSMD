<?php

declare(strict_types=1);

final class VolunteerServiceHistoryService
{
    public static function record(PDO $db,int $userId):array
    {
        $person=self::person($db,$userId);
        if(!$person)throw new RuntimeException('Volunteer account not found.');

        $hours=self::hours($db,$userId);
        $totalMinutes=0;$productionMap=[];$years=[];$categories=[];
        foreach($hours as $row){
            $minutes=(int)$row['minutes'];$totalMinutes+=$minutes;
            $year=date('Y',strtotime((string)$row['served_at']));$years[$year]=($years[$year]??0)+$minutes;
            $category=trim((string)($row['shift_category']??''));if($category!=='')$categories[$category]=($categories[$category]??0)+$minutes;
            $key=$row['production_id']!==null?'production:'.(int)$row['production_id']:'organization';
            if(!isset($productionMap[$key]))$productionMap[$key]=[
                'production_id'=>$row['production_id']!==null?(int)$row['production_id']:null,
                'title'=>$row['production_title']?:'CTSMD Organization Service',
                'season'=>$row['production_season']?:null,
                'production_status'=>$row['production_status']?:null,
                'is_active'=>(bool)($row['production_active']??false),
                'minutes'=>0,'entries'=>0,'first_served_at'=>$row['served_at'],'last_served_at'=>$row['served_at'],'categories'=>[],
            ];
            $productionMap[$key]['minutes']+=$minutes;$productionMap[$key]['entries']++;
            if(strtotime((string)$row['served_at'])<strtotime((string)$productionMap[$key]['first_served_at']))$productionMap[$key]['first_served_at']=$row['served_at'];
            if(strtotime((string)$row['served_at'])>strtotime((string)$productionMap[$key]['last_served_at']))$productionMap[$key]['last_served_at']=$row['served_at'];
            if($category!=='')$productionMap[$key]['categories'][$category]=($productionMap[$key]['categories'][$category]??0)+$minutes;
        }
        foreach($productionMap as &$production){
            arsort($production['categories']);
            $production['category_labels']=array_keys($production['categories']);
        }unset($production);
        usort($productionMap,static fn(array $a,array $b):int=>strtotime((string)$b['last_served_at'])<=>strtotime((string)$a['last_served_at']));
        krsort($years);arsort($categories);

        $training=self::training($db,$userId);
        $credentials=self::credentials($db,$userId);
        $completedTraining=count(array_filter($training,static fn(array $r):bool=>$r['status']==='completed'));
        $approvedCredentials=count(array_filter($credentials,static fn(array $r):bool=>$r['effective_status']==='approved'));

        return [
            'person'=>$person,
            'total_minutes'=>$totalMinutes,
            'production_count'=>count(array_filter($productionMap,static fn(array $p):bool=>$p['production_id']!==null)),
            'service_entries'=>count($hours),
            'completed_training_count'=>$completedTraining,
            'approved_credential_count'=>$approvedCredentials,
            'productions'=>array_values($productionMap),
            'years'=>$years,
            'categories'=>$categories,
            'hours'=>$hours,
            'training'=>$training,
            'credentials'=>$credentials,
        ];
    }

    private static function person(PDO $db,int $userId):?array
    {
        $s=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email,u.display_role,u.active,COALESCE(vp.active,0) volunteer_active FROM users u LEFT JOIN volunteer_profiles vp ON vp.user_id=u.id WHERE u.id=:user LIMIT 1");
        $s->execute(['user'=>$userId]);$row=$s->fetch();return$row?:null;
    }

    private static function hours(PDO $db,int $userId):array
    {
        $s=$db->prepare("SELECT h.id,h.production_id,h.shift_id,h.minutes,h.source_type,h.note,h.served_at,h.verified_by_user_id,vs.title shift_title,vs.category shift_category,vs.location shift_location,p.title production_title,p.season production_season,p.status production_status,COALESCE(p.is_active,0) production_active,CONCAT(v.first_name,' ',v.last_name) verifier_name FROM volunteer_hour_entries h LEFT JOIN volunteer_shifts vs ON vs.id=h.shift_id LEFT JOIN productions p ON p.id=h.production_id LEFT JOIN users v ON v.id=h.verified_by_user_id WHERE h.user_id=:user AND h.status='verified' ORDER BY h.served_at DESC,h.id DESC");
        $s->execute(['user'=>$userId]);return$s->fetchAll();
    }

    private static function training(PDO $db,int $userId):array
    {
        $s=$db->prepare("SELECT m.id,m.title,m.description,m.validity_days,vr.name requirement_name,c.status,c.completed_at,CONCAT(v.first_name,' ',v.last_name) verifier_name FROM volunteer_training_modules m JOIN volunteer_training_completions c ON c.module_id=m.id AND c.user_id=:user AND c.status='completed' LEFT JOIN volunteer_requirements vr ON vr.id=m.requirement_id LEFT JOIN users v ON v.id=c.verified_by_user_id ORDER BY c.completed_at DESC,m.title");
        $s->execute(['user'=>$userId]);return$s->fetchAll();
    }

    private static function credentials(PDO $db,int $userId):array
    {
        $s=$db->prepare("SELECT vc.id,vr.code,vr.name,vr.category,vc.status,vc.completed_at,vc.expires_at,CONCAT(v.first_name,' ',v.last_name) verifier_name FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id=vc.requirement_id LEFT JOIN users v ON v.id=vc.verified_by_user_id WHERE vc.user_id=:user ORDER BY FIELD(vc.status,'approved','review','pending','expired','missing'),vr.name");
        $s->execute(['user'=>$userId]);$rows=$s->fetchAll();
        foreach($rows as &$row){$effective=(string)$row['status'];if($effective==='approved'&&!empty($row['expires_at'])&&strtotime((string)$row['expires_at'])<time())$effective='expired';$row['effective_status']=$effective;}unset($row);
        return$rows;
    }
}
