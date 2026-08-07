<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/StorageService.php';

final class ProductionArchiveService
{
    public static function productionsForViewer(PDO $db, array $viewer): array
    {
        $viewerId = (int)$viewer['id'];
        if (AccessPolicy::canManageProduction($viewer)) {
            $stmt = $db->query("SELECT p.id,p.title,p.season,p.status,p.deactivated_at,p.created_at,
                (SELECT COUNT(*) FROM production_memberships pm WHERE pm.production_id=p.id AND pm.audience_type='student') student_count,
                (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_count
                FROM productions p
                WHERE p.is_active=0 AND p.status='archived'
                ORDER BY COALESCE(p.deactivated_at,p.created_at) DESC,p.title");
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare("SELECT DISTINCT p.id,p.title,p.season,p.status,p.deactivated_at,p.created_at,
            (SELECT COUNT(*) FROM production_memberships pm2 WHERE pm2.production_id=p.id AND pm2.audience_type='student') student_count,
            (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_count
            FROM productions p
            WHERE p.is_active=0 AND p.status='archived'
              AND (
                EXISTS (SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id AND pm.user_id=:viewer)
                OR EXISTS (
                    SELECT 1 FROM family_relationships fr
                    JOIN production_memberships child_pm ON child_pm.user_id=fr.student_user_id AND child_pm.production_id=p.id AND child_pm.audience_type='student'
                    WHERE fr.guardian_user_id=:viewer2 AND fr.status='active'
                )
              )
            ORDER BY COALESCE(p.deactivated_at,p.created_at) DESC,p.title");
        $stmt->execute(['viewer'=>$viewerId,'viewer2'=>$viewerId]);
        return $stmt->fetchAll();
    }

    public static function detail(PDO $db, array $viewer, int $productionId): ?array
    {
        if (!self::canViewProduction($db,$viewer,$productionId)) return null;
        $p = $db->prepare("SELECT id,title,season,status,is_active,activated_at,deactivated_at,created_at FROM productions WHERE id=:id AND is_active=0 AND status='archived' LIMIT 1");
        $p->execute(['id'=>$productionId]);
        $production = $p->fetch();
        if (!$production) return null;

        $audiences = self::historicalAudiences($db,(int)$viewer['id'],$productionId);
        $manager = AccessPolicy::canManageProduction($viewer);
        return [
            'production'=>$production,
            'audiences'=>$audiences,
            'roster'=>self::roster($db,$productionId),
            'cast'=>self::publishedCast($db,$productionId),
            'schedule'=>self::schedule($db,$productionId),
            'playbill'=>self::playbill($db,$productionId),
            'resources'=>self::resources($db,$productionId,$viewer,$audiences,$manager),
            'files'=>self::files($db,$productionId,$viewer,$audiences,$manager),
            'channels'=>self::channels($db,$productionId,$viewer,$audiences,$manager),
            'notices'=>self::notices($db,$productionId),
        ];
    }

    public static function channelDetail(PDO $db,array $viewer,int $productionId,int $channelId):?array
    {
        if(!self::canViewProduction($db,$viewer,$productionId))return null;
        $s=$db->prepare("SELECT c.id,c.production_id,c.name,c.description,c.access_mode,c.read_audiences_json,p.title production_title,p.season FROM channels c JOIN productions p ON p.id=c.production_id WHERE c.id=:channel AND c.production_id=:production AND c.archived_at IS NULL AND p.is_active=0 AND p.status='archived' LIMIT 1");
        $s->execute(['channel'=>$channelId,'production'=>$productionId]);$channel=$s->fetch();if(!$channel)return null;
        $audiences=self::historicalAudiences($db,(int)$viewer['id'],$productionId);
        if(!AccessPolicy::canManageProduction($viewer)&&!self::historicalChannelAccess($db,$channel,$viewer,$audiences))return null;
        $posts=$db->prepare("SELECT cp.id,cp.body,cp.created_at,CONCAT(u.first_name,' ',u.last_name) author_name FROM channel_posts cp JOIN users u ON u.id=cp.author_user_id WHERE cp.channel_id=:channel AND cp.moderation_status='published' ORDER BY cp.created_at,cp.id");
        $posts->execute(['channel'=>$channelId]);$channel['posts']=$posts->fetchAll();return$channel;
    }

    public static function canViewProduction(PDO $db, array $viewer, int $productionId): bool
    {
        if ($productionId < 1) return false;
        $p = $db->prepare("SELECT 1 FROM productions WHERE id=:id AND is_active=0 AND status='archived' LIMIT 1");
        $p->execute(['id'=>$productionId]);
        if (!$p->fetchColumn()) return false;
        if (AccessPolicy::canManageProduction($viewer)) return true;

        $viewerId = (int)$viewer['id'];
        $s = $db->prepare("SELECT 1 FROM production_memberships WHERE production_id=:production AND user_id=:viewer LIMIT 1");
        $s->execute(['production'=>$productionId,'viewer'=>$viewerId]);
        if ($s->fetchColumn()) return true;

        $g = $db->prepare("SELECT 1 FROM family_relationships fr JOIN production_memberships pm ON pm.user_id=fr.student_user_id AND pm.production_id=:production AND pm.audience_type='student' WHERE fr.guardian_user_id=:viewer AND fr.status='active' LIMIT 1");
        $g->execute(['production'=>$productionId,'viewer'=>$viewerId]);
        return (bool)$g->fetchColumn();
    }

    public static function archiveFile(PDO $db, array $viewer, int $productionFileId): ?array
    {
        $s = $db->prepare("SELECT pf.*,p.is_active,p.status production_status FROM production_files pf JOIN productions p ON p.id=pf.production_id WHERE pf.id=:id AND pf.status='active' LIMIT 1");
        $s->execute(['id'=>$productionFileId]);
        $file = $s->fetch();
        if (!$file || (bool)$file['is_active'] || $file['production_status'] !== 'archived') return null;
        $productionId = (int)$file['production_id'];
        if (!self::canViewProduction($db,$viewer,$productionId)) return null;
        $audiences = self::historicalAudiences($db,(int)$viewer['id'],$productionId);
        if (!AccessPolicy::canManageProduction($viewer) && !self::audienceAllows((string)$file['audiences_json'],$viewer,$audiences)) return null;
        $version = StorageService::currentVersion($db,(int)$file['stored_file_id']);
        return $version ? ['file'=>$file,'version'=>$version] : null;
    }

    private static function historicalAudiences(PDO $db, int $viewerId, int $productionId): array
    {
        $s = $db->prepare("SELECT DISTINCT audience_type FROM production_memberships WHERE production_id=:production AND user_id=:viewer");
        $s->execute(['production'=>$productionId,'viewer'=>$viewerId]);
        $types = array_map(static fn(array $r): string => (string)$r['audience_type'],$s->fetchAll());
        if (!in_array('guardian',$types,true)) {
            $g = $db->prepare("SELECT 1 FROM family_relationships fr JOIN production_memberships pm ON pm.user_id=fr.student_user_id AND pm.production_id=:production AND pm.audience_type='student' WHERE fr.guardian_user_id=:viewer AND fr.status='active' LIMIT 1");
            $g->execute(['production'=>$productionId,'viewer'=>$viewerId]);
            if ($g->fetchColumn()) $types[]='guardian';
        }
        return array_values(array_unique($types));
    }

    private static function roster(PDO $db, int $productionId): array
    {
        $s = $db->prepare("SELECT pm.user_id,pm.audience_type,pm.participation_role,pm.status,CONCAT(u.first_name,' ',u.last_name) name FROM production_memberships pm JOIN users u ON u.id=pm.user_id WHERE pm.production_id=:production ORDER BY FIELD(pm.audience_type,'student','staff','guardian'),u.last_name,u.first_name");
        $s->execute(['production'=>$productionId]);
        return $s->fetchAll();
    }

    private static function publishedCast(PDO $db, int $productionId): array
    {
        $s = $db->prepare("SELECT cast_snapshot_json,headline,member_note,published_at FROM production_cast_publications WHERE production_id=:production AND status='published' LIMIT 1");
        $s->execute(['production'=>$productionId]);$row=$s->fetch();
        if (!$row) return [];
        $decoded=json_decode((string)($row['cast_snapshot_json']??'[]'),true);
        $row['cast']=is_array($decoded)?$decoded:[];unset($row['cast_snapshot_json']);
        return $row;
    }

    private static function schedule(PDO $db, int $productionId): array
    {
        $s=$db->prepare("SELECT id,title,starts_at,ends_at,family_call_at,location,item_type,status FROM schedule_items WHERE production_id=:production ORDER BY starts_at,id");
        $s->execute(['production'=>$productionId]);return$s->fetchAll();
    }

    private static function playbill(PDO $db, int $productionId): ?array
    {
        $s=$db->prepare("SELECT * FROM playbills WHERE production_id=:production ORDER BY FIELD(status,'current','archived','draft'),published_at DESC,id DESC LIMIT 1");
        $s->execute(['production'=>$productionId]);$playbill=$s->fetch();if(!$playbill)return null;
        $sections=$db->prepare("SELECT heading,body,section_type,sort_order FROM playbill_sections WHERE playbill_id=:playbill AND active=1 ORDER BY sort_order,id");
        $sections->execute(['playbill'=>(int)$playbill['id']]);$playbill['sections']=$sections->fetchAll();return$playbill;
    }

    private static function resources(PDO $db,int $productionId,array $viewer,array $audiences,bool $manager):array
    {
        $s=$db->prepare("SELECT id,title,category,description,resource_type,resource_url,body,audiences_json,pinned,updated_at FROM production_resources WHERE production_id=:production AND status='active' ORDER BY pinned DESC,category,title");
        $s->execute(['production'=>$productionId]);$out=[];foreach($s->fetchAll() as $row)if($manager||self::audienceAllows((string)$row['audiences_json'],$viewer,$audiences))$out[]=$row;return$out;
    }

    private static function files(PDO $db,int $productionId,array $viewer,array $audiences,bool $manager):array
    {
        $s=$db->prepare("SELECT pf.id,pf.title,pf.category,pf.description,pf.audiences_json,pf.pinned,pf.updated_at,pf.stored_file_id,sfv.original_name,sfv.byte_size,sfv.extension FROM production_files pf JOIN stored_files sf ON sf.id=pf.stored_file_id LEFT JOIN stored_file_versions sfv ON sfv.id=(SELECT v.id FROM stored_file_versions v WHERE v.stored_file_id=pf.stored_file_id ORDER BY v.version_number DESC LIMIT 1) WHERE pf.production_id=:production AND pf.status='active' AND sf.status='active' ORDER BY pf.pinned DESC,pf.category,pf.title");
        $s->execute(['production'=>$productionId]);$out=[];foreach($s->fetchAll() as $row)if($manager||self::audienceAllows((string)$row['audiences_json'],$viewer,$audiences))$out[]=$row;return$out;
    }

    private static function channels(PDO $db,int $productionId,array $viewer,array $audiences,bool $manager):array
    {
        $s=$db->prepare("SELECT c.id,c.name,c.description,c.access_mode,c.read_audiences_json,c.archived_at,(SELECT COUNT(*) FROM channel_posts cp WHERE cp.channel_id=c.id AND cp.moderation_status='published') post_count,(SELECT MAX(cp2.created_at) FROM channel_posts cp2 WHERE cp2.channel_id=c.id AND cp2.moderation_status='published') last_post_at FROM channels c WHERE c.production_id=:production AND c.archived_at IS NULL ORDER BY c.sort_order,c.name");
        $s->execute(['production'=>$productionId]);$out=[];
        foreach($s->fetchAll() as $channel){if($manager||self::historicalChannelAccess($db,$channel,$viewer,$audiences))$out[]=$channel;}
        return$out;
    }

    private static function historicalChannelAccess(PDO $db,array $channel,array $viewer,array $audiences):bool
    {
        $mode=(string)($channel['access_mode']??'audience');$audienceOkay=self::audienceAllows((string)($channel['read_audiences_json']??'[]'),$viewer,$audiences);
        $member=$db->prepare("SELECT 1 FROM channel_members WHERE channel_id=:channel AND user_id=:user AND status='active' AND can_read=1 LIMIT 1");$member->execute(['channel'=>(int)$channel['id'],'user'=>(int)$viewer['id']]);$selected=(bool)$member->fetchColumn();
        $team=$db->prepare("SELECT 1 FROM channel_teams ct JOIN team_members tm ON tm.team_id=ct.team_id AND tm.user_id=:user AND tm.status='active' WHERE ct.channel_id=:channel AND ct.can_read=1 LIMIT 1");$team->execute(['user'=>(int)$viewer['id'],'channel'=>(int)$channel['id']]);$teamOkay=(bool)$team->fetchColumn();
        return match($mode){'selected'=>$selected,'team'=>$teamOkay,'hybrid'=>$audienceOkay||$selected||$teamOkay,default=>$audienceOkay};
    }

    private static function notices(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT n.subject,n.body,n.audience_scope,n.published_at,si.title schedule_title FROM schedule_change_notices n LEFT JOIN schedule_items si ON si.id=n.schedule_item_id WHERE n.production_id=:production AND n.status='published' ORDER BY n.published_at DESC,n.id DESC");$s->execute(['production'=>$productionId]);return$s->fetchAll();
    }

    private static function audienceAllows(string $json,array $viewer,array $audiences):bool
    {
        $allowed=json_decode($json,true);if(!is_array($allowed)||!$allowed)return false;
        $isStudent=AccessPolicy::isStudent($viewer);$isStaff=AccessPolicy::isStaff($viewer);$hasProduction=(bool)$audiences;
        foreach($allowed as $audience){
            if($audience==='all_members'&&$hasProduction)return true;
            if($audience==='production_members'&&$hasProduction)return true;
            if($audience==='production_students'&&in_array('student',$audiences,true))return true;
            if($audience==='production_guardians'&&in_array('guardian',$audiences,true))return true;
            if($audience==='production_staff'&&in_array('staff',$audiences,true))return true;
            if($audience==='students'&&$isStudent)return true;
            if($audience==='staff'&&$isStaff)return true;
            if($audience==='adults'&&!$isStudent)return true;
            if($audience==='volunteers'&&str_contains(strtolower((string)($viewer['role']??'')),'volunteer'))return true;
        }
        return false;
    }
}
