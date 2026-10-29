<?php

declare(strict_types=1);

/** Presentation only. Callers retain ownership of permissions, data and actions. */
final class WorkspaceUI
{
    public static function styles(string $basePath): void
    {
        $href = htmlspecialchars(($basePath ?: '') . '/assets/css/ux-workspaces.css?v=20261027', ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" data-ux-workspaces href="' . $href . '">';
        $operational = htmlspecialchars(($basePath ?: '') . '/assets/css/ux-operational.css?v=20261027', ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" data-ux-operational href="' . $operational . '">';
        $script = htmlspecialchars(($basePath ?: '') . '/assets/js/ux-workspaces.js?v=20261027', ENT_QUOTES, 'UTF-8');
        echo '<script defer src="' . $script . '"></script>';
    }

    /** Explicit administrative opt-in; does not alter routes, data or permissions. */
    public static function operationsStyles(string $basePath): void
    {
        self::styles($basePath);
        $root = htmlspecialchars(($basePath ?: '') . '/assets/', ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" data-ux-operations href="' . $root . 'css/ux-operations.css?v=20261029">';
        echo '<script defer src="' . $root . 'js/ux-operations.js?v=20261029"></script>';
    }

    public static function pageHeader(string $title, string $description, ?array $action = null): void
    {
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        echo '<header class="ux-page-header"><div><h2>' . $e($title) . '</h2><p>' . $e($description) . '</p></div>';
        if ($action !== null) {
            echo '<a class="ux-context-action" href="' . $e($action['href']) . '">' . $e($action['label']) . '</a>';
        }
        echo '</header>';
    }

    /** Presentation-only lock. Does not replace the write handler's lifecycle enforcement. */
    public static function productionReadOnly(PDO $db, ?int $productionId): bool
    {
        if (!$productionId) return false;
        require_once __DIR__.'/ProductionLifecycleGuard.php';
        try { ProductionLifecycleGuard::assertWritable($db, $productionId); return false; }
        catch (RuntimeException $error) { return true; }
    }

    public static function readinessLabel(array $finding): string
    {
        if ((int)($finding['count'] ?? 0) < 1) return 'Ready';
        return ($finding['severity'] ?? '') === 'blocker' ? 'Blocked' : 'Warning';
    }

    public static function formReadOnly(PDO $db, int $formId): bool
    {
        $query=$db->prepare('SELECT production_id FROM forms WHERE id=:id LIMIT 1');
        $query->execute(['id'=>$formId]);
        $productionId=$query->fetchColumn();
        if ($productionId === false) return true;
        return self::productionReadOnly($db,$productionId===null?null:(int)$productionId);
    }

    public static function workflow(array $stages): void
    {
        echo '<ol class="ux-workflow" aria-label="Casting workflow">';
        foreach ($stages as $title => $description) {
            echo '<li><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</span></li>';
        }
        echo '</ol>';
    }
}
