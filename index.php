<?php

declare(strict_types=1);

$data = require __DIR__ . '/src/mock-data.php';

$basePath = rtrim((string)($_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH') ?: ''), '/');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath)) ?: '/';
}
$route = rtrim($uri, '/') ?: '/';

if ($route === '/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'app' => 'CTSMD Connect', 'build' => '001'], JSON_PRETTY_PRINT);
    exit;
}

$routes = [
    '/' => ['Public landing', 'landing'],
    '/app' => ['Today', 'dashboard'],
    '/parent' => ['My family', 'parent'],
    '/staff' => ['Staff overview', 'staff'],
    '/channels' => ['Channels', 'channels'],
    '/messages' => ['Messages', 'messages'],
    '/volunteers' => ['Volunteer operations', 'volunteers'],
    '/volunteer-shifts' => ['Volunteer shifts', 'shift-signup'],
    '/volunteers/profile' => ['Volunteer profile', 'volunteer-profile'],
    '/admin/shifts' => ['Manage shifts', 'shift-admin'],
    '/schedule' => ['Schedule', 'schedule'],
    '/playbills' => ['Digital Playbills', 'playbills'],
    '/forms' => ['Forms & acknowledgments', 'forms'],
    '/admin' => ['Administration', 'admin'],
    '/wordpress' => ['WordPress integration', 'wordpress'],
];

[$pageTitle, $screen] = $routes[$route] ?? ['Not found', 'not-found'];

function url(string $path): string {
    global $basePath;
    return ($basePath ?: '') . $path;
}

function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function icon(string $name): string {
    $icons = ['home'=>'⌂','family'=>'♟','staff'=>'★','channels'=>'#','messages'=>'✉','volunteer'=>'♡','schedule'=>'◷','playbill'=>'▤','forms'=>'✓','admin'=>'⚙','alert'=>'!','arrow'=>'→','lock'=>'●'];
    return $icons[$name] ?? '•';
}
function navItem(string $href, string $label, string $ico, string $route): string {
    $active = ($route === $href || ($href !== '/app' && str_starts_with($route, $href))) ? ' active' : '';
    return '<a class="nav-item'.$active.'" href="'.url($href).'"><span>'.icon($ico).'</span><span>'.esc($label).'</span></a>';
}
function metric(string $value, string $label, string $note=''): string {
    return '<article class="metric-card"><strong>'.$value.'</strong><span>'.$label.'</span>'.($note ? '<small>'.$note.'</small>' : '').'</article>';
}
function statusPill(string $status): string {
    $slug = strtolower(str_replace([' ','/'], '-', $status));
    return '<span class="pill '.$slug.'">'.esc($status).'</span>';
}

$public = $screen === 'landing';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#a6192e">
    <title><?= esc($pageTitle) ?> · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
</head>
<body class="<?= $public ? 'public-body' : 'app-body' ?>">
<?php if ($public): ?>
    <header class="public-header"><a class="brand" href="<?= url('/') ?>"><span class="brand-mark">C</span><span><b>CTSMD</b><small>CONNECT</small></span></a><nav><a href="#features">What it does</a><a href="#safety">Safety</a><a href="<?= url('/app') ?>" class="button button-small">View prototype</a></nav></header>
<?php else: ?>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand inverse" href="<?= url('/app') ?>"><span class="brand-mark">C</span><span><b>CTSMD</b><small>CONNECT</small></span></a>
        <div class="nav-label">Community</div>
        <?= navItem('/app','Today','home',$route) ?>
        <?= navItem('/parent','My Family','family',$route) ?>
        <?= navItem('/channels','Channels','channels',$route) ?>
        <?= navItem('/messages','Messages','messages',$route) ?>
        <?= navItem('/schedule','Schedule','schedule',$route) ?>
        <?= navItem('/volunteer-shifts','Volunteer','volunteer',$route) ?>
        <?= navItem('/playbills','Playbills','playbill',$route) ?>
        <?= navItem('/forms','Forms','forms',$route) ?>
        <div class="nav-label">Staff & operations</div>
        <?= navItem('/staff','Staff overview','staff',$route) ?>
        <?= navItem('/volunteers','Volunteer ops','volunteer',$route) ?>
        <?= navItem('/admin','Administration','admin',$route) ?>
        <div class="sidebar-footer"><span class="avatar">JC</span><span><b>Jamie Carter</b><small>Parent + Volunteer</small></span></div>
    </aside>
    <main class="main-stage">
        <header class="app-header"><button class="mobile-menu" aria-label="Open navigation">☰</button><div><span class="eyebrow">Children’s Theatre of Southern Maryland</span><h1><?= esc($pageTitle) ?></h1></div><div class="header-actions"><button class="icon-button">⌕</button><button class="icon-button notification">♢<i>3</i></button><span class="avatar">JC</span></div></header>
        <div class="mobile-nav"><a href="<?= url('/app') ?>">Today</a><a href="<?= url('/channels') ?>">Channels</a><a href="<?= url('/messages') ?>">Messages</a><a href="<?= url('/schedule') ?>">Schedule</a><a href="<?= url('/volunteer-shifts') ?>">Volunteer</a></div>
        <section class="page-wrap">
<?php endif; ?>

<?php if ($screen === 'landing'): ?>
<section class="hero">
    <div class="spotlight spotlight-one"></div><div class="spotlight spotlight-two"></div>
    <div class="hero-copy"><span class="kicker">The whole theatre. One safer place.</span><h1>A smarter backstage for the <em>entire</em> CTSMD community.</h1><p>Schedules, announcements, safeguarded conversations, volunteer coordination, forms, Playbills, and the hundred little things it takes to put on a show.</p><div class="hero-actions"><a class="button" href="<?= url('/app') ?>">Explore Build 001 <?= icon('arrow') ?></a><a class="text-link" href="#safety">See how safety works</a></div><div class="trust-row"><span>Invitation-only</span><span>Guardian-aware messaging</span><span>Built for real theatre days</span></div></div>
    <div class="hero-card"><div class="mini-header"><span class="mini-dot"></span><span>Tonight at CTSMD</span><small>THU 06</small></div><div class="call-card"><span class="time-box">5:30<small>PM</small></span><div><b>Full Cast Rehearsal</b><small>Main Stage · Call 5:15 PM</small></div><span class="pill current">TODAY</span></div><div class="notice-card"><span>!</span><div><b>Schedule changed</b><small>Thursday rehearsal begins 30 minutes earlier.</small></div></div><div class="mini-grid"><div><strong>2</strong><span>forms due</span></div><div><strong>1</strong><span>open shift</span></div><div><strong>4</strong><span>new posts</span></div></div></div>
</section>
<section id="features" class="section"><div class="section-heading"><span class="kicker dark">Designed around the show</span><h2>Less chasing. More creating.</h2><p>Not another generic social feed. Connect is organized around families, productions, rehearsals, and the work that actually keeps CTSMD moving.</p></div><div class="feature-grid"><article><span>#</span><h3>Community channels</h3><p>Important updates stay findable without turning every conversation into noise.</p></article><article><span>◷</span><h3>One schedule</h3><p>Call times, rehearsals, performances, reminders, and family-facing notes in one place.</p></article><article><span>♡</span><h3>Volunteer ready</h3><p>Match people to shifts while respecting background checks and required training.</p></article><article><span>▤</span><h3>Production hub</h3><p>Forms, Playbills, resources, cast information, and future production tools can grow here.</p></article></div></section>
<section id="safety" class="safety-section"><div><span class="kicker">Safety by design</span><h2>A student never has to navigate a private adult conversation alone.</h2><p>When a conversation includes a student and an adult, approved guardians are part of that conversation too. In the production version, the server will enforce that rule rather than trusting a checkbox in the interface.</p></div><div class="safety-demo"><span class="participant staff">MS</span><span class="line"></span><span class="participant student">EC</span><span class="line"></span><span class="participant parent">JC</span><strong>Staff + Student + Guardian</strong><small>Guardian visibility is required</small></div></section>
<section class="cta-section"><span class="kicker dark">Build 001</span><h2>See the product before we wire the machinery.</h2><p>This first build is intentionally a clickable visual prototype with realistic theatre data.</p><a class="button" href="<?= url('/app') ?>">Enter CTSMD Connect <?= icon('arrow') ?></a></section>
<footer class="public-footer"><a class="brand inverse" href="<?= url('/') ?>"><span class="brand-mark">C</span><span><b>CTSMD</b><small>CONNECT</small></span></a><p>Children’s Theatre of Southern Maryland · Visual prototype</p></footer>

<?php elseif (in_array($screen, ['dashboard','parent'], true)): ?>
<div class="welcome-row"><div><span class="kicker dark">Thursday, August 6</span><h2>Good evening, Jamie.</h2><p>Here’s what your family needs to know today.</p></div><div class="action-cluster"><button class="button secondary">Report absence</button><button class="button">Contact staff</button></div></div>
<div class="alert-banner"><span class="alert-icon">!</span><div><b>Call time changed for Thursday rehearsal</b><p>Full cast now begins at 5:30 PM. Family arrival is 5:15 PM.</p></div><a href="<?= url('/schedule') ?>">View update <?= icon('arrow') ?></a></div>
<div class="dashboard-grid"><section class="panel span-2"><div class="panel-head"><div><span class="eyebrow">Up next</span><h3>Today’s schedule</h3></div><a href="<?= url('/schedule') ?>">Full schedule</a></div><div class="timeline"><?php foreach ($data['schedule'] as $item): ?><div class="timeline-item"><div class="timeline-time"><?= esc($item['time']) ?></div><div><b><?= esc($item['title']) ?></b><small><?= esc($item['detail']) ?></small></div><?= statusPill($item['tag']) ?></div><?php endforeach; ?></div></section><section class="panel"><div class="panel-head"><div><span class="eyebrow">Action needed</span><h3>Forms</h3></div><span class="count-badge">2</span></div><div class="stack-list"><?php foreach (array_slice($data['forms'],1,2) as $form): ?><div><span><b><?= esc($form['title']) ?></b><small>Due <?= esc($form['due']) ?></small></span><?= statusPill($form['status']) ?></div><?php endforeach; ?></div><a class="panel-link" href="<?= url('/forms') ?>">Review all forms <?= icon('arrow') ?></a></section><section class="panel span-2"><div class="panel-head"><div><span class="eyebrow">Community</span><h3>Important announcements</h3></div><a href="<?= url('/channels') ?>">Open channels</a></div><div class="announcement-list"><?php foreach ($data['announcements'] as $post): ?><article><span class="post-marker <?= esc($post['tone']) ?>"></span><div><b><?= esc($post['title']) ?></b><small><?= esc($post['meta']) ?></small><p><?= esc($post['body']) ?></p></div></article><?php endforeach; ?></div></section><section class="panel volunteer-card"><span class="eyebrow">Volunteer</span><h3>You're cleared to help.</h3><p>3 open shifts match your current eligibility.</p><a class="button full" href="<?= url('/volunteer-shifts') ?>">Find a shift</a></section></div>

<?php elseif ($screen === 'staff'): ?>
<div class="welcome-row"><div><span class="kicker dark">Operations view</span><h2>Tonight’s production pulse</h2><p>Urgent items first, then the work that needs staff attention.</p></div><div class="action-cluster"><button class="button secondary">Post announcement</button><button class="button">Create schedule item</button></div></div>
<div class="metric-grid"><?= metric('3','Volunteer gaps','2 critical tonight') ?><?= metric('6','Background checks','pending review') ?><?= metric('4','Safeguarded threads','unread') ?><?= metric('2','Reported items','need review') ?></div>
<div class="dashboard-grid"><section class="panel span-2"><div class="panel-head"><div><span class="eyebrow">Today</span><h3>Production run of show</h3></div><a href="<?= url('/schedule') ?>">Schedule tools</a></div><div class="timeline"><?php foreach ($data['schedule'] as $item): ?><div class="timeline-item"><div class="timeline-time"><?= esc($item['time']) ?></div><div><b><?= esc($item['title']) ?></b><small><?= esc($item['detail']) ?></small></div><?= statusPill($item['tag']) ?></div><?php endforeach; ?></div></section><section class="panel"><div class="panel-head"><div><span class="eyebrow">Attention</span><h3>Staff queue</h3></div><span class="count-badge">7</span></div><div class="task-list"><div><span class="task-dot red"></span><span><b>Dressing room shift uncovered</b><small>Saturday matinee</small></span></div><div><span class="task-dot gold"></span><span><b>3 training records expire soon</b><small>Volunteer compliance</small></span></div><div><span class="task-dot"></span><span><b>Family absence report</b><small>Emma Carter · Friday</small></span></div></div></section><section class="panel span-3"><div class="panel-head"><div><span class="eyebrow">Changes</span><h3>Recent operational activity</h3></div><button class="text-button">View audit log</button></div><div class="activity-row"><span>Schedule</span><b>Thursday full cast call moved to 5:30 PM</b><small>12 min ago · Maya</small></div><div class="activity-row"><span>Volunteer</span><b>Background check approved for Taylor Brooks</b><small>31 min ago · Jordan</small></div><div class="activity-row"><span>Messaging</span><b>Safeguarded conversation reported for review</b><small>1 hr ago · System</small></div></section></div>

<?php elseif ($screen === 'channels'): ?>
<div class="workspace"><aside class="workspace-rail"><div class="rail-head"><span class="eyebrow">Community</span><h3>Channels</h3><button>＋</button></div><div class="channel-search">⌕ Find a channel</div><?php foreach ($data['channels'] as $i=>$channel): ?><a class="channel-link <?= $i===3?'selected':'' ?>" href="#"><span>#</span><?= esc($channel) ?><?= in_array($i,[0,3,7],true)?'<i>'.($i===3?'4':'').'</i>':'' ?></a><?php endforeach; ?></aside><section class="conversation-stage"><div class="conversation-head"><div><span class="channel-hash">#</span><div><h3>Current Production</h3><small>Cast, family, and production updates for Matilda Jr.</small></div></div><div><button class="icon-button">⌕</button><button class="icon-button">•••</button></div></div><div class="pinned-note"><span>★</span><div><b>Pinned for this week</b><small>Tech week call sheet · Costume checklist · Parking map</small></div><button>View 3 pins</button></div><div class="post-stream"><?php foreach ($data['channel_posts'] as $post): ?><article class="channel-post"><span class="post-avatar"><?= strtoupper(substr($post['author'],0,1)) ?></span><div class="post-body"><div><b><?= esc($post['author']) ?></b><small><?= esc($post['time']) ?></small><?= $post['pinned']?'<span class="tiny-pill">PINNED</span>':'' ?></div><p><?= esc($post['text']) ?></p><button class="reaction-row"><?= esc($post['reactions']) ?></button></div></article><?php endforeach; ?></div><div class="composer"><button>＋</button><span>Write in #current-production</span><button>☺</button><button>➤</button></div></section></div>

<?php elseif ($screen === 'messages'): ?>
<div class="message-layout"><aside class="message-list"><div class="rail-head"><span><span class="eyebrow">Inbox</span><h3>Messages</h3></span><button>＋</button></div><div class="message-search">⌕ Search messages</div><article class="message-preview active"><span class="post-avatar">MR</span><div><b>Maya Rivera</b><small>Guardian-visible conversation</small><p>Thanks, Jamie. I’ll make sure Emma...</p></div><time>4:22</time></article><article class="message-preview"><span class="post-avatar">JL</span><div><b>Jordan Lee</b><small>Direct message</small><p>Can you help with the lobby table?</p></div><time>Tue</time></article></aside><section class="message-thread"><div class="conversation-head"><div><span class="post-avatar">MR</span><div><h3>Emma Carter · Rehearsal question</h3><small>3 participants</small></div></div><button class="icon-button">ⓘ</button></div><div class="safety-banner"><span>✓</span><div><b>Safeguarded conversation</b><p>This conversation includes the student’s approved guardian(s). Required guardians cannot be removed while the student remains in the conversation.</p></div></div><div class="participant-strip"><span>Participants</span><div><i class="participant staff">MR</i><b>Maya Rivera</b><small>Staff</small></div><div><i class="participant student">EC</i><b>Emma Carter</b><small>Student</small></div><div><i class="participant parent">JC</i><b>Jamie Carter</b><small>Guardian</small></div></div><div class="thread-stream"><div class="thread-message"><span class="post-avatar">EC</span><div><b>Emma Carter <small>3:54 PM</small></b><p>Hi Ms. Maya, I’m not sure which shoes I need for Saturday’s costume fitting.</p></div></div><div class="thread-message"><span class="post-avatar">MR</span><div><b>Maya Rivera <small>4:02 PM</small></b><p>Bring both your black jazz shoes and character shoes, please. We’ll check both with the costume.</p></div></div><div class="thread-message mine"><span class="post-avatar">JC</span><div><b>Jamie Carter <small>4:22 PM</small></b><p>Thanks! We’ll make sure she has both.</p></div></div></div><div class="composer"><button>＋</button><span>Message all participants</span><button>☺</button><button>➤</button></div></section></div>

<?php elseif ($screen === 'volunteers'): ?>
<div class="welcome-row"><div><span class="kicker dark">Volunteer operations</span><h2>People ready to make the show happen.</h2><p>Compliance, coverage, and hours at a glance.</p></div><div class="action-cluster"><a class="button secondary" href="<?= url('/volunteers/profile') ?>">View volunteer</a><a class="button" href="<?= url('/admin/shifts') ?>">Create shift</a></div></div><div class="metric-grid"><?php foreach ($data['volunteer_stats'] as $stat) echo metric($stat['value'],$stat['label'],$stat['note']); ?></div><div class="dashboard-grid"><section class="panel span-2"><div class="panel-head"><div><span class="eyebrow">Coverage</span><h3>Upcoming volunteer gaps</h3></div><a href="<?= url('/admin/shifts') ?>">Manage all shifts</a></div><div class="data-list"><div class="data-row"><span><b>Dressing Room Monitor</b><small>Sat · 1:00 PM · Matilda Jr.</small></span><strong class="danger-text">1 needed</strong><button class="button ghost">Find volunteer</button></div><div class="data-row"><span><b>Front of House</b><small>Fri · 5:30 PM · Main Stage</small></span><strong>2 needed</strong><button class="button ghost">View signups</button></div><div class="data-row"><span><b>Strike Crew</b><small>Sun · 5:00 PM · Main Stage</small></span><strong>4 needed</strong><button class="button ghost">View signups</button></div></div></section><section class="panel"><div class="panel-head"><div><span class="eyebrow">Compliance</span><h3>Needs attention</h3></div><span class="count-badge">15</span></div><div class="progress-item"><span><b>Background checks</b><small>6 pending review</small></span><i style="--progress:68%"></i></div><div class="progress-item"><span><b>Child safety training</b><small>5 incomplete</small></span><i style="--progress:82%"></i></div><div class="progress-item"><span><b>Facility education</b><small>4 due soon</small></span><i style="--progress:74%"></i></div></section><section class="panel span-3"><div class="panel-head"><div><span class="eyebrow">Recent volunteers</span><h3>Readiness roster</h3></div><button class="text-button">Export preview</button></div><div class="roster-grid"><div><span class="post-avatar">TB</span><span><b>Taylor Brooks</b><small>FOH · Backstage</small></span><?= statusPill('Ready') ?></div><div><span class="post-avatar">KS</span><span><b>Kim Sanders</b><small>Concessions · Check-in</small></span><?= statusPill('Training incomplete') ?></div><div><span class="post-avatar">DP</span><span><b>Devon Price</b><small>Set build · Strike</small></span><?= statusPill('Ready') ?></div></div></section></div>

<?php elseif ($screen === 'shift-signup'): ?>
<div class="welcome-row"><div><span class="kicker dark">Give a little time. Make a big show.</span><h2>Volunteer shifts</h2><p>We’ll only show signup actions when your requirements are met.</p></div><button class="button secondary">My shifts · 2</button></div><div class="filter-bar"><button class="active">All shifts</button><button>This week</button><button>Front of house</button><button>Backstage</button><button>Build & strike</button></div><div class="shift-grid"><?php foreach ($data['shifts'] as $shift): ?><article class="shift-card <?= esc($shift['status']) ?>"><div class="shift-top"><span class="shift-category">VOLUNTEER SHIFT</span><?= statusPill($shift['status']==='eligible'?'Eligible':'Requirements needed') ?></div><h3><?= esc($shift['title']) ?></h3><div class="shift-details"><span>◷ <b><?= esc($shift['when']) ?></b></span><span>⌖ <?= esc($shift['location']) ?></span><span>♟ <?= esc($shift['slots']) ?></span></div><div class="requirement-box"><small>Requirements</small><b><?= esc($shift['requirements']) ?></b></div><?php if($shift['status']==='eligible'): ?><button class="button full">Sign up</button><?php else: ?><button class="button full locked" disabled>● Complete requirements first</button><a href="<?= url('/volunteers/profile') ?>">See what’s missing</a><?php endif; ?></article><?php endforeach; ?></div>

<?php elseif ($screen === 'volunteer-profile'): ?>
<div class="profile-hero"><div class="profile-id"><span class="large-avatar">TB</span><div><span class="eyebrow">Volunteer profile</span><h2>Taylor Brooks</h2><p>Parent · Volunteer · Active since 2024</p></div></div><div class="action-cluster"><button class="button secondary">Add note</button><button class="button">Edit volunteer</button></div></div><div class="profile-grid"><section class="panel profile-summary"><h3>Contact & interests</h3><dl><dt>Email</dt><dd>taylor@example.test</dd><dt>Phone</dt><dd>(301) 555-0148</dd><dt>Interests</dt><dd>Front of House, Backstage, Set Build</dd><dt>Current production</dt><dd>Matilda Jr.</dd><dt>Volunteer hours</dt><dd><strong>28.5</strong> this season</dd></dl></section><section class="panel span-2"><div class="panel-head"><div><span class="eyebrow">Eligibility</span><h3>Compliance & training</h3></div><?= statusPill('Ready') ?></div><div class="credential-grid"><article><span class="check">✓</span><div><b>Background check</b><small>Approved · Expires May 18, 2027</small></div><button>View</button></article><article><span class="check">✓</span><div><b>Child safety training</b><small>Completed Jul 12, 2026</small></div><button>View</button></article><article><span class="check">✓</span><div><b>Facility orientation</b><small>Completed Jun 24, 2026</small></div><button>View</button></article><article><span class="check">✓</span><div><b>Code of conduct</b><small>Signed Jun 20, 2026</small></div><button>View</button></article></div></section><section class="panel span-3"><div class="panel-head"><div><span class="eyebrow">Access</span><h3>Eligible shift categories</h3></div><button class="text-button">Manage rules</button></div><div class="tag-cloud"><span>Front of House</span><span>Concessions</span><span>Dressing Room Monitor</span><span>Backstage Support</span><span>Set Build</span><span>Load-in / Strike</span></div></section></div>

<?php elseif ($screen === 'shift-admin'): ?>
<div class="welcome-row"><div><span class="kicker dark">Staff tools</span><h2>Create & manage shifts</h2><p>Design the work, requirements, and coverage before publishing it to volunteers.</p></div><button class="button">＋ New shift</button></div><div class="admin-split"><section class="panel form-preview"><div class="panel-head"><div><span class="eyebrow">Shift editor</span><h3>Dressing Room Monitor</h3></div><?= statusPill('Draft') ?></div><div class="field-grid"><label>Shift title<input value="Dressing Room Monitor" readonly></label><label>Production / event<input value="Matilda Jr." readonly></label><label>Date<input value="Saturday, Aug 15" readonly></label><label>Time<input value="1:00 PM – 5:00 PM" readonly></label><label>Location<input value="Backstage · Dressing Rooms" readonly></label><label>Volunteers needed<input value="2" readonly></label></div><div class="requirements-editor"><small>Required credentials / training</small><div class="tag-cloud"><span>✓ Approved background check</span><span>✓ Child safety training</span><button>＋ Add requirement</button></div></div><div class="toggle-row"><span><b>Approval required</b><small>Coordinator approves each signup.</small></span><i class="toggle on"></i></div><div class="toggle-row"><span><b>Waitlist</b><small>Allow volunteers to join when full.</small></span><i class="toggle"></i></div><div class="action-cluster left"><button class="button secondary">Save draft</button><button class="button">Publish shift</button></div></section><section class="panel signup-preview"><span class="eyebrow">Current coverage</span><h3>1 of 2 filled</h3><div class="coverage-bar"><i></i></div><div class="signup-person"><span class="post-avatar">TB</span><span><b>Taylor Brooks</b><small>Eligible · Approved</small></span><?= statusPill('Confirmed') ?></div><button class="button ghost full">＋ Add volunteer override</button><div class="admin-note"><b>Admin override</b><p>Overrides will require a reason and will be written to the audit log when backend logic is added.</p></div></section></div>

<?php elseif ($screen === 'schedule'): ?>
<div class="welcome-row"><div><span class="kicker dark">August 2026</span><h2>Production schedule</h2><p>Rehearsals, performances, meetings, calls, and the notes each audience needs.</p></div><div class="action-cluster"><button class="button secondary">Month</button><button class="button">＋ Add event</button></div></div><div class="week-strip"><?php foreach (['MON 3','TUE 4','WED 5','THU 6','FRI 7','SAT 8','SUN 9'] as $day): ?><button class="<?= str_contains($day,'THU')?'active':'' ?>"><?= str_replace(' ','<b>',$day).'</b>' ?></button><?php endforeach; ?></div><div class="schedule-day"><div class="date-column"><strong>06</strong><span>THURSDAY</span><small>AUGUST</small></div><div class="day-events"><article class="event-card rehearsal"><span class="event-time">5:30 PM</span><div><span class="event-type">REHEARSAL</span><h3>Full Cast Rehearsal</h3><p>Main Stage · Call 5:15 PM</p><div class="note-pair"><span><small>Family note</small>Bring water, script, and both show shoes.</span><span><small>Staff note</small>Act II spacing first. Costume team arrives 5:00.</span></div></div><button>•••</button></article><article class="event-card meeting"><span class="event-time">6:45 PM</span><div><span class="event-type">VOLUNTEER</span><h3>Parent Volunteer Orientation</h3><p>Studio B · 30 minutes</p></div><button>•••</button></article></div></div>

<?php elseif ($screen === 'playbills'): ?>
<div class="welcome-row"><div><span class="kicker dark">The show lives on</span><h2>Digital Playbills</h2><p>Current and archived productions, ready to view on any device.</p></div><button class="button">Manage Playbills</button></div><div class="playbill-grid"><?php foreach ($data['playbills'] as $i=>$playbill): ?><article class="playbill-card"><div class="playbill-cover cover-<?= $i+1 ?>"><span>CTSMD PRESENTS</span><strong><?= esc($playbill['title']) ?></strong><small><?= esc($playbill['season']) ?></small></div><div class="playbill-info"><div><h3><?= esc($playbill['title']) ?></h3><p><?= esc($playbill['season']) ?></p></div><?= statusPill($playbill['status']) ?><div class="playbill-actions"><button class="button full">View Playbill</button><button class="button secondary full">Download PDF</button></div></div></article><?php endforeach; ?><article class="playbill-card add-card"><span>＋</span><h3>Future Playbill builder</h3><p>Cast, crew, sponsors, ads, sections, and shareable links will live here.</p></article></div>

<?php elseif ($screen === 'forms'): ?>
<div class="welcome-row"><div><span class="kicker dark">Paperwork, minus the paper chase</span><h2>Forms & acknowledgments</h2><p>One place to see what’s complete, missing, or needs review.</p></div><button class="button secondary">History</button></div><div class="forms-list"><?php foreach ($data['forms'] as $form): ?><article><span class="document-icon">▤</span><div><h3><?= esc($form['title']) ?></h3><p>Assigned to Jamie Carter · Due <?= esc($form['due']) ?></p></div><?= statusPill($form['status']) ?><button class="button ghost"><?= $form['status']==='Completed'?'View':'Open' ?></button></article><?php endforeach; ?></div><section class="future-strip"><div><span class="eyebrow">Planned form types</span><h3>Built to grow with CTSMD</h3></div><div class="tag-cloud"><span>Code of conduct</span><span>Medical / emergency</span><span>Media release</span><span>Parent handbook</span><span>Child safety</span><span>Production agreements</span></div></section>

<?php elseif ($screen === 'admin'): ?>
<div class="welcome-row"><div><span class="kicker dark">Administration</span><h2>Run the organization without losing the theatre.</h2><p>Desktop-first tools for the detailed work, with mobile fallbacks when you’re away from the desk.</p></div><button class="button">Quick action ＋</button></div><div class="admin-grid"><?php $adminItems=[['People','Members, profiles, status, imports','128','family'],['Families','Guardian relationships and students','64','family'],['Roles','Permissions and multi-role assignments','10','admin'],['Productions','Groups, casts, crews, classes','4','playbill'],['Channels','Visibility, membership, posting rules','9','channels'],['Announcements','Publish organization and group updates','12','alert'],['Volunteer Requirements','Eligibility rules and credentials','7','volunteer'],['Training Records','Completion and expiration tracking','42','forms'],['Background Checks','Review status and expiration dates','6','staff'],['Forms','Assignments, acknowledgments, reviews','8','forms'],['Schedules','Rehearsals, calls, events, absences','23','schedule'],['Playbills','Current and archived productions','3','playbill'],['Audit Log','Sensitive actions and future overrides','—','admin'],['Reports','Board, participation, volunteer coverage','—','staff'],['Settings','Organization and platform controls','—','admin']]; foreach($adminItems as $item): ?><article class="admin-card"><span class="admin-icon"><?= icon($item[3]) ?></span><div><h3><?= esc($item[0]) ?></h3><p><?= esc($item[1]) ?></p></div><strong><?= esc($item[2]) ?></strong><span class="arrow">→</span></article><?php endforeach; ?></div><a class="integration-card" href="<?= url('/wordpress') ?>"><div><span class="eyebrow">Architecture concept</span><h3>How WordPress can fit without owning Connect</h3><p>See the proposed boundary between public CMS content and safety-sensitive platform logic.</p></div><span>Explore concept →</span></a>

<?php elseif ($screen === 'wordpress'): ?>
<div class="welcome-row"><div><span class="kicker dark">Future integration</span><h2>WordPress can be the marquee, not the backstage rules engine.</h2><p>Public content can live in WordPress while Connect keeps ownership of family relationships, messaging safeguards, volunteer eligibility, and audit-sensitive behavior.</p></div><a class="button secondary" href="<?= url('/admin') ?>">Back to admin</a></div><div class="architecture-map"><section><span class="system-label public">PUBLIC WEBSITE</span><h3>WordPress</h3><ul><li>Public pages & navigation</li><li>Show and class content</li><li>News and announcements</li><li>Search / SEO content</li><li>Optional Connect admin shell</li></ul></section><div class="bridge"><span>API / PLUGIN BRIDGE</span><i>↔</i><small>Explicit contracts, portable boundaries</small></div><section><span class="system-label connect">PRIVATE PLATFORM</span><h3>CTSMD Connect</h3><ul><li>Guardian relationships</li><li>Safeguarded messaging</li><li>Roles & authorization</li><li>Volunteer eligibility</li><li>Audit logs & app data</li></ul></section></div><div class="principle-panel"><span>CORE PRINCIPLE</span><h3>WordPress may expose controls. Connect owns the rules.</h3><p>If CTSMD later changes CMS or hosting strategy, the safety-sensitive platform should remain portable rather than being trapped inside WordPress-specific data structures.</p></div>

<?php else: ?>
<div class="empty-state"><span>🎭</span><h2>That scene isn’t in this build.</h2><p>The route you requested isn’t part of Build 001.</p><a class="button" href="<?= url('/app') ?>">Back to the dashboard</a></div>
<?php endif; ?>

<?php if (!$public): ?>
        </section>
    </main>
</div>
<?php endif; ?>
<script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
