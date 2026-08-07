<?php

declare(strict_types=1);

final class VisualPass
{
    public static function handles(string $route): bool
    {
        return in_array($route, [
            '/prototype',
            '/production',
            '/people',
            '/safeguarding',
            '/resources',
            '/notifications',
        ], true);
    }

    public static function render(string $route, string $basePath, array $data): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $user = $data['user'];
        $currentProduction = $data['playbills'][0]['title'] ?? 'Current production';
        $pages = [
            '/prototype' => ['Visual Pass 1', 'Prototype map'],
            '/production' => ['Production', 'Production workspace'],
            '/people' => ['People & families', 'People and family relationships'],
            '/safeguarding' => ['Safeguarding', 'Safety review workspace'],
            '/resources' => ['Resources', 'Production resource hub'],
            '/notifications' => ['Notifications', 'Notification center'],
        ];
        [$eyebrow, $title] = $pages[$route];

        $sliceCards = [
            ['Family & member', 'What do I need today?', 'Fast, phone-first access to schedules, announcements, forms, volunteer shifts and family actions.', '/app', 'Ready for review'],
            ['Communication & safety', 'Channels + safeguarded messaging', 'Community discussion without losing the structural guardian visibility that makes this product different.', '/messages', 'Ready for review'],
            ['Volunteer', 'Coverage + eligibility', 'See open work, understand why a shift is locked, and give coordinators a useful operational picture.', '/volunteers', 'Ready for review'],
            ['Production', 'One production workspace', 'Bring schedule, resources, call information, Playbills and production-specific work into a coherent home.', '/production', 'New visual slice'],
            ['Administration', 'People, safety and operations', 'Desktop-first tools for relationships, permissions, review queues and organization management.', '/people', 'New visual slice'],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $esc($title) ?> · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/visual-pass.css') ?>">
</head>
<body class="app-body">
<div class="vp-shell">
    <aside class="vp-sidebar">
        <a class="vp-brand" href="<?= $url('/app') ?>"><span>C</span><b>CTSMD <small>CONNECT</small></b></a>
        <nav>
            <a href="<?= $url('/prototype') ?>" <?= $route === '/prototype' ? 'class="active"' : '' ?>>Prototype map</a>
            <span>Member</span>
            <a href="<?= $url('/app') ?>">Today</a>
            <a href="<?= $url('/channels') ?>">Channels</a>
            <a href="<?= $url('/messages') ?>">Messages</a>
            <a href="<?= $url('/schedule') ?>">Schedule</a>
            <a href="<?= $url('/forms') ?>">Forms</a>
            <span>Operations</span>
            <a href="<?= $url('/production') ?>" <?= $route === '/production' ? 'class="active"' : '' ?>>Production</a>
            <a href="<?= $url('/volunteers') ?>">Volunteer ops</a>
            <a href="<?= $url('/people') ?>" <?= $route === '/people' ? 'class="active"' : '' ?>>People & families</a>
            <a href="<?= $url('/safeguarding') ?>" <?= $route === '/safeguarding' ? 'class="active"' : '' ?>>Safeguarding</a>
            <a href="<?= $url('/resources') ?>" <?= $route === '/resources' ? 'class="active"' : '' ?>>Resources</a>
        </nav>
        <div class="vp-user"><i><?= $esc($user['initials']) ?></i><span><b><?= $esc($user['name']) ?></b><small><?= $esc($user['role']) ?></small></span></div>
    </aside>
    <main class="vp-main">
        <header class="vp-header"><div><small><?= $esc($eyebrow) ?></small><h1><?= $esc($title) ?></h1></div><div class="vp-header-actions"><a href="<?= $url('/notifications') ?>">Notifications</a><a class="button" href="<?= $url('/prototype') ?>">Review map</a></div></header>
        <div class="vp-page">
        <?php if ($route === '/prototype'): ?>
            <section class="vp-intro"><span>VISUAL PASS 1</span><h2>Build the whole theatre in broad strokes first.</h2><p>This is the review map for CTSMD Connect. Every slice should become understandable and navigable before we deepen forms, permissions, persistence, or automation.</p></section>
            <div class="vp-slice-grid">
                <?php foreach ($sliceCards as $card): ?>
                <a class="vp-slice-card" href="<?= $url($card[3]) ?>"><small><?= $esc($card[0]) ?></small><h3><?= $esc($card[1]) ?></h3><p><?= $esc($card[2]) ?></p><footer><span><?= $esc($card[4]) ?></span><b>Open →</b></footer></a>
                <?php endforeach; ?>
            </div>
            <section class="vp-review"><div><small>PASS RULE</small><h3>We are judging experience, not completeness.</h3></div><div class="vp-review-list"><span>Can I tell where I am?</span><span>Can I tell what needs attention?</span><span>Does this feel like theatre rather than SaaS?</span><span>Does mobile vs desktop priority make sense?</span><span>Are safety rules visible without feeling scary?</span></div></section>

        <?php elseif ($route === '/production'): ?>
            <section class="vp-intro compact"><span>CURRENT PRODUCTION</span><h2><?= $esc($currentProduction) ?></h2><p>A single operational home for the show, with the information families need separated from the controls staff need.</p></section>
            <div class="vp-hero-grid"><article class="vp-feature-card dark"><small>NEXT ON THE SCHEDULE</small><h3><?= $esc($data['schedule'][0]['title'] ?? 'No upcoming item') ?></h3><p><?= $esc($data['schedule'][0]['detail'] ?? 'Nothing currently scheduled') ?></p><a href="<?= $url('/schedule') ?>">Open run of show →</a></article><article class="vp-feature-card"><small>PRODUCTION HEALTH</small><div class="vp-kpis"><span><b><?= count($data['announcements']) ?></b>updates</span><span><b><?= count($data['shifts']) ?></b>volunteer shifts</span><span><b><?= count($data['forms']) ?></b>assigned forms</span></div></article></div>
            <div class="vp-module-grid"><a href="<?= $url('/schedule') ?>"><b>Schedule & calls</b><small>Rehearsals, performances, call times and audience-specific notes.</small></a><a href="<?= $url('/resources') ?>"><b>Production resources</b><small>Scripts, music, choreography, packets and family documents.</small></a><a href="<?= $url('/channels') ?>"><b>Production channels</b><small>Cast, crew, costume and parent communication in context.</small></a><a href="<?= $url('/playbills') ?>"><b>Playbill</b><small>Current production Playbill and archive experience.</small></a><a href="<?= $url('/volunteers') ?>"><b>Volunteer coverage</b><small>See gaps that could affect the production.</small></a><a href="<?= $url('/forms') ?>"><b>Forms & agreements</b><small>What families and volunteers still need to complete.</small></a></div>

        <?php elseif ($route === '/people'): ?>
            <section class="vp-intro compact"><span>ADMINISTRATION</span><h2>People are relationships, not rows.</h2><p>The admin experience should make roles, volunteer readiness, productions and safety context obvious before anyone opens a record.</p></section>
            <div class="vp-toolbar"><label>⌕ <input placeholder="Search people, students or families"></label><button class="button">Add person</button></div>
            <div class="vp-people-grid">
                <?php foreach ($data['people'] as $person): ?>
                <article class="vp-family-card"><header><div><i><?= $esc($person['initials']) ?></i><span><b><?= $esc($person['name']) ?></b><small><?= $esc($person['role']) ?></small></span></div><span class="pill <?= strtolower($person['status']) ?>"><?= $esc($person['status']) ?></span></header><div class="vp-family-link"><span>Current context</span><b><?= $esc($person['context']) ?></b><small>Relationship and assignment details will layer here in the next visual pass.</small></div><footer><span>Seeded organization record</span><button>Open person</button></footer></article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($route === '/safeguarding'): ?>
            <section class="vp-intro compact"><span>RESTRICTED WORKSPACE</span><h2>Safety review without turning the whole app into a surveillance tool.</h2><p>Only the right administrators should see this area. The visual hierarchy emphasizes exceptions, required follow-up and auditable actions.</p></section>
            <div class="vp-kpi-row"><?php foreach ($data['safeguarding']['metrics'] as $metric): ?><article><b><?= $esc($metric['value']) ?></b><span><?= $esc($metric['label']) ?></span></article><?php endforeach; ?></div>
            <section class="vp-review-queue"><header><div><small>REVIEW QUEUE</small><h3>Needs safeguarding attention</h3></div><button>Filters</button></header><?php if ($data['safeguarding']['queue']): ?><?php foreach ($data['safeguarding']['queue'] as $item): ?><article><span class="vp-severity <?= strtolower($item['severity']) ?>"><?= $esc($item['severity']) ?></span><div><b><?= $esc($item['title']) ?></b><small><?= $esc($item['detail']) ?></small></div><button>Review</button></article><?php endforeach; ?><?php else: ?><div class="vp-empty"><b>No review items.</b><span>The queue is clear in the current seeded dataset.</span></div><?php endif; ?></section>

        <?php elseif ($route === '/resources'): ?>
            <section class="vp-intro compact"><span><?= $esc(strtoupper($currentProduction)) ?> · RESOURCE HUB</span><h2>Everything people keep asking someone to resend.</h2><p>Production resources should be findable by purpose, audience and urgency instead of buried in old posts.</p></section>
            <div class="vp-resource-grid"><article><span>★</span><small>THIS WEEK</small><h3>Tech week packet</h3><p>Call sheet, arrival map, costume checklist and family notes.</p><button>Open packet</button></article><article><span>♫</span><small>CAST</small><h3>Music & rehearsal tracks</h3><p>Approved practice material organized by number.</p><button>Browse music</button></article><article><span>▶</span><small>CHOREOGRAPHY</small><h3>Review videos</h3><p>Reference videos grouped by scene and ensemble.</p><button>View videos</button></article><article><span>▤</span><small>FAMILIES</small><h3>Parent documents</h3><p>Handbook, venue information, parking and production expectations.</p><button>Browse documents</button></article></div>

        <?php elseif ($route === '/notifications'): ?>
            <section class="vp-intro compact"><span>YOUR INBOX</span><h2>Notifications should answer “what changed?”</h2><p>Not every post deserves an alert. This view separates action-required items from awareness updates.</p></section>
            <div class="vp-notification-layout"><section><header><small>ACTION REQUIRED</small><h3>Needs you</h3></header><?php foreach ($data['forms'] as $form): ?><?php if ($form['status'] !== 'Completed'): ?><article class="vp-note <?= $form['status'] === 'Missing' ? 'urgent' : '' ?>"><i>!</i><div><b><?= $esc($form['title']) ?></b><small><?= $esc($form['status']) ?> · Due <?= $esc($form['due']) ?></small></div><a href="<?= $url('/forms') ?>">Review</a></article><?php endif; ?><?php endforeach; ?><?php foreach ($data['shifts'] as $shift): ?><?php if ($shift['status'] === 'eligible'): ?><article class="vp-note"><i>♡</i><div><b><?= $esc($shift['title']) ?></b><small><?= $esc($shift['when']) ?> · <?= $esc($shift['slots']) ?></small></div><a href="<?= $url('/volunteer-shifts') ?>">View</a></article><?php break; ?><?php endif; ?><?php endforeach; ?></section><section><header><small>WHAT CHANGED</small><h3>Updates</h3></header><?php foreach ($data['announcements'] as $announcement): ?><article class="vp-note"><i>#</i><div><b><?= $esc($announcement['title']) ?></b><small><?= $esc($announcement['meta']) ?></small></div><a href="<?= $url('/channels') ?>">Open</a></article><?php endforeach; ?></section></div>
        <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
<?php
        exit;
    }
}
