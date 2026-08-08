<?php

declare(strict_types=1);

require_once __DIR__.'/StorageService.php';

final class PlaybillEnhancementService
{
    public static function castProfiles(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT pm.user_id,pm.participation_role,COALESCE(sp.preferred_name,CONCAT(u.first_name,' ',u.last_name)) display_name,sp.short_bio,sp.headshot_stored_file_id FROM production_memberships pm JOIN users u ON u.id=pm.user_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE pm.production_id=:production AND pm.status='active' AND pm.audience_type='student' ORDER BY pm.participation_role,u.last_name,u.first_name");$s->execute(['production'=>$productionId]);return$s->fetchAll();
    }

    public static function sponsors(PDO $db,int $playbillId):array
    {
        $s=$db->prepare("SELECT s.*,ps.placement_label,ps.sort_order FROM playbill_sponsors ps JOIN sponsors s ON s.id=ps.sponsor_id AND s.status='active' WHERE ps.playbill_id=:playbill ORDER BY ps.sort_order,s.name");$s->execute(['playbill'=>$playbillId]);return$s->fetchAll();
    }
    public static function sponsorLibrary(PDO $db):array{return$db->query("SELECT * FROM sponsors WHERE status='active' ORDER BY name")->fetchAll();}

    public static function saveArtwork(PDO $db,string $root,int $playbillId,int $actorId,array $upload):void
    {
        $stored=null;$db->beginTransaction();try{$stored=StorageService::store($db,$root,$actorId,$upload);if(!str_starts_with((string)$stored['mime_type'],'image/'))throw new RuntimeException('Production artwork must be JPG, PNG, or WebP.');$db->prepare('UPDATE playbills SET artwork_stored_file_id=:file WHERE id=:id')->execute(['file'=>(int)$stored['stored_file_id'],'id'=>$playbillId]);self::audit($db,$actorId,'playbill.artwork_updated','playbill',$playbillId,'Updated Playbill production artwork.',[]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($stored&&isset($stored['absolute_path'])&&is_file((string)$stored['absolute_path']))@unlink((string)$stored['absolute_path']);throw $e instanceof RuntimeException?$e:new RuntimeException('The production artwork could not be saved.');}
    }

    public static function createSponsor(PDO $db,string $root,int $actorId,array $input,array $upload):int
    {
        $name=trim((string)($input['sponsor_name']??''));$url=trim((string)($input['website_url']??''));$blurb=trim((string)($input['blurb']??''));if($name===''||mb_strlen($name)>190)throw new RuntimeException('Enter a sponsor name.');if(mb_strlen($url)>1000||mb_strlen($blurb)>500)throw new RuntimeException('Sponsor details are too long.');if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('Enter a valid sponsor website URL.');$stored=null;$db->beginTransaction();try{$logo=null;if((int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$stored=StorageService::store($db,$root,$actorId,$upload);if(!str_starts_with((string)$stored['mime_type'],'image/'))throw new RuntimeException('Sponsor logos must be JPG, PNG, or WebP.');$logo=(int)$stored['stored_file_id'];}$s=$db->prepare("INSERT INTO sponsors (name,website_url,blurb,logo_stored_file_id,status,created_by_user_id) VALUES (:name,:url,:blurb,:logo,'active',:actor)");$s->execute(['name'=>$name,'url'=>$url!==''?$url:null,'blurb'=>$blurb!==''?$blurb:null,'logo'=>$logo,'actor'=>$actorId]);$id=(int)$db->lastInsertId();self::audit($db,$actorId,'playbill.sponsor_created','sponsor',$id,'Created reusable sponsor.',['name'=>$name]);$db->commit();return$id;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($stored&&isset($stored['absolute_path'])&&is_file((string)$stored['absolute_path']))@unlink((string)$stored['absolute_path']);throw $e instanceof RuntimeException?$e:new RuntimeException('The sponsor could not be saved.');}
    }

    public static function attachSponsor(PDO $db,int $playbillId,int $sponsorId,string $placement,int $sortOrder,int $actorId):void
    {
        $s=$db->prepare("SELECT 1 FROM sponsors WHERE id=:id AND status='active'");$s->execute(['id'=>$sponsorId]);if(!$s->fetchColumn())throw new RuntimeException('Choose an active sponsor.');$q=$db->prepare("INSERT INTO playbill_sponsors (playbill_id,sponsor_id,placement_label,sort_order) VALUES (:playbill,:sponsor,:placement,:sort) ON DUPLICATE KEY UPDATE placement_label=VALUES(placement_label),sort_order=VALUES(sort_order)");$q->execute(['playbill'=>$playbillId,'sponsor'=>$sponsorId,'placement'=>trim($placement)!==''?trim($placement):null,'sort'=>$sortOrder]);self::audit($db,$actorId,'playbill.sponsor_attached','playbill',$playbillId,'Added sponsor to Playbill.',['sponsor_id'=>$sponsorId]);
    }

    public static function publicAsset(PDO $db,string $slug,string $kind,int $id):?array
    {
        $p=$db->prepare("SELECT pb.id,pb.production_id,pb.artwork_stored_file_id FROM playbills pb WHERE pb.public_slug=:slug AND pb.status='current' LIMIT 1");$p->execute(['slug'=>$slug]);$playbill=$p->fetch();if(!$playbill)return null;$fileId=null;
        if($kind==='artwork')$fileId=$playbill['artwork_stored_file_id']?(int)$playbill['artwork_stored_file_id']:null;
        elseif($kind==='headshot'){$s=$db->prepare("SELECT sp.headshot_stored_file_id FROM production_memberships pm JOIN student_profiles sp ON sp.user_id=pm.user_id WHERE pm.production_id=:production AND pm.user_id=:user AND pm.audience_type='student' AND pm.status='active' LIMIT 1");$s->execute(['production'=>(int)$playbill['production_id'],'user'=>$id]);$fileId=(int)($s->fetchColumn()?:0)?:null;}
        elseif($kind==='sponsor'){$s=$db->prepare("SELECT s.logo_stored_file_id FROM playbill_sponsors ps JOIN sponsors s ON s.id=ps.sponsor_id AND s.status='active' WHERE ps.playbill_id=:playbill AND s.id=:sponsor LIMIT 1");$s->execute(['playbill'=>(int)$playbill['id'],'sponsor'=>$id]);$fileId=(int)($s->fetchColumn()?:0)?:null;}
        return$fileId?StorageService::currentVersion($db,$fileId):null;
    }

    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $metadata):void{$s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');$s->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);}
}
