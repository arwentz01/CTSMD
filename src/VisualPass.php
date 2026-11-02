<?php

declare(strict_types=1);

final class VisualPass
{
    private const ROUTES = [
        '/prototype',
        '/family-hub',
        '/communication-review',
        '/volunteer-readiness',
        '/production',
        '/production/day',
        '/people',
        '/people/detail',
        '/safeguarding',
        '/safeguarding/review',
        '/resources',
        '/notifications',
        '/states',
    ];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath, array $data): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $user = $data['user'];
        $currentProduction = $data['playbills'][0]['title'] ?? 'Current production';
        $people = $data['people'];
        $primaryPerson = $people[0] ?? $user;

        $pages = [
            '/prototype' => ['Visual Pass 2', 'Prototype map'],
            '/family-hub' => ['Family & member', 'Family hub'],
            '/communication-review' => ['Communication', 'Safeguarded communication'],
            '/volunteer-readiness' => ['Volunteer', 'Readiness & eligibility'],
            '/production' => ['Production', 'Production workspace'],
            '/production/day' => ['Production', 'Production day'],
            '/people' => ['Administration', 'People & families'],
            '/people/detail' => ['Administration', 'Person detail'],
            '/safeguarding' => ['Safeguarding', 'Safety review workspace'],
            '/safeguarding/review' => ['Safeguarding', 'Review detail'],
            '/resources' => ['Production', 'Resource hub'],
            '/notifications' => ['Member', 'Notification center'],
            '/states' => ['Design system', 'States & feedback'],
        ];
        [$eyebrow, $title] = $pages[$route];

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#a6192e">
    <title><?= $esc($title) ?> · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/visual-pass.css') ?>">
