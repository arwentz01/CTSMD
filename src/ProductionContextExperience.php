<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/ProductionContext.php';

final class ProductionContextExperience
{
    private const ROUTE = '/production/select';

    public static function handles(string $route): bool
    {
        return $route === self::ROUTE;
    }

    public static function render(string $basePath): never
    {
        Auth::startSession();

        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::redirect($basePath . '/production');
        }

        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['production_context_csrf'] ?? ''), $token)) {
            $_SESSION['production_context_flash'] = ['type' => 'error', 'message' => 'Your production selector expired. Please try again.'];
            self::redirect($basePath . '/production');
        }

        $productionId = filter_input(INPUT_POST, 'production_id', FILTER_VALIDATE_INT) ?: 0;
        $returnTo = self::safeReturnPath((string)($_POST['return_to'] ?? '/production'));

        try {
            $production = ProductionContext::select($db, $user, (int)$productionId);
            $_SESSION['production_context_flash'] = [
                'type' => 'success',
                'message' => 'Working production changed to ' . $production['title'] . '.',
            ];
        } catch (RuntimeException $e) {
            $_SESSION['production_context_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        }

        self::redirect($basePath . $returnTo);
    }

    private static function safeReturnPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/production';
        }
        return $path;
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
