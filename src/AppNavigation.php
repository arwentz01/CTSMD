<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';

final class AppNavigation
{
    public static function renderSidebar(string $route, string $basePath, array $user): void
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $staff = AccessPolicy::isStaff($user);

        $isActive = static function (string $path) use ($route): string {
            if ($path === '/app') {
                return in_array($route, ['/app', '/family-hub', '/family/action', '/notifications', '/forms', '/forms/view'], true) ? ' active' : '';
            }
            if ($path === '/production') {
                return in_array($route, ['/production', '/production/people', '/schedule', '/production/day', '/production/edit', '/production/notices', '/production/notice', '/resources', '/playbills'], true) ? ' active' : '';
            }
            if ($path === '/volunteer-readiness') {
                return in_array($route, ['/volunteer-readiness', '/volunteer-shifts', '/volunteer/shift', '/volunteer/approvals'], true) ? ' active' : '';
            }
            return ($route === $path || str_starts_with($route, rtrim($path, '/') . '/')) ? ' active' : '';
        };
        ?>
        <aside class="unified-sidebar" data-unified-sidebar>
            <div class="unified-sidebar-head">
                <a class="unified-brand" href="<?= $url('/app') ?>"><span>C</span><b>CTSMD <small>CONNECT</small></b></a>
                <button class="unified-close" type="button" data-nav-close aria-label="Close navigation">×</button>
            </div>
            <nav class="unified-nav" aria-label="Primary navigation">
                <a class="unified-nav-item<?= $isActive('/app') ?>" href="<?= $url('/app') ?>"><i>⌂</i><span><b>Home</b><small>Today & family</small></span></a>

                <span class="unified-nav-label">Theatre</span>
                <a class="unified-nav-item<?= $isActive('/production') ?>" href="<?= $url('/production') ?>"><i>★</i><span><b>Production</b><small>Schedule, calls & resources</small></span></a>
                <a class="unified-nav-item<?= $isActive('/channels') ?>" href="<?= $url('/channels') ?>"><i>#</i><span><b>Community</b><small>Channels & announcements</small></span></a>
                <a class="unified-nav-item<?= $isActive('/messages') ?>" href="<?= $url('/messages') ?>"><i>✉</i><span><b>Messages</b><small>Protected conversations</small></span></a>
                <a class="unified-nav-item<?= $isActive('/volunteer-readiness') ?>" href="<?= $url('/volunteer-readiness') ?>"><i>♡</i><span><b>Volunteer</b><small>Readiness & shifts</small></span></a>

                <?php if ($staff): ?>
                <span class="unified-nav-label">Operations</span>
                <a class="unified-nav-item<?= $isActive('/people') ?>" href="<?= $url('/people') ?>"><i>♟</i><span><b>People</b><small>Families, roles & access</small></span></a>
                <a class="unified-nav-item<?= $route === '/production/people' ? ' active' : '' ?>" href="<?= $url('/production/people') ?>"><i>★</i><span><b>Production roster</b><small>Cast, guardians & staff</small></span></a>
                <a class="unified-nav-item<?= str_starts_with($route, '/admin/volunteer-approvals') ? ' active' : '' ?>" href="<?= $url('/admin/volunteer-approvals') ?>"><i>♡</i><span><b>Volunteer Operations</b><small>Approval queue & staffing</small></span></a>
                <a class="unified-nav-item<?= str_starts_with($route, '/admin/forms') ? ' active' : '' ?>" href="<?= $url('/admin/forms') ?>"><i>✓</i><span><b>Forms Review</b><small>Submissions & approvals</small></span></a>
                <a class="unified-nav-item<?= $route === '/production/notices' || $route === '/production/notice' ? ' active' : '' ?>" href="<?= $url('/production/notices') ?>"><i>↗</i><span><b>Production updates</b><small>Review & publish changes</small></span></a>
                <a class="unified-nav-item<?= $isActive('/safeguarding') ?>" href="<?= $url('/safeguarding') ?>"><i>●</i><span><b>Safeguarding</b><small>Restricted review</small></span></a>
                <?php endif; ?>
            </nav>

            <div class="unified-sidebar-foot">
                <a href="<?= $url('/notifications') ?>">Notifications</a>
                <?php if (AccessPolicy::localIdentitySwitchEnabled()): ?><a href="<?= $url('/dev/identity') ?>">Switch test identity</a><?php endif; ?>
                <a href="<?= $url('/prototype') ?>">Design review</a>
                <div class="unified-user"><i><?= $esc((string)$user['initials']) ?></i><span><b><?= $esc((string)$user['name']) ?></b><small><?= $esc((string)$user['role']) ?></small></span></div>
            </div>
        </aside>
        <div class="unified-nav-scrim" data-nav-scrim></div>
        <?php
    }

    public static function renderHeader(string $eyebrow, string $title, string $basePath, ?array $subnav = null): void
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        ?>
        <header class="unified-header">
            <button class="unified-menu" type="button" data-nav-open aria-label="Open navigation">☰</button>
            <div class="unified-title"><small><?= $esc($eyebrow) ?></small><h1><?= $esc($title) ?></h1></div>
            <div class="unified-utilities"><a href="<?= $url('/notifications') ?>">Notifications</a><span class="unified-avatar"><?= $esc(substr((string)$title, 0, 1)) ?></span></div>
        </header>
        <?php if ($subnav): ?>
        <nav class="unified-subnav" aria-label="Section navigation">
            <?php foreach ($subnav as $item): ?>
                <a href="<?= $url($item['href']) ?>"<?= !empty($item['active']) ? ' class="active"' : '' ?>><?= $esc($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <?php
    }
}
