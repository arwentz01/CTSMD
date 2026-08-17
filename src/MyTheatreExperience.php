<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class MyTheatreExperience
{
    private const ROUTE = '/my-theatre';

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

        $productions = ProductionContext::activeProductions($db, $user);
        $hasActiveProduction = (bool)$productions;
        $hasArchive = self::hasArchive($db, $user);
        $hasFamily = self::hasFamily($db, (int)$user['id']);
        self::page($basePath, $user, $productions, $hasActiveProduction, $hasArchive, $hasFamily);
    }

    private static function hasFamily(PDO $db, int $userId): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:user AND status='active' LIMIT 1");
        $stmt->execute(['user'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function hasArchive(PDO $db, array $user): bool
    {
        if (AccessPolicy::canManageProduction($user)) {
            return (bool)$db->query("SELECT 1 FROM productions WHERE is_active=0 AND status='archived' LIMIT 1")->fetchColumn();
        }
        $stmt = $db->prepare("SELECT 1 FROM productions p WHERE p.is_active=0 AND p.status='archived' AND (EXISTS (SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id AND pm.user_id=:viewer) OR EXISTS (SELECT 1 FROM family_relationships fr JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id AND cpm.production_id=p.id AND cpm.audience_type='student' WHERE fr.guardian_user_id=:guardian AND fr.status='active')) LIMIT 1");
        $stmt->execute(['viewer'=>(int)$user['id'],'guardian'=>(int)$user['id']]);
        return (bool)$stmt->fetchColumn();
    }

    private static function page(string $basePath, array $user, array $productions, bool $hasActiveProduction, bool $hasArchive, bool $hasFamily): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $approved = Auth::isApprovedMember($user);
        $student = AccessPolicy::isStudent($user);
        $staff = AccessPolicy::isStaff($user);
        $cards = [];

        if ($hasActiveProduction || $staff) {
            $cards[] = ['icon'=>'◫','label'=>'Calendar','detail'=>'Rehearsals, performances, calls, conflicts and upcoming activity across your active shows.','href'=>'/calendar'];
        }
        if ($hasActiveProduction) {
            $cards[] = ['icon'=>'★','label'=>'Cast','detail'=>'Published roles and casting information for productions connected to your account.','href'=>'/cast'];
        }
        if ($hasFamily || $student) {
            $cards[] = ['icon'=>'✦','label'=>'Theatre History','detail'=>'Profiles, credits and acting résumé information that grows with each production.','href'=>'/theatre-history'];
        }
        if ($approved || $hasActiveProduction || $staff) {
            $cards[] = ['icon'=>'▤','label'=>'Resources','detail'=>'CTSMD-wide information plus production files and links available to your account.','href'=>'/resources'];
        }
        if ($approved || $hasActiveProduction) {
            $cards[] = ['icon'=>'♡','label'=>'Volunteer','detail'=>'Readiness, available shifts, training, approvals and your service record.','href'=>'/volunteer-readiness'];
            $cards[] = ['icon'=>'✓','label'=>'Forms','detail'=>'Assigned forms, production requirements and household paperwork that needs attention.','href'=>'/forms'];
        }
        if ($hasArchive) {
            $cards[] = ['icon'=>'◷','label'=>'Archive','detail'=>'Past productions, historical resources and Community content you are allowed to revisit.','href'=>'/archive'];
        }

        header('Content-Type:text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>My Theatre · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/my-theatre.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar(self::ROUTE,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('CTSMD','My Theatre',$basePath);?><div class="mytheatre-page">
        <section class="mytheatre-hero"><div><small>YOUR CTSMD EXPERIENCE</small><h2>Everything about your time in the theatre.</h2><p>Calendar, cast, forms, resources, volunteering and history live here so the main navigation can stay simple.</p></div><span><?=$e((string)count($productions))?> active show<?=count($productions)===1?'':'s'?></span></section>
        <?php if($productions):?><section class="mytheatre-shows"><header><small>ACTIVE PRODUCTIONS</small><h3>Your current shows</h3></header><div><?php foreach($productions as $production):?><article><i>★</i><span><b><?=$e((string)$production['title'])?></b><small><?=$e((string)($production['season'] ?: 'Active production'))?></small></span></article><?php endforeach;?></div></section><?php endif;?>
        <?php if($cards):?><section class="mytheatre-grid"><?php foreach($cards as $card):?><a href="<?=$url($card['href'])?>"><i><?=$e($card['icon'])?></i><div><h3><?=$e($card['label'])?></h3><p><?=$e($card['detail'])?></p><span>Open →</span></div></a><?php endforeach;?></section><?php else:?><section class="mytheatre-empty"><h3>Your theatre tools will appear here.</h3><p>Once your membership or production access is active, CTSMD Connect will place the relevant tools in this hub automatically.</p></section><?php endif;?>
        </div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
