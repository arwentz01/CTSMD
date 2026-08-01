<?php

declare(strict_types=1);

namespace App\View;

use RuntimeException;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $views = dirname(__DIR__, 2) . '/views';
        $templateFile = $views . '/' . $template . '.php';

        if (!is_file($templateFile)) {
            throw new RuntimeException('View not found.');
        }

        extract($data, EXTR_SKIP);
        $app = $app ?? require dirname(__DIR__, 2) . '/config/app.php';
        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        ob_start();
        require $views . '/layout.php';
        return (string) ob_get_clean();
    }
}
