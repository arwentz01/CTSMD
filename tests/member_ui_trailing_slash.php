<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/MemberUI.php';
foreach (['/my-theatre','/my-theatre/','/my-theatre/?context=self','/forms/view/?id=3'] as $url) {
    $_SERVER['REQUEST_URI']=$url;
    ob_start();
    MemberUI::styles('');
    $links=(string)ob_get_clean();
    if (!str_contains(MemberUI::bodyClass(),'ux-member') || !str_contains($links,'ux-member.css')) {
        throw new RuntimeException('Member styling was not enabled for '.$url);
    }
}
echo "PASS: member styling remains active for directory-style trailing-slash URLs.\n";
