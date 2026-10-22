<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/PlaybillQrService.php';

$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{
    ++$checks;
    if(!$condition)throw new RuntimeException('FAIL: '.$message);
};

$url='https://connect.example.test/playbill?slug=sample-production';
$opaque=PlaybillQrService::svg($url,false,8);
$transparent=PlaybillQrService::svg($url,true,8);
$assert(str_starts_with($opaque,'<?xml'),'QR output is XML.');
$assert(str_contains($opaque,'<svg'),'QR output contains an SVG root.');
$assert(str_contains($opaque,'<rect'),'Opaque QR includes a background.');
$assert(!str_contains($transparent,'<rect'),'Transparent QR omits the background.');
$assert(str_contains($transparent,'<path'),'QR output contains encoded modules.');

$experience=file_get_contents(dirname(__DIR__).'/src/Playbill2Experience.php');
$renderer=file_get_contents(dirname(__DIR__).'/src/PlaybillPublicRenderer.php');
$assert(is_string($experience)&&str_contains($experience,"public_enabled=1"),'Public lookup requires public availability.');
$enhancements=file_get_contents(dirname(__DIR__).'/src/PlaybillEnhancementService.php');
$assert(is_string($enhancements)&&str_contains($enhancements,"approval_status='approved'"),'Public cast lookup requires approval.');
$assert(str_contains($enhancements,'pcp.visible=1'),'Hidden cast profiles are excluded from public data.');
$assert(str_contains($enhancements,"u.account_status<>'disabled'"),'Disabled cast accounts are excluded from public data.');
$assert(is_string($renderer)&&str_contains($renderer,'X-Robots-Tag: noindex, nofollow'),'Public biographies default to noindex.');

echo "Playbill publication checks passed: {$checks}\n";
