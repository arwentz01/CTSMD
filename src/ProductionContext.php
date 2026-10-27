<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';

final class ProductionContext
{
    private const SESSION_KEY = 'selected_production_id';
    private static array $activeProductionCache = [];

    public static function activeProductions(PDO $db, array $user): array
    {
        $cacheKey=(int)$user['id'].':'.(AccessPolicy::isStaff($user)?'staff':'member');
        if(isset(self::$activeProductionCache[$cacheKey]))return self::$activeProductionCache[$cacheKey];

        if (AccessPolicy::isStaff($user)) {
            $stmt = $db->query("SELECT id, title, season, status, lifecycle_stage, audition_starts_at, audition_ends_at, performance_starts_at, performance_ends_at, is_active FROM productions WHERE is_active = 1 OR lifecycle_stage IN ('upcoming','planning') ORDER BY title, id");
            return self::$activeProductionCache[$cacheKey]=$stmt->fetchAll();
        }

        // Member pages aggregate every CURRENT production on the student's roster.
        // Guardians inherit access from an active relationship to a rostered child;
        // they do not require their own redundant production_memberships row.
        // Do not couple member visibility to staff's selected production or to
        // lifecycle stages (planning/callbacks/rehearsals are all relevant).
        $stmt=$db->prepare("SELECT DISTINCT p.id,p.title,p.season,p.status,p.lifecycle_stage,
            p.audition_starts_at,p.audition_ends_at,p.performance_starts_at,p.performance_ends_at,p.is_active
            FROM productions p
            WHERE p.is_active=1 AND (
                EXISTS (
                    SELECT 1 FROM production_memberships pm
                    WHERE pm.production_id=p.id AND pm.user_id=:user_id AND pm.status='active'
                    AND (pm.audience_type<>'guardian' OR EXISTS (
                        SELECT 1 FROM family_relationships fr
                        JOIN production_memberships child_pm ON child_pm.production_id=p.id
                             AND child_pm.user_id=fr.student_user_id AND child_pm.audience_type='student'
                             AND child_pm.status='active'
                        JOIN users child ON child.id=fr.student_user_id AND child.active=1
                             AND child.account_status<>'disabled'
                        WHERE fr.guardian_user_id=:guardian_id AND fr.status='active'
                             AND fr.relationship_type IN ('parent','guardian')
                    ))
                )
                OR EXISTS (
                    SELECT 1 FROM family_relationships fr
                    JOIN users child ON child.id=fr.student_user_id AND child.active=1
                         AND child.account_status<>'disabled'
                    JOIN production_memberships child_pm ON child_pm.user_id=child.id
                         AND child_pm.production_id=p.id AND child_pm.audience_type='student'
                         AND child_pm.status='active'
                    WHERE fr.guardian_user_id=:family_id AND fr.status='active'
                         AND fr.relationship_type IN ('parent','guardian')
                )
            ) ORDER BY p.title,p.id");
        $stmt->execute(['user_id'=>(int)$user['id'],'guardian_id'=>(int)$user['id'],'family_id'=>(int)$user['id']]);
        return self::$activeProductionCache[$cacheKey]=$stmt->fetchAll();
    }

    public static function selected(PDO $db, array $user, ?int $requestedId = null): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $available = self::activeProductions($db, $user);
        if (!$available) {
            unset($_SESSION[self::SESSION_KEY]);
            return null;
        }

        $availableById = [];
        foreach ($available as $production) {
            $availableById[(int)$production['id']] = $production;
        }

        if ($requestedId && isset($availableById[$requestedId])) {
            $_SESSION[self::SESSION_KEY] = $requestedId;
            return $availableById[$requestedId];
        }

        $sessionId = (int)($_SESSION[self::SESSION_KEY] ?? 0);
        if ($sessionId > 0 && isset($availableById[$sessionId])) {
            return $availableById[$sessionId];
        }

        $first = $available[0];
        $_SESSION[self::SESSION_KEY] = (int)$first['id'];
        return $first;
    }

    public static function select(PDO $db, array $user, int $productionId): array
    {
        $selected = self::selected($db, $user, $productionId);
        if (!$selected || (int)$selected['id'] !== $productionId) {
            throw new RuntimeException('That production is not active or is not available to your account.');
        }
        return $selected;
    }

    public static function clearRequestCache():void{self::$activeProductionCache=[];}

