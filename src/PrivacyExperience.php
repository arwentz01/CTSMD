<?php
declare(strict_types=1);
require_once __DIR__.'/PublicPolicyExperience.php';

/** Compatibility wrapper for legacy callers. The public privacy policy has one source of truth. */
final class PrivacyExperience
{
    public static function render(): never
    {
        $basePath=(string)(getenv('APP_BASE_PATH')?:'');
        PublicPolicyExperience::render('/privacy',$basePath);
    }
}
