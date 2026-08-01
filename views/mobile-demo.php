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
$firstConversation = $conversations[0] ?? null;
?>
<section class="mobile-demo-stage">
    <div class="mobile-demo-copy">
        <p class="eyebrow">Future app experience</p>
        <h1>CTSMD Connect on iOS and Android.</h1>
        <p><?= $isPreviewing ? 'Previewing the mobile app as a demo ' . ((int) ($user['is_student'] ?? 0) === 1 ? 'student' : 'parent or staff member') . '.' : 'This screen is a web-rendered prototype of the mobile app direction: simple tabs, glanceable updates, protected conversations, and safety visibility up front.' ?></p>
        <a class="button button-primary" href="/dashboard">Back to dashboard <span>→</span></a>
        <?php if ($isAdmin): ?>
            <div class="persona-strip mobile-personas">
                <a href="/mobile-demo?persona=parent">Parent</a>
                <a href="/mobile-demo?persona=student">Student</a>
                <a href="/dashboard?persona=parent">Parent web</a>
                <a href="/dashboard?persona=student">Student web</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="phone-shell" aria-label="Mobile app demo">
        <div class="phone-status"><span>9:41</span><span>CTSMD</span></div>
        <div class="app-topbar">
            <div>
                <p>Welcome back</p>
                <h2><?= $h($user['first_name'] ?? 'Member') ?></h2>
            </div>
            <span class="avatar"><?= $h(strtoupper(substr((string) ($user['first_name'] ?? 'C'), 0, 1))) ?></span>
        </div>
        <div class="safety-strip">
            <strong>Guardian visibility active</strong>
            <span><?= $h(($reportCounts['open'] ?? 0) + ($reportCounts['reviewing'] ?? 0)) ?> review items · <?= $h($pendingNotifications) ?> notices</span>
        </div>
        <div class="mobile-section">
            <div class="mobile-heading"><h3>Today</h3><a href="/channels?id=1">All</a></div>
            <?php foreach ($recentPosts as $post): ?>
                <a class="mobile-card" href="/channels?id=<?= $h($post['channel_id'] ?? '') ?>">
                    <span><?= $h($post['channel_name'] ?? '') ?></span>
                    <strong><?= $h($post['body'] ?? '') ?></strong>
                    <small><?= (int) ($post['is_pinned'] ?? 0) === 1 ? 'Pinned update' : 'Community post' ?></small>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="mobile-section">
            <div class="mobile-heading"><h3>Safeguarded</h3><a href="<?= $firstConversation ? '/conversations?id=' . $h($firstConversation['id'] ?? '') : '#' ?>">Open</a></div>
            <div class="mobile-message-preview">
                <span>Protected thread</span>
                <strong><?= $firstConversation ? $h($firstConversation['participants'] ?? '') : 'No visible conversations yet.' ?></strong>
                <small>Required guardians cannot be removed while the student remains.</small>
            </div>
        </div>
        <div class="mobile-section">
            <div class="mobile-heading"><h3>Coming next</h3><a href="/dashboard">Roadmap</a></div>
            <div class="mobile-action-grid">
                <a href="/events">Events</a>
                <a href="/playbills">Playbills</a>
                <a href="/registrations">Signups</a>
                <a href="/website">Website</a>
            </div>
        </div>
        <div class="mobile-tabs">
            <a class="active" href="/mobile-demo"><span>●</span>Home</a>
            <a href="/channels?id=1"><span>◆</span>Channels</a>
            <a href="<?= $firstConversation ? '/conversations?id=' . $h($firstConversation['id'] ?? '') : '/dashboard' ?>"><span>◫</span>Messages</a>
            <a href="/dashboard"><span>≡</span>More</a>
        </div>
    </div>
</section>