    public static function userHasActiveProduction(PDO $db, int $userId): bool
    {
        $stmt=$db->prepare("SELECT 1 FROM productions p WHERE p.is_active=1
            AND (EXISTS (SELECT 1 FROM production_memberships pm
                WHERE pm.production_id=p.id AND pm.user_id=:user_id AND pm.status='active'
                AND (pm.audience_type<>'guardian' OR EXISTS (
                    SELECT 1 FROM family_relationships fr
                    JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id
                         AND cpm.production_id=p.id AND cpm.audience_type='student' AND cpm.status='active'
                    JOIN users child ON child.id=fr.student_user_id AND child.active=1
                         AND child.account_status<>'disabled'
                    WHERE fr.guardian_user_id=:guardian AND fr.status='active'
                         AND fr.relationship_type IN ('parent','guardian')
                )))
            OR EXISTS (
                SELECT 1 FROM family_relationships fr
                JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id AND cpm.production_id=p.id
                     AND cpm.audience_type='student' AND cpm.status='active'
                JOIN users child ON child.id=fr.student_user_id AND child.active=1
                     AND child.account_status<>'disabled'
                WHERE fr.guardian_user_id=:family AND fr.status='active'
                     AND fr.relationship_type IN ('parent','guardian')
            )) LIMIT 1");
        $stmt->execute(['user_id'=>$userId,'guardian'=>$userId,'family'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function isLiveProductionStage(PDO $db,int $productionId):bool
    {
        if($productionId<1)return false;
        $q=$db->prepare("SELECT 1 FROM productions WHERE id=:id AND is_active=1 AND lifecycle_stage IN ('in_production','performing') LIMIT 1");
        $q->execute(['id'=>$productionId]);
        return (bool)$q->fetchColumn();
    }

    public static function isActiveMember(PDO $db, int $userId, int $productionId): bool
    {
        if($productionId<1)return false;
        $stmt=$db->prepare("SELECT 1 FROM productions p WHERE p.id=:production_id AND p.is_active=1
           AND (EXISTS (
               SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id
                 AND pm.user_id=:user_id AND pm.status='active'
                 AND (pm.audience_type<>'guardian' OR EXISTS (
                    SELECT 1 FROM family_relationships fr
                    JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id
                      AND cpm.production_id=p.id AND cpm.audience_type='student' AND cpm.status='active'
                    JOIN users child ON child.id=fr.student_user_id AND child.active=1
                      AND child.account_status<>'disabled'
                    WHERE fr.guardian_user_id=:guardian AND fr.status='active'
                      AND fr.relationship_type IN ('parent','guardian')
                 ))
           ) OR EXISTS (
               SELECT 1 FROM family_relationships fr
               JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id
                 AND cpm.production_id=p.id AND cpm.audience_type='student' AND cpm.status='active'
               JOIN users child ON child.id=fr.student_user_id AND child.active=1
                 AND child.account_status<>'disabled'
               WHERE fr.guardian_user_id=:family AND fr.status='active'
                 AND fr.relationship_type IN ('parent','guardian')
           )) LIMIT 1");
        $stmt->execute(['production_id'=>$productionId,'user_id'=>$userId,'guardian'=>$userId,'family'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function audienceType(PDO $db, int $userId, int $productionId): ?string
    {
        if($productionId<1)return null;
        // Direct staff/student roster entries take precedence; guardian eligibility
        // is independently derived from active parent/guardian -> student links.
        $direct=$db->prepare("SELECT pm.audience_type FROM production_memberships pm
            JOIN productions p ON p.id=pm.production_id AND p.is_active=1
            JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled'
            WHERE pm.production_id=:production AND pm.user_id=:user AND pm.status='active'
            AND pm.audience_type<>'guardian'
            ORDER BY FIELD(pm.audience_type,'staff','student') LIMIT 1");
        $direct->execute(['production'=>$productionId,'user'=>$userId]);
        $type=$direct->fetchColumn();
        if($type!==false)return (string)$type;
        $guardian=$db->prepare("SELECT 1 FROM productions p
            JOIN production_memberships child_pm ON child_pm.production_id=p.id
                AND child_pm.audience_type='student' AND child_pm.status='active'
            JOIN users student ON student.id=child_pm.user_id AND student.active=1
                AND student.account_status<>'disabled'
            JOIN family_relationships fr ON fr.student_user_id=student.id
                AND fr.status='active' AND fr.relationship_type IN ('parent','guardian')
            JOIN users adult ON adult.id=fr.guardian_user_id AND adult.active=1
                AND adult.account_status<>'disabled'
            WHERE p.id=:production AND p.is_active=1 AND fr.guardian_user_id=:user LIMIT 1");
        $guardian->execute(['production'=>$productionId,'user'=>$userId]);
        return $guardian->fetchColumn()?'guardian':null;
    }
}
