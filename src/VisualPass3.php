<?php

declare(strict_types=1);

final class VisualPass3
{
    private const ROUTES = [
        '/pass3',
        '/family/action',
        '/communication/thread',
        '/volunteer/shift-preview',
        '/production/edit',
        '/people/edit',
        '/safeguarding/case',
        '/playbill-preview',
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
        $production = $data['playbills'][0]['title'] ?? 'Current production';
        $openForms = array_values(array_filter($data['forms'], static fn(array $form): bool => $form['status'] !== 'Completed'));
        $lockedShifts = array_values(array_filter($data['shifts'], static fn(array $shift): bool => $shift['status'] === 'locked'));
        $eligibleShifts = array_values(array_filter($data['shifts'], static fn(array $shift): bool => $shift['status'] === 'eligible'));
        $conversations = $data['conversation_overview'];
        $people = $data['people'];
        $firstPerson = $people[0] ?? ['name' => $user['name'], 'initials' => $user['initials'], 'role' => $user['role'], 'status' => 'Active', 'context' => 'Organization member'];
        $reviewQueue = $data['safeguarding']['queue'] ?? [];

        $titles = [
            '/pass3' => ['Visual Pass 3', 'Decision & action flows'],
            '/family/action' => ['Family', 'Action center'],
            '/communication/thread' => ['Communication', 'Protected thread'],
            '/volunteer/shift-preview' => ['Volunteer', 'Shift decision'],
            '/production/edit' => ['Production', 'Schedule change'],
            '/people/edit' => ['Administration', 'Edit person'],
            '/safeguarding/case' => ['Safeguarding', 'Case review'],
            '/playbill-preview' => ['Production', 'Playbill preview'],
        ];
        [$eyebrow, $title] = $titles[$route];

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
    <link rel="stylesheet" href="<?= $url('/assets/css/visual-pass-3.css') ?>">
</head>
<body class="app-body vp3-body">
<div class="vp3-shell">
    <aside class="vp3-sidebar">
        <a class="vp3-brand" href="<?= $url('/pass3') ?>"><span>C</span><b>CTSMD <small>CONNECT</small></b></a>
        <nav>
            <a href="<?= $url('/pass3') ?>" <?= $route === '/pass3' ? 'class="active"' : '' ?>>Pass 3 review</a>
            <span>Member</span>
            <a href="<?= $url('/family/action') ?>" <?= $route === '/family/action' ? 'class="active"' : '' ?>>Action center</a>
            <a href="<?= $url('/communication/thread') ?>" <?= $route === '/communication/thread' ? 'class="active"' : '' ?>>Protected thread</a>
            <a href="<?= $url('/volunteer/shift-preview') ?>" <?= $route === '/volunteer/shift-preview' ? 'class="active"' : '' ?>>Shift decision</a>
            <span>Operations</span>
            <a href="<?= $url('/production/edit') ?>" <?= $route === '/production/edit' ? 'class="active"' : '' ?>>Schedule change</a>
            <a href="<?= $url('/people/edit') ?>" <?= $route === '/people/edit' ? 'class="active"' : '' ?>>Edit person</a>
            <a href="<?= $url('/safeguarding/case') ?>" <?= $route === '/safeguarding/case' ? 'class="active"' : '' ?>>Case review</a>
            <a href="<?= $url('/playbill-preview') ?>" <?= $route === '/playbill-preview' ? 'class="active"' : '' ?>>Playbill preview</a>
            <span>Earlier passes</span>
            <a href="<?= $url('/prototype') ?>">Visual Pass 2</a>
            <a href="<?= $url('/app') ?>">Main app</a>
        </nav>
        <div class="vp3-user"><i><?= $esc($user['initials']) ?></i><span><b><?= $esc($user['name']) ?></b><small><?= $esc($user['role']) ?></small></span></div>
    </aside>

    <main class="vp3-main">
        <header class="vp3-header"><div><small><?= $esc($eyebrow) ?></small><h1><?= $esc($title) ?></h1></div><div class="vp3-header-actions"><a href="<?= $url('/notifications') ?>">Notifications</a><a class="button button-small" href="<?= $url('/pass3') ?>">Review hub</a></div></header>
        <div class="vp3-page">

        <?php if ($route === '/pass3'): ?>
            <section class="vp3-stage"><span>VISUAL PASS 3</span><h2>Now test the moments where someone has to decide, edit, approve, or act.</h2><p>These screens add realistic friction without committing us to backend workflows yet. The goal is to expose confusing decisions before persistence and permissions make them expensive.</p></section>
            <div class="vp3-card-grid">
                <a href="<?= $url('/family/action') ?>"><small>FAMILY</small><h3>Action center</h3><p>Forms, schedule changes and volunteer opportunities prioritized by urgency.</p><b>Review →</b></a>
                <a href="<?= $url('/communication/thread') ?>"><small>COMMUNICATION</small><h3>Protected thread</h3><p>Participant visibility, safety explanation and a real composer shape.</p><b>Review →</b></a>
                <a href="<?= $url('/volunteer/shift-preview') ?>"><small>VOLUNTEER</small><h3>Shift decision</h3><p>Eligibility, conflicts, requirements and confirmation before signup.</p><b>Review →</b></a>
                <a href="<?= $url('/production/edit') ?>"><small>PRODUCTION</small><h3>Schedule change</h3><p>Staff edit flow with audience impact and notification preview.</p><b>Review →</b></a>
                <a href="<?= $url('/people/edit') ?>"><small>ADMIN</small><h3>Edit person</h3><p>Role, family context and restricted settings without a giant user form.</p><b>Review →</b></a>
                <a href="<?= $url('/safeguarding/case') ?>"><small>SAFEGUARDING</small><h3>Case review</h3><p>Evidence, disposition and audit language with restrained visual tone.</p><b>Review →</b></a>
                <a href="<?= $url('/playbill-preview') ?>"><small>THEATRE IDENTITY</small><h3>Playbill preview</h3><p>A more theatrical public-facing moment to keep the product from becoming generic ops software.</p><b>Review →</b></a>
            </div>
            <section class="vp3-criteria"><div><small>PASS 3 QUESTIONS</small><h3>Would a real person know what happens next?</h3></div><div><span>Primary action is visually unmistakable</span><span>Consequences appear before confirmation</span><span>Locked states explain a remedy</span><span>Safety language stays calm and structural</span><span>Admin tools remain denser than member tools</span><span>Theatre personality still shows through</span></div></section>

        <?php elseif ($route === '/family/action'): ?>
            <section class="vp3-stage compact"><span>MY FAMILY</span><h2>Three things need your attention.</h2><p>The member experience should rank action by consequence, not by which module created it.</p></section>
            <div class="vp3-action-layout">
                <section class="vp3-stack">
                    <?php if ($openForms): $form = $openForms[0]; ?>
                    <article class="vp3-task urgent"><div class="vp3-task-icon">!</div><div><small>FORM · <?= $esc(strtoupper($form['status'])) ?></small><h3><?= $esc($form['title']) ?></h3><p>Due <?= $esc($form['due']) ?>. Complete this before the next production checkpoint.</p></div><a class="button" href="<?= $url('/forms') ?>">Review form</a></article>
                    <?php endif; ?>
                    <?php if ($data['announcements']): $announcement = $data['announcements'][0]; ?>
                    <article class="vp3-task changed"><div class="vp3-task-icon">↻</div><div><small>SCHEDULE CHANGE</small><h3><?= $esc($announcement['title']) ?></h3><p><?= $esc($announcement['body']) ?></p></div><a class="button secondary" href="<?= $url('/schedule') ?>">See schedule</a></article>
                    <?php endif; ?>
                    <?php if ($eligibleShifts): $shift = $eligibleShifts[0]; ?>
                    <article class="vp3-task"><div class="vp3-task-icon">♡</div><div><small>VOLUNTEER OPPORTUNITY</small><h3><?= $esc($shift['title']) ?></h3><p><?= $esc($shift['when']) ?> · <?= $esc($shift['slots']) ?></p></div><a class="button secondary" href="<?= $url('/volunteer/shift-preview') ?>">View shift</a></article>
                    <?php endif; ?>
                </section>
                <aside class="vp3-side-note"><small>WHY THIS VIEW</small><h3>Modules disappear. Priorities remain.</h3><p>Parents should not have to check Forms, Schedule, Channels and Volunteer one by one just to learn what changed.</p><a href="<?= $url('/family-hub') ?>">Back to family hub →</a></aside>
            </div>

        <?php elseif ($route === '/communication/thread'): ?>
            <?php $conversation = $conversations[0] ?? ['subject' => 'Protected conversation', 'type' => 'safeguarded', 'participants' => 3, 'messages' => 0, 'latest' => 'No messages']; ?>
            <section class="vp3-thread-shell">
                <header><div><small><?= $esc(strtoupper($conversation['type'])) ?> CONVERSATION</small><h2><?= $esc($conversation['subject']) ?></h2><p><?= (int)$conversation['participants'] ?> participants · <?= (int)$conversation['messages'] ?> messages</p></div><button class="vp3-chip" data-dialog-open="participants-dialog">Participants</button></header>
                <div class="vp3-thread-safety"><b>Guardian visibility active</b><span>CTSMD keeps the approved guardian structurally included when a student and adult are communicating.</span><button data-dialog-open="safety-dialog">How this works</button></div>
                <div class="vp3-thread-body">
                    <div class="vp3-message staff"><span>STAFF</span><p>Thanks for checking in. Please bring both options so we can confirm the costume fit at rehearsal.</p><small><?= $esc($conversation['latest']) ?></small></div>
                    <div class="vp3-message guardian"><span>GUARDIAN</span><p>Got it. We’ll make sure everything comes with us.</p><small>Guardian-visible example state</small></div>
                </div>
                <footer class="vp3-thread-composer"><textarea rows="2" placeholder="Write a message…"></textarea><button class="button" data-preview-action="Message previewed — no message was sent.">Send</button><small>All protected participants will retain visibility.</small></footer>
            </section>

        <?php elseif ($route === '/volunteer/shift-preview'): ?>
            <?php $shift = $lockedShifts[0] ?? ($eligibleShifts[0] ?? null); ?>
            <section class="vp3-stage compact"><span>VOLUNTEER SHIFT</span><h2><?= $esc($shift['title'] ?? 'Volunteer opportunity') ?></h2><p><?= $esc($shift['when'] ?? 'Schedule pending') ?> · <?= $esc($shift['location'] ?? 'Location pending') ?></p></section>
            <div class="vp3-decision-grid">
                <section class="vp3-panel"><header><small>BEFORE YOU SIGN UP</small><h3>Eligibility check</h3></header><div class="vp3-check <?= ($shift['status'] ?? '') === 'eligible' ? 'good' : 'blocked' ?>"><i><?= ($shift['status'] ?? '') === 'eligible' ? '✓' : '!' ?></i><div><b><?= ($shift['status'] ?? '') === 'eligible' ? 'You are eligible for this shift' : 'This shift is currently locked' ?></b><span><?= $esc($shift['requirements'] ?? 'No special requirements') ?></span></div></div><div class="vp3-detail-list"><span><b>Availability</b><small>No detected signup conflict in the current prototype data.</small></span><span><b>Coverage</b><small><?= $esc($shift['slots'] ?? 'Availability pending') ?></small></span><span><b>Commitment</b><small>Confirm only if you can stay for the full shift window.</small></span></div></section>
                <aside class="vp3-confirm-card"><small>DECISION</small><h3><?= ($shift['status'] ?? '') === 'eligible' ? 'Ready to help?' : 'One step remains.' ?></h3><p><?= ($shift['status'] ?? '') === 'eligible' ? 'Signing up would reserve one volunteer slot and add the shift to your schedule.' : 'Complete the listed requirement and this shift can become available automatically.' ?></p><?php if (($shift['status'] ?? '') === 'eligible'): ?><button class="button full" data-dialog-open="signup-dialog">Sign up for this shift</button><?php else: ?><a class="button full" href="<?= $url('/volunteer-readiness') ?>">Review requirements</a><?php endif; ?><a href="<?= $url('/volunteer-readiness') ?>">Back to readiness</a></aside>
            </div>

        <?php elseif ($route === '/production/edit'): ?>
            <?php $schedule = $data['schedule'][0] ?? ['title' => 'Production activity', 'time' => '', 'detail' => '', 'tag' => '']; ?>
            <section class="vp3-stage compact"><span><?= $esc(strtoupper($production)) ?></span><h2>Change the schedule without surprising anyone.</h2><p>The edit experience previews audience impact before staff save anything.</p></section>
            <div class="vp3-editor-layout">
                <section class="vp3-panel vp3-form-card"><header><small>EDIT SCHEDULE ITEM</small><h3><?= $esc($schedule['title']) ?></h3></header><label>Title<input value="<?= $esc($schedule['title']) ?>"></label><div class="vp3-form-row"><label>Call time<input value="<?= $esc($schedule['time']) ?>"></label><label>Location<input value="<?= $esc(explode(' · ', $schedule['detail'])[0] ?? '') ?>"></label></div><label>Family-facing note<textarea rows="4">Please review the updated arrival details before coming to the theatre.</textarea></label><label class="vp3-toggle"><input type="checkbox" checked> Notify affected families and volunteers</label><div class="vp3-form-actions"><a href="<?= $url('/production/day') ?>">Cancel</a><button class="button" data-dialog-open="schedule-dialog">Review change</button></div></section>
                <aside class="vp3-impact"><small>IMPACT PREVIEW</small><h3>Who will notice this?</h3><div><span><b>Families</b><small>Schedule and notification center</small></span><span><b>Production staff</b><small>Production day and staff overview</small></span><span><b>Volunteers</b><small>Any linked shift timing should be reviewed</small></span></div><p>No database write occurs in this visual pass.</p></aside>
            </div>

        <?php elseif ($route === '/people/edit'): ?>
            <section class="vp3-stage compact"><span>PEOPLE & FAMILIES</span><h2><?= $esc($firstPerson['name']) ?></h2><p>Edit the relationship context first; hide rarely used account mechanics until someone actually needs them.</p></section>
            <div class="vp3-editor-layout">
                <section class="vp3-panel vp3-form-card"><header><small>PROFILE</small><h3>Identity & role</h3></header><div class="vp3-person-hero"><i><?= $esc($firstPerson['initials']) ?></i><div><b><?= $esc($firstPerson['name']) ?></b><small><?= $esc($firstPerson['role']) ?> · <?= $esc($firstPerson['status']) ?></small></div></div><label>Display role<input value="<?= $esc($firstPerson['role']) ?>"></label><label>Current context<input value="<?= $esc($firstPerson['context']) ?>"></label><div class="vp3-form-actions"><a href="<?= $url('/people/detail') ?>">Cancel</a><button class="button" data-preview-action="Person update previewed — nothing was saved.">Save changes</button></div></section>
                <aside class="vp3-panel"><header><small>RELATIONSHIPS & ACCESS</small><h3>What matters around this person</h3></header><div class="vp3-detail-list"><span><b>Family relationships</b><small>Guardian/student links belong here, not buried under permissions.</small></span><span><b>Volunteer readiness</b><small><?= $esc($firstPerson['context']) ?></small></span><span><b>Production access</b><small>Role-based access will be wired after the visual model is approved.</small></span><span class="restricted"><b>Restricted account controls</b><small>Deactivate, merge and audit controls should require elevated access.</small></span></div></aside>
            </div>

        <?php elseif ($route === '/safeguarding/case'): ?>
            <?php $case = $reviewQueue[0] ?? ['severity' => 'Review', 'title' => 'No active exception', 'detail' => 'The current seeded review queue is clear.']; ?>
            <section class="vp3-stage compact safe"><span>RESTRICTED · <?= $esc(strtoupper($case['severity'])) ?></span><h2><?= $esc($case['title']) ?></h2><p><?= $esc($case['detail']) ?></p></section>
            <div class="vp3-case-layout"><section class="vp3-panel"><header><small>EVIDENCE</small><h3>What the reviewer should see</h3></header><div class="vp3-evidence"><article><span>1</span><div><b>System status</b><small><?= $esc($case['detail']) ?></small></div></article><article><span>2</span><div><b>Related context</b><small>Show only relevant conversation, credential or relationship facts.</small></div></article><article><span>3</span><div><b>Audit trail</b><small>Every disposition will eventually record reviewer, time and rationale.</small></div></article></div></section><aside class="vp3-disposition"><small>DISPOSITION</small><h3>Choose a measured next step.</h3><button data-preview-action="Case marked for follow-up in preview only.">Request follow-up</button><button data-preview-action="Case approval previewed — nothing was changed.">Approve / clear</button><button class="danger" data-dialog-open="escalate-dialog">Escalate review</button><p>Destructive or sensitive actions should never be one-click.</p></aside></div>

        <?php elseif ($route === '/playbill-preview'): ?>
            <section class="vp3-playbill-wrap"><article class="vp3-playbill"><header><span>CHILDREN'S THEATRE OF SOUTHERN MARYLAND</span><small>presents</small></header><div class="vp3-playbill-title"><small>CTSMD</small><h2><?= $esc($production) ?></h2><p><?= $esc($data['playbills'][0]['season'] ?? 'Current season') ?></p></div><div class="vp3-curtain"><i></i><b>Tonight, the stage belongs to them.</b><i></i></div><footer><span>CAST</span><span>CREATIVE TEAM</span><span>SPONSORS</span><span>ABOUT CTSMD</span></footer></article><aside><small>VISUAL IDENTITY TEST</small><h3>Not every surface should look like the dashboard.</h3><p>Digital Playbills are a chance for CTSMD Connect to feel celebratory, theatrical and public-facing while still living inside the same product family.</p><a class="button" href="<?= $url('/playbills') ?>">Back to Playbills</a></aside></section>
        <?php endif; ?>
        </div>
    </main>
</div>

<dialog class="vp3-dialog" id="participants-dialog"><button class="vp3-dialog-x" data-dialog-close>×</button><small>PROTECTED PARTICIPANTS</small><h3>Who can see this thread</h3><p>Adult, student and approved guardian visibility are shown together so there is no ambiguity about who is included.</p><button class="button secondary" data-dialog-close>Close</button></dialog>
<dialog class="vp3-dialog" id="safety-dialog"><button class="vp3-dialog-x" data-dialog-close>×</button><small>SAFEGUARDING</small><h3>Guardian visibility is structural</h3><p>The production version will enforce required guardians server-side. A participant cannot simply remove the required guardian from a protected conversation.</p><button class="button secondary" data-dialog-close>Got it</button></dialog>
<dialog class="vp3-dialog" id="signup-dialog"><button class="vp3-dialog-x" data-dialog-close>×</button><small>CONFIRM SIGNUP</small><h3>Reserve this volunteer shift?</h3><p>This preview shows the confirmation moment. No signup is written during Visual Pass 3.</p><button class="button" data-preview-action="Shift signup previewed — nothing was reserved.">Confirm signup</button><button class="button secondary" data-dialog-close>Not yet</button></dialog>
<dialog class="vp3-dialog" id="schedule-dialog"><button class="vp3-dialog-x" data-dialog-close>×</button><small>REVIEW CHANGE</small><h3>Save and notify?</h3><p>Families and affected volunteers would receive the updated schedule details. This pass does not write the change.</p><button class="button" data-preview-action="Schedule change previewed — nothing was saved.">Save change</button><button class="button secondary" data-dialog-close>Keep editing</button></dialog>
<dialog class="vp3-dialog" id="escalate-dialog"><button class="vp3-dialog-x" data-dialog-close>×</button><small>ESCALATE REVIEW</small><h3>Escalation should require intention.</h3><p>The production flow will require a rationale and preserve an audit record. This preview intentionally stops before submission.</p><textarea rows="4" placeholder="Reason for escalation"></textarea><button class="button" data-preview-action="Escalation previewed — no case was changed.">Preview escalation</button><button class="button secondary" data-dialog-close>Cancel</button></dialog>
<div class="vp3-toast" role="status" aria-live="polite"></div>
<script src="<?= $url('/assets/js/visual-pass-3.js') ?>"></script>
</body>
</html>
<?php
        exit;
    }
}
