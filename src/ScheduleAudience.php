<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class ScheduleAudience
{
    public static function groups(PDO $db, int $productionId, bool $activeOnly = true): array
    {
        if ($productionId < 1) return [];
        $sql = "SELECT pg.id,pg.production_id,pg.name,pg.group_type,pg.description,pg.active,pg.sort_order,
                       COUNT(DISTINCT CASE WHEN pgm.status='active' AND pm.status='active' AND u.id IS NOT NULL THEN pgm.production_membership_id END) member_count
                FROM production_groups pg
                LEFT JOIN production_group_members pgm ON pgm.group_id=pg.id
                LEFT JOIN production_memberships pm ON pm.id=pgm.production_membership_id
                LEFT JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
                WHERE pg.production_id=:production_id" . ($activeOnly ? " AND pg.active=1" : "") . "
                GROUP BY pg.id,pg.production_id,pg.name,pg.group_type,pg.description,pg.active,pg.sort_order
                ORDER BY pg.active DESC,pg.sort_order,pg.name";
        $stmt=$db->prepare($sql);
        $stmt->execute(['production_id'=>$productionId]);
        return $stmt->fetchAll();
    }

    public static function groupIdsForItem(PDO $db, int $scheduleItemId): array
    {
        if ($scheduleItemId < 1) return [];
        $stmt=$db->prepare('SELECT group_id FROM schedule_item_groups WHERE schedule_item_id=:id ORDER BY group_id');
        $stmt->execute(['id'=>$scheduleItemId]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function groupNamesForItem(PDO $db, int $scheduleItemId): array
    {
        if ($scheduleItemId < 1) return [];
        $stmt=$db->prepare("SELECT pg.name FROM schedule_item_groups sig JOIN production_groups pg ON pg.id=sig.group_id WHERE sig.schedule_item_id=:id ORDER BY pg.sort_order,pg.name");
        $stmt->execute(['id'=>$scheduleItemId]);
        return array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function validateGroupIds(PDO $db, int $productionId, array $groupIds): array
    {
        $ids=self::normalizeGroupIds($groupIds);
        if (!$ids) return [];
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$db->prepare("SELECT id FROM production_groups WHERE production_id=? AND active=1 AND id IN ($placeholders)");
        $stmt->execute(array_merge([$productionId],$ids));
        $valid=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($valid); sort($ids);
        if ($valid !== $ids) throw new RuntimeException('One or more selected production groups are unavailable in this show.');
        return $valid;
    }

    public static function replaceItemGroups(PDO $db, int $scheduleItemId, int $productionId, string $audienceMode, array $groupIds): array
    {
        if (!in_array($audienceMode,['production','groups'],true)) throw new RuntimeException('Choose a valid schedule targeting mode.');
        $validated=$audienceMode==='groups' ? self::validateGroupIds($db,$productionId,$groupIds) : [];
        if ($audienceMode==='groups' && !$validated) throw new RuntimeException('Choose at least one production group for a group-targeted schedule item.');
        $db->prepare('DELETE FROM schedule_item_groups WHERE schedule_item_id=:id')->execute(['id'=>$scheduleItemId]);
        if ($validated) {
            $insert=$db->prepare('INSERT INTO schedule_item_groups (schedule_item_id,group_id) VALUES (:item,:group_id)');
            foreach($validated as $groupId) $insert->execute(['item'=>$scheduleItemId,'group_id'=>$groupId]);
        }
        return $validated;
    }

    public static function audienceMembersForItem(PDO $db, int $scheduleItemId): array
    {
        $stmt=$db->prepare('SELECT id,production_id,visibility,audience_mode FROM schedule_items WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$scheduleItemId]);
        $item=$stmt->fetch();
        if(!$item) return [];
        return self::audienceMembers($db,(int)$item['production_id'],(string)$item['visibility'],(string)$item['audience_mode'],self::groupIdsForItem($db,$scheduleItemId));
    }

    public static function audienceMembers(PDO $db, int $productionId, string $visibility, string $audienceMode = 'production', array $groupIds = []): array
    {
        if ($productionId < 1) return [];
        if (!in_array($visibility,['family','staff','all'],true)) return [];
        if ($audienceMode !== 'groups') return self::productionAudience($db,$productionId,$visibility);

        // Read-time audience resolution must fail closed rather than throw if a previously
        // targeted group has since been deactivated. Strict validation remains on writes.
        $groupIds=self::normalizeGroupIds($groupIds);
        if(!$groupIds) return [];
        $placeholders=implode(',',array_fill(0,count($groupIds),'?'));
        $types=match($visibility){'family'=>['student','guardian'],'staff'=>['staff'],default=>['student','guardian','staff']};
        $typePh=implode(',',array_fill(0,count($types),'?'));
        $sql="SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name,pm.audience_type,u.last_name sort_last_name,u.first_name sort_first_name
              FROM production_group_members pgm
              JOIN production_groups pg ON pg.id=pgm.group_id AND pg.active=1
              JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.status='active'
              JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
              WHERE pg.production_id=? AND pgm.status='active' AND pg.id IN ($placeholders) AND pm.audience_type IN ($typePh)
                AND (pm.audience_type<>'guardian' OR EXISTS (
                    SELECT 1 FROM family_relationships fr
                    JOIN production_memberships spm ON spm.production_id=pm.production_id AND spm.user_id=fr.student_user_id AND spm.audience_type='student' AND spm.status='active'
                    JOIN users student ON student.id=spm.user_id AND student.active=1 AND student.account_status<>'disabled'
                    WHERE fr.guardian_user_id=pm.user_id AND fr.status='active'
                ))";
        $stmt=$db->prepare($sql);
        $stmt->execute(array_merge([$productionId],$groupIds,$types));
        $rows=$stmt->fetchAll();
        $byId=[];
        foreach($rows as $row) $byId[(int)$row['id']]=$row;

        if(in_array($visibility,['family','all'],true)){
            $studentStmt=$db->prepare("SELECT DISTINCT pm.user_id
                FROM production_group_members pgm
                JOIN production_groups pg ON pg.id=pgm.group_id AND pg.active=1
                JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.status='active' AND pm.audience_type='student'
                JOIN users student ON student.id=pm.user_id AND student.active=1 AND student.account_status<>'disabled'
                WHERE pg.production_id=? AND pgm.status='active' AND pg.id IN ($placeholders)");
            $studentStmt->execute(array_merge([$productionId],$groupIds));
            $studentIds=array_map('intval',$studentStmt->fetchAll(PDO::FETCH_COLUMN));
            if($studentIds){
                $studentPh=implode(',',array_fill(0,count($studentIds),'?'));
                $guardianStmt=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name,gpm.audience_type,u.last_name sort_last_name,u.first_name sort_first_name
                    FROM family_relationships fr
                    JOIN production_memberships gpm ON gpm.user_id=fr.guardian_user_id AND gpm.production_id=? AND gpm.audience_type='guardian' AND gpm.status='active'
                    JOIN users u ON u.id=gpm.user_id AND u.active=1 AND u.account_status<>'disabled'
                    WHERE fr.status='active' AND fr.student_user_id IN ($studentPh)");
                $guardianStmt->execute(array_merge([$productionId],$studentIds));
                foreach($guardianStmt->fetchAll() as $row) $byId[(int)$row['id']]=$row;
            }
        }
        uasort($byId,static fn(array $a,array $b):int=>[$a['sort_last_name'],$a['sort_first_name']]<=>[$b['sort_last_name'],$b['sort_first_name']]);
        return array_values($byId);
    }

    private static function productionAudience(PDO $db,int $productionId,string $visibility): array
    {
        $types=match($visibility){'family'=>['student','guardian'],'staff'=>['staff'],default=>['student','guardian','staff']};
        $ph=implode(',',array_fill(0,count($types),'?'));
        $stmt=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name,pm.audience_type,u.last_name sort_last_name,u.first_name sort_first_name
            FROM production_memberships pm
            JOIN users u ON u.id=pm.user_id
            WHERE pm.production_id=? AND pm.status='active' AND u.active=1 AND u.account_status<>'disabled' AND pm.audience_type IN ($ph)
              AND (pm.audience_type<>'guardian' OR EXISTS (
                  SELECT 1 FROM family_relationships fr
                  JOIN production_memberships spm ON spm.production_id=pm.production_id AND spm.user_id=fr.student_user_id AND spm.audience_type='student' AND spm.status='active'
                  JOIN users student ON student.id=spm.user_id AND student.active=1 AND student.account_status<>'disabled'
                  WHERE fr.guardian_user_id=pm.user_id AND fr.status='active'
              ))
            ORDER BY sort_last_name,sort_first_name");
        $stmt->execute(array_merge([$productionId],$types));
        return $stmt->fetchAll();
    }

    public static function userCanViewItem(PDO $db,array $user,array $item): bool
    {
        if(AccessPolicy::canManageProduction($user)) return true;
        $productionId=(int)($item['production_id']??0);
        if($productionId<1) return false;
        $audienceType=self::productionAudienceType($db,(int)$user['id'],$productionId);
        if($audienceType===null) return false;
        $visibility=(string)($item['visibility']??'all');
        $visibilityAllows=match($visibility){'family'=>in_array($audienceType,['student','guardian'],true),'staff'=>$audienceType==='staff',default=>true};
        if(!$visibilityAllows) return false;
        if((string)($item['audience_mode']??'production')!=='groups') return true;

        $scheduleItemId=(int)($item['id']??0);
        if($scheduleItemId<1) return false;
        $direct=$db->prepare("SELECT 1
            FROM schedule_item_groups sig
            JOIN production_groups pg ON pg.id=sig.group_id AND pg.active=1 AND pg.production_id=:production
            JOIN production_group_members pgm ON pgm.group_id=pg.id AND pgm.status='active'
            JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.status='active'
            JOIN users participant ON participant.id=pm.user_id AND participant.active=1 AND participant.account_status<>'disabled'
            WHERE sig.schedule_item_id=:item AND pm.user_id=:user LIMIT 1");
        $direct->execute(['production'=>$productionId,'item'=>$scheduleItemId,'user'=>(int)$user['id']]);
        if($direct->fetchColumn()) return true;

        if($audienceType==='guardian' && in_array($visibility,['family','all'],true)){
            $guardian=$db->prepare("SELECT 1
                FROM family_relationships fr
                JOIN production_memberships spm ON spm.user_id=fr.student_user_id AND spm.production_id=:production AND spm.audience_type='student' AND spm.status='active'
                JOIN users student ON student.id=spm.user_id AND student.active=1 AND student.account_status<>'disabled'
                JOIN production_group_members pgm ON pgm.production_membership_id=spm.id AND pgm.status='active'
                JOIN production_groups pg ON pg.id=pgm.group_id AND pg.active=1 AND pg.production_id=:production_group
                JOIN schedule_item_groups sig ON sig.group_id=pg.id AND sig.schedule_item_id=:item
                WHERE fr.guardian_user_id=:guardian AND fr.status='active' LIMIT 1");
            $guardian->execute(['production'=>$productionId,'production_group'=>$productionId,'item'=>$scheduleItemId,'guardian'=>(int)$user['id']]);
            return (bool)$guardian->fetchColumn();
        }
        return false;
    }

    private static function productionAudienceType(PDO $db,int $userId,int $productionId): ?string
    {
        return ProductionContext::audienceType($db,$userId,$productionId);
    }

    private static function normalizeGroupIds(array $groupIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval',$groupIds),static fn(int $id):bool=>$id>0)));
    }
}
