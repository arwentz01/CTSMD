<?php
/** @var array<string, mixed> $user */
/** @var array<string, mixed> $viewer */
/** @var bool $isAdmin */
/** @var bool $isPreviewing */
/** @var array<string, int> $counts */
/** @var list<string> $roles */
/** @var array<int, array<string, mixed>> $channels */
/** @var array<int, array<string, mixed>> $recentPosts */
/** @var array<int, array<string, mixed>> $conversations */
/** @var array<string, int> $reportCounts */
/** @var int $pendingNotifications */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$firstName = (string) ($user['first_name'] ?? 'Member');
$initial = strtoupper(substr($firstName, 0, 1));
$isStudent = (int) ($user['is_student'] ?? 0) === 1;
$roleLabel = $isStudent ? 'Student performer' : (in_array('guardian', $roles, true) ? 'Parent / guardian' : 'Staff / admin');
$activeReviews = (int) ($reportCounts['open'] ?? 0) + (int) ($reportCounts['reviewing'] ?? 0);
$firstConversation = $conversations[0] ?? null;
$channelIcon = static fn (mixed $name): string => strtoupper(substr((string) $name, 0, 1));
?>
<section class="mobile-app" aria-label="CTSMD Connect mobile app demo">
    <header class="mobile-app-bar">
        <div>
            <p class="eyebrow">CTSMD Connect</p>
            <h1><?= $h($firstName) ?></h1>
            <span><?= $h($roleLabel) ?><?= $isPreviewing ? ' preview' : '' ?></span>
        </div>
        <a class="mobile-avatar" href="/dashboard" aria-label="Back to dashboard"><?= $h($initial) ?></a>
    </header>

    <?php if ($isAdmin): ?>
        <nav class="mobile-persona-switch" aria-label="Preview personas">
            <a href="/mobile-demo">Admin</a>
            <a href="/mobile-demo?persona=parent">Parent</a>
            <a href="/mobile-demo?persona=student">Student</a>
            <a href="/mobile-demo?persona=instructor">Instructor</a>
        </nav>
    <?php endif; ?>

    <nav class="mobile-segments" aria-label="Mobile sections">
        <a href="#mobile-channels">Channels</a>
        <a href="#mobile-messages">Messages</a>
        <a href="#mobile-safety">Safety</a>
        <a href="#mobile-roadmap">More</a>
    </nav>

    <section id="mobile-safety" class="mobile-alert">
        <span class="mobile-alert-icon" aria-hidden="true">✓</span>
        <div>
            <strong>Guardian visibility active</strong>
            <span><?= $h($activeReviews) ?> review items · <?= $h($pendingNotifications) ?> pending notice<?= $pendingNotifications === 1 ? '' : 's' ?></span>
        </div>
        <a href="/reports">Review</a>
    </section>

    <section id="mobile-channels" class="mobile-pane">
        <div class="mobile-pane-heading">
            <h2>Channels</h2>
            <a href="/channels">See all</a>
        </div>
        <div class="mobile-list">
            <?php foreach ($channels as $channel): ?>
                <a class="mobile-list-card" href="/channels?id=<?= $h($channel['id'] ?? '') ?>">
                    <span class="mobile-card-icon" aria-hidden="true"><?= $h($channelIcon($channel['name'] ?? 'C')) ?></span>
                    <span class="mobile-card-kicker"><?= $h($channel['type'] ?? 'Channel') ?> · <?= $h($channel['posting_policy'] ?? 'Members') ?></span>
                    <strong><?= $h($channel['name'] ?? '') ?></strong>
                    <p><?= $h($channel['description'] ?? '') ?></p>
                    <span class="mobile-card-arrow" aria-hidden="true">›</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mobile-pane">
        <div class="mobile-pane-heading">
            <h2>Latest Posts</h2>
            <a href="/channels/posts">Post</a>
        </div>
        <div class="mobile-feed">
            <?php foreach ($recentPosts as $post): ?>
                <a class="mobile-feed-item" href="/channels?id=<?= $h($post['channel_id'] ?? '') ?>">
                    <span class="mobile-feed-dot" aria-hidden="true"></span>
                    <span class="mobile-card-kicker"><?= $h($post['channel_name'] ?? '') ?></span>
                    <p><?= $h($post['body'] ?? '') ?></p>
                    <small><?= (int) ($post['is_pinned'] ?? 0) === 1 ? 'Pinned update' : 'Community post' ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="mobile-messages" class="mobile-pane">
        <div class="mobile-pane-heading">
            <h2>Messages</h2>
            <a href="<?= $firstConversation ? '/conversations?id=' . $h($firstConversation['id'] ?? '') : '/conversations' ?>">Open</a>
        </div>
        <div class="mobile-list">
            <?php if ($conversations === []): ?>
                <div class="mobile-empty">No conversations are visible for this persona yet.</div>
            <?php endif; ?>
            <?php foreach ($conversations as $conversation): ?>
                <a class="mobile-list-card message" href="/conversations?id=<?= $h($conversation['id'] ?? '') ?>">
                    <span class="mobile-card-icon message-icon" aria-hidden="true">M</span>
                    <span class="mobile-card-kicker">Protected thread</span>
                    <strong><?= $h($conversation['participants'] ?? 'Conversation') ?></strong>
                    <p><?= $h($conversation['topic'] ?? 'Safeguarded message thread') ?></p>
                    <small>Required guardians remain included while a student is in the thread.</small>
                    <span class="mobile-card-arrow" aria-hidden="true">›</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="mobile-roadmap" class="mobile-pane mobile-roadmap-pane">
        <div class="mobile-pane-heading">
            <h2>Next Modules</h2>
            <a href="/dashboard">Web</a>
        </div>
        <div class="mobile-action-grid">
            <a href="/events"><strong>Events</strong><span>Schedules and call times</span></a>
            <a href="/playbills"><strong>Playbills</strong><span>Production programs</span></a>
            <a href="/registrations"><strong>Signups</strong><span>Auditions and forms</span></a>
            <a href="/website"><strong>Website</strong><span>Public content path</span></a>
        </div>
    </section>

    <nav class="mobile-bottom-nav" aria-label="Mobile app navigation">
        <a class="active" href="/mobile-demo"><span aria-hidden="true">H</span>Home</a>
        <a href="#mobile-channels"><span aria-hidden="true">#</span>Channels</a>
        <a href="#mobile-messages"><span aria-hidden="true">M</span>Messages</a>
        <a href="#mobile-safety"><span aria-hidden="true">!</span>Safety</a>
        <a href="#mobile-roadmap"><span aria-hidden="true">+</span>More</a>
    </nav>
</section>
