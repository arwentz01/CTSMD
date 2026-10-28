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

    public static function workflow(array $stages): void
    {
        echo '<ol class="ux-workflow" aria-label="Casting workflow">';
        foreach ($stages as $title => $description) {
            echo '<li><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</span></li>';
        }
        echo '</ol>';
    }
}
