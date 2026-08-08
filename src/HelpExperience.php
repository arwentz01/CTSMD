<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';

final class HelpExperience
{
    public static function handles(string $route): bool{return $route==='/help';}

    public static function render(string $basePath): never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $u=static fn(string $p):string=>($basePath?:'').$p;
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Help & Safety · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/product-polish.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/help',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('CTSMD Connect','Help & Safety',$basePath);?><div class="help-page"><section class="help-hero"><small>HELP · SAFETY · FEATURES</small><h2>Connect with confidence.</h2><p>Quick answers about how CTSMD Connect works, what families can see, and where to go when you need help.</p></section><nav class="help-jumps" aria-label="Help topics"><a href="#messages">Messaging safety</a><a href="#community">Community</a><a href="#family">Families</a><a href="#privacy">Privacy & access</a></nav><section id="messages" class="help-section"><div class="help-icon">?</div><div><small>MESSAGING SAFETY</small><h3>Safeguarded conversations</h3><p>When a student and CTSMD staff member communicate directly, Connect includes an active guardian in the conversation automatically. The guardian remains part of the thread and can see messages and attachments. Student-to-student direct messaging is not available.</p><p>If the required safety structure is missing, sending is paused until staff resolves it.</p></div></section><section id="community" class="help-section"><div class="help-icon">#</div><div><small>COMMUNITY</small><h3>Rooms for the people who need them</h3><p>Community channels may be organization-wide, production-specific, audience-based, selected-member, or Team-based. You only see channels your account is authorized to access.</p></div></section><section id="family" class="help-section"><div class="help-icon">♟</div><div><small>FAMILIES</small><h3>One account view for your household</h3><p>Guardians can see linked children’s calls, forms, production participation, and other family activity without switching production context.</p></div></section><section id="privacy" class="help-section"><div class="help-icon">✓</div><div><small>PRIVACY & ACCESS</small><h3>Access follows your role and relationships</h3><p>Production membership, organization membership, family relationships, private channel membership, and staff permissions are checked by the server. If you believe you can see something you should not—or cannot see something you should—contact CTSMD staff.</p></div></section></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
