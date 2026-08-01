<?php
/** @var array<string, mixed> $user */
/** @var bool $isAdmin */
/** @var array<string, int> $counts */
/** @var list<string> $roles */
/** @var array<int, array<string, mixed>> $channels */
/** @var array<int, array<string, mixed>> $recentPosts */
/** @var array<int, array<string, mixed>> $conversations */
/** @var array<string, int> $reportCounts */
/** @var int $pendingNotifications */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="dashboard">
    <div class="dashboard-hero">
        <p class="eyebrow">CTSMD Connect demo</p>
        <h1>Welcome, <?= $h($user['first_name'] ?? 'there') ?>.</h1>
        <p>One private place for announcements, parent questions, safeguarded messages, and moderation visibility.</p>
        <div class="hero-actions">
            <?php if ($isAdmin): ?><a class="button button-primary" href="/admin">Open admin controls <span>→</span></a><?php endif; ?>
            <a class="button button-primary" href="/channels?id=1">View announcements <span>→</span></a>
        </div>
    </div>

    <div class="metric-grid dashboard-metrics">
        <article><span>Members</span><strong><?= $h($counts['members'] ?? 0) ?></strong><small><?= $h(implode(', ', $roles)) ?></small></article>
        <article><span>Channels</span><strong><?= $h($counts['channels'] ?? 0) ?></strong><small>Community updates</small></article>
        <article><span>Safeguarded threads</span><strong><?= count($conversations) ?></strong><small>Participant-only</small></article>
        <article class="accent"><span>Review queue</span><strong><?= $h(($reportCounts['open'] ?? 0) + ($reportCounts['reviewing'] ?? 0)) ?></strong><small><?= $h($pendingNotifications) ?> pending notices</small></article>
    </div>

    <div class="admin-grid">
        <article class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Latest posts</p><h3>Community feed</h3></div><span><?= count($recentPosts) ?> items</span></div>
            <div class="compact-list">
                <?php foreach ($recentPosts as $post): ?>
                    <p><strong><a href="/channels?id=<?= $h($post['channel_id'] ?? '') ?>"><?= $h($post['channel_name'] ?? '') ?></a></strong><?= $h($post['body'] ?? '') ?> <span><?= (int) ($post['is_pinned'] ?? 0) === 1 ? 'pinned' : $h($post['channel_type'] ?? '') ?></span></p>
                <?php endforeach; ?>
                <?php if ($recentPosts === []): ?><p>No posts yet.</p><?php endif; ?>
            </div>
        </article>
        <article class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Safeguarded</p><h3>Visible conversations</h3></div><span><?= count($conversations) ?> threads</span></div>
            <div class="compact-list">
                <?php foreach ($conversations as $conversation): ?>
                    <p><strong><a href="/conversations?id=<?= $h($conversation['id'] ?? '') ?>">Conversation #<?= $h($conversation['id'] ?? '') ?></a></strong><?= $h($conversation['participants'] ?? '') ?> <span><?= $h($conversation['type'] ?? '') ?></span></p>
                <?php endforeach; ?>
                <?php if ($conversations === []): ?><p>No safeguarded conversations visible to this account.</p><?php endif; ?>
            </div>
        </article>
    </div>

    <div class="admin-grid">
        <article class="panel">
            <div class="panel-heading"><div><p class="eyebrow">Channels</p><h3>Spaces</h3></div><span><?= count($channels) ?> active</span></div>
            <div class="compact-list">
                <?php foreach ($channels as $channel): ?>
                    <p><strong><a href="/channels?id=<?= $h($channel['id'] ?? '') ?>"><?= $h($channel['name'] ?? '') ?></a></strong><?= $h($channel['description'] ?? '') ?> <span><?= $h($channel['posting_policy'] ?? '') ?></span></p>
                <?php endforeach; ?>
            </div>
        </article>
        <article class="panel safety-panel">
            <p class="eyebrow">Board demo path</p>
            <h3>Suggested walkthrough</h3>
            <p>Start here, open Announcements, then Parent Questions, then the safeguarded conversation. Finish in Admin to show guardian links, moderation, and the notification outbox.</p>
            <span class="policy-status">Demo ready</span>
        </article>
    </div>
</section>