</head>
<body class="app-body">
<div class="vp-shell">
    <aside class="vp-sidebar">
        <a class="vp-brand" href="<?= $url('/prototype') ?>"><span>C</span><b>CTSMD <small>CONNECT</small></b></a>
        <nav>
            <a href="<?= $url('/prototype') ?>" <?= $route === '/prototype' ? 'class="active"' : '' ?>>Review map</a>
            <span>Member experience</span>
            <a href="<?= $url('/family-hub') ?>" <?= $route === '/family-hub' ? 'class="active"' : '' ?>>Family hub</a>
            <a href="<?= $url('/communication-review') ?>" <?= $route === '/communication-review' ? 'class="active"' : '' ?>>Communication</a>
            <a href="<?= $url('/volunteer-readiness') ?>" <?= $route === '/volunteer-readiness' ? 'class="active"' : '' ?>>Volunteer readiness</a>
            <span>Production</span>
            <a href="<?= $url('/production') ?>" <?= str_starts_with($route, '/production') ? 'class="active"' : '' ?>>Production home</a>
            <a href="<?= $url('/resources') ?>" <?= $route === '/resources' ? 'class="active"' : '' ?>>Resources</a>
            <span>Administration</span>
            <a href="<?= $url('/people') ?>" <?= str_starts_with($route, '/people') ? 'class="active"' : '' ?>>People & families</a>
            <a href="<?= $url('/safeguarding') ?>" <?= str_starts_with($route, '/safeguarding') ? 'class="active"' : '' ?>>Safeguarding</a>
            <a href="<?= $url('/states') ?>" <?= $route === '/states' ? 'class="active"' : '' ?>>States & feedback</a>
        </nav>
        <div class="vp-user"><i><?= $esc($user['initials']) ?></i><span><b><?= $esc($user['name']) ?></b><small><?= $esc($user['role']) ?></small></span></div>
    </aside>

    <main class="vp-main">
        <header class="vp-header">
            <div><small><?= $esc($eyebrow) ?></small><h1><?= $esc($title) ?></h1></div>
            <div class="vp-header-actions"><a href="<?= $url('/notifications') ?>">Notifications</a><a class="button button-small" href="<?= $url('/prototype') ?>">Review map</a></div>
        </header>

        <div class="vp-page">
        <?php if ($route === '/prototype'): ?>
            <section class="vp-intro"><span>VISUAL PASS 2</span><h2>Now give every slice enough depth to feel real.</h2><p>Pass 1 established the map. Pass 2 tests detail hierarchy, role context, mobile behavior, safety language, empty states and the handoffs between slices. Still no deep CRUD.</p></section>
            <div class="vp-pass-grid">
                <a class="vp-pass-card" href="<?= $url('/family-hub') ?>"><span>01</span><small>FAMILY & MEMBER</small><h3>Family context without clutter</h3><p>Linked students, forms, upcoming work and relevant theatre activity in one phone-first view.</p><b>Open slice →</b></a>
                <a class="vp-pass-card" href="<?= $url('/communication-review') ?>"><span>02</span><small>COMMUNICATION</small><h3>Make safety structural</h3><p>Show why a conversation is safeguarded and who is included without making messaging feel punitive.</p><b>Open slice →</b></a>
                <a class="vp-pass-card" href="<?= $url('/volunteer-readiness') ?>"><span>03</span><small>VOLUNTEER</small><h3>Explain eligibility</h3><p>A volunteer should understand readiness, locked shifts and next actions in seconds.</p><b>Open slice →</b></a>
                <a class="vp-pass-card" href="<?= $url('/production/day') ?>"><span>04</span><small>PRODUCTION</small><h3>Run the day</h3><p>Give staff a desktop operational view while keeping the family-facing schedule easy to scan.</p><b>Open slice →</b></a>
                <a class="vp-pass-card" href="<?= $url('/people/detail') ?>"><span>05</span><small>ADMINISTRATION</small><h3>People as relationships</h3><p>Profile, role, volunteer context and family/safety relationships before settings and permissions.</p><b>Open slice →</b></a>
                <a class="vp-pass-card" href="<?= $url('/safeguarding/review') ?>"><span>06</span><small>SAFEGUARDING</small><h3>Review exceptions calmly</h3><p>Restricted staff workflow that emphasizes evidence, status and next action rather than alarm.</p><b>Open slice →</b></a>
            </div>
            <section class="vp-review"><div><small>PASS 2 TEST</small><h3>Do the details reinforce the product?</h3></div><div class="vp-review-list"><span>Member actions are obvious on mobile</span><span>Staff density increases on desktop</span><span>Locked states explain themselves</span><span>Safety rules feel built-in, not bolted-on</span><span>Empty states still feel intentional</span><span>Every detail page has a clear escape route</span></div></section>

        <?php elseif ($route === '/family-hub'): ?>
            <section class="vp-intro compact"><span>MY FAMILY</span><h2><?= $esc($user['name']) ?></h2><p>A family home should answer what is happening, what needs action, and who it affects without forcing parents through admin-style screens.</p></section>
            <div class="vp-family-layout">
                <section class="vp-stack">
                    <article class="vp-action-card"><small>ACTION NEEDED</small><h3><?= (int)$data['family_context']['open_forms'] ?> forms still need attention</h3><p>Keep time-sensitive acknowledgments and family requirements above general updates.</p><a href="<?= $url('/forms') ?>">Review forms →</a></article>
                    <article class="vp-panel"><header><small>LINKED PEOPLE</small><h3>Your theatre family</h3></header><?php if ($data['family_context']['linked_people']): ?><?php foreach ($data['family_context']['linked_people'] as $person): ?><div class="vp-person-row"><i><?= $esc($person['initials']) ?></i><span><b><?= $esc($person['name']) ?></b><small><?= $esc($person['role']) ?> · Guardian visibility active where required</small></span><button>Open</button></div><?php endforeach; ?><?php else: ?><div class="vp-empty-state"><b>No linked students yet</b><span>When family relationships are assigned, they will appear here.</span></div><?php endif; ?></article>
                </section>
                <aside class="vp-stack"><article class="vp-panel"><header><small>NEXT UP</small><h3>Schedule</h3></header><?php foreach (array_slice($data['schedule'], 0, 3) as $item): ?><div class="vp-agenda-row"><b><?= $esc($item['time']) ?></b><span><strong><?= $esc($item['title']) ?></strong><small><?= $esc($item['detail']) ?></small></span></div><?php endforeach; ?><a class="vp-inline-link" href="<?= $url('/schedule') ?>">Full schedule →</a></article><article class="vp-panel"><header><small>VOLUNTEER</small><h3><?= (int)$data['family_context']['eligible_shifts'] ?> eligible opportunities</h3></header><p class="vp-muted">Only shifts you are currently cleared to take should feel actionable.</p><a class="vp-inline-link" href="<?= $url('/volunteer-readiness') ?>">See readiness →</a></article></aside>
            </div>

        <?php elseif ($route === '/communication-review'): ?>
            <section class="vp-intro compact"><span>COMMUNICATION & SAFETY</span><h2>Conversation should feel normal. Safeguarding should be unmistakable.</h2><p>CTSMD Connect should explain protected conversation structure in plain language while enforcing the real rule server-side later.</p></section>
            <div class="vp-conversation-grid">
                <section class="vp-panel"><header><small>YOUR CONVERSATIONS</small><h3>Messages</h3></header><?php foreach ($data['conversation_overview'] as $conversation): ?><article class="vp-conversation-row"><div><b><?= $esc($conversation['subject']) ?></b><small><?= $esc($conversation['latest']) ?> · <?= (int)$conversation['messages'] ?> messages</small></div><span class="vp-type <?= $esc($conversation['type']) ?>"><?= $esc($conversation['type']) ?></span></article><?php endforeach; ?></section>
                <section class="vp-safety-card"><small>WHY THIS IS DIFFERENT</small><h3>Guardian visibility is part of the conversation.</h3><p>When a student is involved with an adult, the approved guardian is not an optional CC. The relationship is structural and eventually enforced on the server.</p><div class="vp-safety-chain"><span>STAFF</span><i>＋</i><span>STUDENT</span><i>＋</i><span>GUARDIAN</span></div><footer>Student-to-student direct messaging remains unavailable for MVP.</footer></section>
            </div>
            <section class="vp-panel vp-composer-demo"><header><small>COMPOSER STATE</small><h3>Protected conversation</h3></header><div class="vp-system-note">● Guardian visibility is active for this conversation.</div><div class="vp-composer"><textarea placeholder="Write a message…" disabled></textarea><button class="button" disabled>Send</button></div><small class="vp-muted">Visual-only composer for this pass. No message write path is being built yet.</small></section>

        <?php elseif ($route === '/volunteer-readiness'): ?>
            <section class="vp-intro compact"><span>VOLUNTEER READINESS</span><h2>Make “Can I sign up?” answer itself.</h2><p>Eligibility should be transparent. People should never discover a missing requirement only after trying to claim a shift.</p></section>
            <div class="vp-readiness-grid"><section class="vp-panel"><header><small>YOUR REQUIREMENTS</small><h3><?= $esc($user['name']) ?></h3></header><?php foreach ($data['credential_summary'] as $credential): ?><div class="vp-credential-row"><span><b><?= $esc($credential['name']) ?></b><small><?= $esc($credential['expires']) ?></small></span><em class="vp-status <?= strtolower(str_replace(' ', '-', $credential['status'])) ?>"><?= $esc($credential['status']) ?></em></div><?php endforeach; ?></section><section class="vp-panel"><header><small>SHIFT PREVIEW</small><h3>What your status unlocks</h3></header><?php foreach (array_slice($data['shifts'], 0, 4) as $shift): ?><article class="vp-shift-preview <?= $shift['status'] === 'locked' ? 'locked' : '' ?>"><div><b><?= $esc($shift['title']) ?></b><small><?= $esc($shift['when']) ?> · <?= $esc($shift['location']) ?></small><small><?= $esc($shift['requirements']) ?></small></div><span><?= $shift['status'] === 'eligible' ? 'Eligible' : 'Locked' ?></span></article><?php endforeach; ?><a class="vp-inline-link" href="<?= $url('/volunteer-shifts') ?>">Browse all shifts →</a></section></div>

        <?php elseif ($route === '/production'): ?>
            <section class="vp-intro compact"><span>CURRENT PRODUCTION</span><h2><?= $esc($currentProduction) ?></h2><p>A single operational home for the show, with family-facing information separated from staff controls.</p></section>
            <div class="vp-hero-grid"><article class="vp-feature-card dark"><small>NEXT ON THE SCHEDULE</small><h3><?= $esc($data['schedule'][0]['title'] ?? 'No upcoming item') ?></h3><p><?= $esc($data['schedule'][0]['detail'] ?? 'Nothing currently scheduled') ?></p><a href="<?= $url('/production/day') ?>">Open production day →</a></article><article class="vp-feature-card"><small>PRODUCTION HEALTH</small><div class="vp-kpis"><span><b><?= count($data['announcements']) ?></b>updates</span><span><b><?= count($data['shifts']) ?></b>volunteer shifts</span><span><b><?= count($data['forms']) ?></b>assigned forms</span></div></article></div>
            <div class="vp-module-grid"><a href="<?= $url('/production/day') ?>"><b>Production day</b><small>Calls, locations, staff notes and coverage in one operational view.</small></a><a href="<?= $url('/resources') ?>"><b>Resources</b><small>Scripts, music, choreography, packets and family documents.</small></a><a href="<?= $url('/channels') ?>"><b>Channels</b><small>Cast, crew, costume and parent communication.</small></a><a href="<?= $url('/playbills') ?>"><b>Playbill</b><small>Current production Playbill and archive.</small></a><a href="<?= $url('/volunteers') ?>"><b>Volunteer coverage</b><small>Gaps that could affect the production.</small></a><a href="<?= $url('/forms') ?>"><b>Forms & agreements</b><small>Family and volunteer completion state.</small></a></div>

        <?php elseif ($route === '/production/day'): ?>
            <section class="vp-intro compact"><span><?= $esc(strtoupper($currentProduction)) ?></span><h2>Production day</h2><p>Desktop-first operational density, but still usable from a phone backstage.</p></section>
            <div class="vp-day-layout"><section class="vp-panel"><header><small>RUN OF DAY</small><h3>Calls & activity</h3></header><?php foreach ($data['schedule'] as $item): ?><div class="vp-run-row"><time><?= $esc($item['time']) ?></time><span><b><?= $esc($item['title']) ?></b><small><?= $esc($item['detail']) ?></small></span><em><?= $esc($item['tag']) ?></em></div><?php endforeach; ?></section><aside class="vp-stack"><article class="vp-panel"><header><small>VOLUNTEER PRESSURE</small><h3>Coverage</h3></header><?php foreach ($data['volunteer_stats'] as $metric): ?><div class="vp-mini-metric"><b><?= $esc($metric['value']) ?></b><span><?= $esc($metric['label']) ?><small><?= $esc($metric['note']) ?></small></span></div><?php endforeach; ?></article><article class="vp-panel"><header><small>RECENT CHANGE</small><h3><?= $esc($data['announcements'][0]['title'] ?? 'No recent updates') ?></h3></header><p class="vp-muted"><?= $esc($data['announcements'][0]['body'] ?? 'No production updates in the seeded dataset.') ?></p></article></aside></div>

        <?php elseif ($route === '/people'): ?>
            <section class="vp-intro compact"><span>ADMINISTRATION</span><h2>People are relationships, not rows.</h2><p>Roles, volunteer readiness, productions and safety context should be visible before settings and permissions.</p></section>
            <div class="vp-toolbar"><label>⌕ <input placeholder="Search people, students or families"></label><a class="button" href="<?= $url('/people/detail') ?>">Preview detail</a></div>
            <div class="vp-people-grid"><?php foreach ($people as $person): ?><a class="vp-family-card" href="<?= $url('/people/detail') ?>"><header><div><i><?= $esc($person['initials']) ?></i><span><b><?= $esc($person['name']) ?></b><small><?= $esc($person['role']) ?></small></span></div><span class="pill <?= strtolower($person['status']) ?>"><?= $esc($person['status']) ?></span></header><div class="vp-family-link"><span>CURRENT CONTEXT</span><b><?= $esc($person['context']) ?></b><small>Open the person to see relationships and operational context.</small></div><footer><span>Seeded organization record</span><b>Open →</b></footer></a><?php endforeach; ?></div>

        <?php elseif ($route === '/people/detail'): ?>
            <a class="vp-back" href="<?= $url('/people') ?>">← People & families</a>
            <section class="vp-profile-hero"><i><?= $esc($primaryPerson['initials']) ?></i><div><small>PERSON RECORD</small><h2><?= $esc($primaryPerson['name']) ?></h2><p><?= $esc($primaryPerson['role']) ?> · <?= $esc($primaryPerson['context']) ?></p></div><span class="pill <?= strtolower($primaryPerson['status']) ?>"><?= $esc($primaryPerson['status']) ?></span></section>
            <div class="vp-detail-grid"><section class="vp-panel"><header><small>RELATIONSHIPS</small><h3>Family & safeguarding context</h3></header><?php if ($data['family_context']['linked_people']): ?><?php foreach ($data['family_context']['linked_people'] as $linked): ?><div class="vp-person-row"><i><?= $esc($linked['initials']) ?></i><span><b><?= $esc($linked['name']) ?></b><small><?= $esc($linked['role']) ?></small></span><span class="vp-type safeguarded">linked</span></div><?php endforeach; ?><?php else: ?><div class="vp-empty-state"><b>No family relationship in this seeded view</b><span>This is how an intentional empty state should look, not a blank panel.</span></div><?php endif; ?></section><section class="vp-panel"><header><small>OPERATIONAL CONTEXT</small><h3>Current access signals</h3></header><div class="vp-info-list"><span><b>Role</b><small><?= $esc($primaryPerson['role']) ?></small></span><span><b>Volunteer state</b><small><?= $esc($primaryPerson['status']) ?></small></span><span><b>Record source</b><small>Seeded organization data</small></span></div></section></div>

        <?php elseif ($route === '/safeguarding'): ?>
            <section class="vp-intro compact"><span>RESTRICTED WORKSPACE</span><h2>Safety review without turning the whole app into surveillance.</h2><p>Only appropriate administrators should see this area. Exceptions, evidence and next actions get priority.</p></section>
            <div class="vp-kpi-row"><?php foreach ($data['safeguarding']['metrics'] as $metric): ?><article><b><?= $esc($metric['value']) ?></b><span><?= $esc($metric['label']) ?></span></article><?php endforeach; ?></div>
            <section class="vp-review-queue"><header><div><small>REVIEW QUEUE</small><h3>Needs safeguarding attention</h3></div><a href="<?= $url('/safeguarding/review') ?>">Preview review</a></header><?php if ($data['safeguarding']['queue']): ?><?php foreach ($data['safeguarding']['queue'] as $item): ?><a href="<?= $url('/safeguarding/review') ?>" class="vp-review-row"><span class="vp-severity <?= strtolower($item['severity']) ?>"><?= $esc($item['severity']) ?></span><div><b><?= $esc($item['title']) ?></b><small><?= $esc($item['detail']) ?></small></div><b>Review →</b></a><?php endforeach; ?><?php else: ?><div class="vp-empty-state"><b>No review items</b><span>The queue is clear in the current seeded dataset.</span></div><?php endif; ?></section>

        <?php elseif ($route === '/safeguarding/review'): ?>
            <a class="vp-back" href="<?= $url('/safeguarding') ?>">← Safeguarding queue</a>
            <?php $review = $data['safeguarding']['queue'][0] ?? null; ?>
            <section class="vp-review-detail"><div><small>RESTRICTED REVIEW</small><h2><?= $esc($review['title'] ?? 'No current review item') ?></h2><p><?= $esc($review['detail'] ?? 'The seeded review queue is currently empty.') ?></p></div><span class="vp-severity <?= strtolower($review['severity'] ?? 'review') ?>"><?= $esc($review['severity'] ?? 'Clear') ?></span></section>
            <div class="vp-detail-grid"><section class="vp-panel"><header><small>EVIDENCE</small><h3>What the system knows</h3></header><div class="vp-info-list"><span><b>Source</b><small>Seeded credential/compliance record</small></span><span><b>Current state</b><small><?= $esc($review['detail'] ?? 'No exception') ?></small></span><span><b>Audit expectation</b><small>Any eventual action should preserve who changed what and when.</small></span></div></section><section class="vp-panel"><header><small>NEXT ACTION</small><h3>Visual workflow only</h3></header><p class="vp-muted">Future controls can approve, request more information, or document an exception. Pass 2 only establishes hierarchy and language.</p><div class="vp-action-row"><button class="button" disabled>Approve</button><button class="button secondary" disabled>Request information</button></div></section></div>

        <?php elseif ($route === '/resources'): ?>
            <section class="vp-intro compact"><span><?= $esc(strtoupper($currentProduction)) ?> · RESOURCE HUB</span><h2>Everything people keep asking someone to resend.</h2><p>Resource categories are visual placeholders in this pass. Persisted resource records come later.</p></section>
            <div class="vp-resource-grid"><article><span>★</span><small>THIS WEEK</small><h3>Production packet</h3><p>Call sheets, arrival information and current family notes.</p><button>Preview category</button></article><article><span>♫</span><small>CAST</small><h3>Music & rehearsal</h3><p>Future approved practice material organized by number.</p><button>Preview category</button></article><article><span>▶</span><small>REHEARSAL</small><h3>Review media</h3><p>Future reference videos grouped by scene and ensemble.</p><button>Preview category</button></article><article><span>▤</span><small>FAMILIES</small><h3>Parent documents</h3><p>Future handbooks, venue information and production expectations.</p><button>Preview category</button></article></div>

        <?php elseif ($route === '/notifications'): ?>
            <section class="vp-intro compact"><span>YOUR INBOX</span><h2>Notifications should answer “what changed?”</h2><p>Action-required items stay separate from awareness updates.</p></section>
            <div class="vp-notification-layout"><section><header><small>ACTION REQUIRED</small><h3>Needs you</h3></header><?php $actions = 0; foreach ($data['forms'] as $form): if ($form['status'] !== 'Completed'): $actions++; ?><article class="vp-note <?= $form['status'] === 'Missing' ? 'urgent' : '' ?>"><i>!</i><div><b><?= $esc($form['title']) ?></b><small><?= $esc($form['status']) ?> · Due <?= $esc($form['due']) ?></small></div><a href="<?= $url('/forms') ?>">Review</a></article><?php endif; endforeach; ?><?php if ($actions === 0): ?><div class="vp-empty-state"><b>You are caught up</b><span>No action-required notifications in the current dataset.</span></div><?php endif; ?></section><section><header><small>WHAT CHANGED</small><h3>Updates</h3></header><?php foreach ($data['announcements'] as $announcement): ?><article class="vp-note"><i>#</i><div><b><?= $esc($announcement['title']) ?></b><small><?= $esc($announcement['meta']) ?></small></div><a href="<?= $url('/channels') ?>">Open</a></article><?php endforeach; ?></section></div>

        <?php elseif ($route === '/states'): ?>
            <section class="vp-intro compact"><span>VISUAL LANGUAGE</span><h2>The boring states are part of the product.</h2><p>Before wiring behavior, we need a consistent visual answer for empty, loading, locked, successful and failed moments.</p></section>
            <div class="vp-state-grid"><article><small>EMPTY</small><div class="vp-empty-state"><b>Nothing needs your attention</b><span>Keep empty states calm and useful.</span></div></article><article><small>LOADING</small><div class="vp-skeleton"><i></i><span></span><span></span></div></article><article><small>LOCKED</small><div class="vp-lock-state"><b>● Requirements needed</b><span>Explain exactly what blocks the action.</span><button disabled>Unavailable</button></div></article><article><small>SUCCESS</small><div class="vp-feedback success"><b>✓ Saved</b><span>The interface confirms the action without taking over the screen.</span></div></article><article><small>WARNING</small><div class="vp-feedback warning"><b>! Review needed</b><span>Use urgency proportionally.</span></div></article><article><small>ERROR</small><div class="vp-feedback error"><b>Couldn’t complete that action</b><span>Say what happened and what the person can do next.</span></div></article></div>
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
