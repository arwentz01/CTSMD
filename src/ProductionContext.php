<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';

final class ProductionContext
{
    private const SESSION_KEY = 'selected_production_id';

    public static function activeProductions(PDO $db, array $user): array
    {
        if (AccessPolicy::isStaff($user)) {
            $stmt = $db->query("SELECT id, title, season, status, is_active FROM productions WHERE is_active = 1 ORDER BY title, id");
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare("SELECT DISTINCT p.id, p.title, p.season, p.status, p.is_active
            FROM productions p
            JOIN production_memberships pm ON pm.production_id = p.id
            JOIN users u ON u.id = pm.user_id AND u.active = 1 AND u.account_status <> 'disabled'
            WHERE p.is_active = 1
              AND pm.user_id = :user_id
              AND pm.status = 'active'
              AND (
                  pm.audience_type <> 'guardian'
                  OR EXISTS (
                      SELECT 1
                      FROM family_relationships fr
                      JOIN production_memberships spm
                        ON spm.production_id=pm.production_id
                       AND spm.user_id=fr.student_user_id
                       AND spm.audience_type='student'
                       AND spm.status='active'
                      JOIN users student
                        ON student.id=spm.user_id
                       AND student.active=1
                       AND student.account_status<>'disabled'
                      WHERE fr.guardian_user_id=pm.user_id
                        AND fr.status='active'
                  )
              )
            ORDER BY p.title, p.id");
        $stmt->execute(['user_id' => (int)$user['id']]);
        return $stmt->fetchAll();
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

    public static function userHasActiveProduction(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare("SELECT 1
            FROM production_memberships pm
            JOIN productions p ON p.id = pm.production_id
            JOIN users u ON u.id = pm.user_id AND u.active = 1 AND u.account_status <> 'disabled'
            WHERE pm.user_id = :user_id
              AND pm.status = 'active'
              AND p.is_active = 1
              AND (
                  pm.audience_type <> 'guardian'
                  OR EXISTS (
                      SELECT 1
                      FROM family_relationships fr
                      JOIN production_memberships spm
                        ON spm.production_id=pm.production_id
                       AND spm.user_id=fr.student_user_id
                       AND spm.audience_type='student'
                       AND spm.status='active'
                      JOIN users student
                        ON student.id=spm.user_id
                       AND student.active=1
                       AND student.account_status<>'disabled'
                      WHERE fr.guardian_user_id=pm.user_id
                        AND fr.status='active'
                  )
              )
            LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function isActiveMember(PDO $db, int $userId, int $productionId): bool
    {
        if ($productionId < 1) return false;
        $stmt = $db->prepare("SELECT 1
            FROM production_memberships pm
            JOIN productions p ON p.id = pm.production_id
            JOIN users u ON u.id = pm.user_id AND u.active = 1 AND u.account_status <> 'disabled'
            WHERE pm.production_id = :production_id
              AND pm.user_id = :user_id
              AND pm.status = 'active'
              AND p.is_active = 1
              AND (
                  pm.audience_type <> 'guardian'
                  OR EXISTS (
                      SELECT 1
                      FROM family_relationships fr
                      JOIN production_memberships spm
                        ON spm.production_id=pm.production_id
                       AND spm.user_id=fr.student_user_id
                       AND spm.audience_type='student'
                       AND spm.status='active'
                      JOIN users student
                        ON student.id=spm.user_id
                       AND student.active=1
                       AND student.account_status<>'disabled'
                      WHERE fr.guardian_user_id=pm.user_id
                        AND fr.status='active'
                  )
              )
            LIMIT 1");
        $stmt->execute(['production_id' => $productionId, 'user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function audienceType(PDO $db, int $userId, int $productionId): ?string
    {
        if ($productionId < 1) return null;
        $stmt = $db->prepare("SELECT pm.audience_type
            FROM production_memberships pm
            JOIN productions p ON p.id = pm.production_id
            JOIN users u ON u.id = pm.user_id AND u.active = 1 AND u.account_status <> 'disabled'
            WHERE pm.production_id = :production_id
              AND pm.user_id = :user_id
              AND pm.status = 'active'
              AND p.is_active = 1
              AND (
                  pm.audience_type <> 'guardian'
                  OR EXISTS (
                      SELECT 1
                      FROM family_relationships fr
                      JOIN production_memberships spm
                        ON spm.production_id=pm.production_id
                       AND spm.user_id=fr.student_user_id
                       AND spm.audience_type='student'
                       AND spm.status='active'
                      JOIN users student
                        ON student.id=spm.user_id
                       AND student.active=1
                       AND student.account_status<>'disabled'
                      WHERE fr.guardian_user_id=pm.user_id
                        AND fr.status='active'
                  )
              )
            ORDER BY FIELD(pm.audience_type,'staff','guardian','student')
            LIMIT 1");
        $stmt->execute(['production_id' => $productionId, 'user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string)$value : null;
    }
}
