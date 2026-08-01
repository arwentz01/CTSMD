<?php
/** @var array<string, mixed> $channel */
/** @var array<int, array<string, mixed>> $posts */
/** @var array<int, array<string, mixed>> $members */
/** @var string $csrf */
/** @var bool $canPost */
/** @var string|null $flash */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="conversation-page">
    <div class="conversation-header">
        <p class="eyebrow"><?= $h($channel['type'] ?? '') ?> channel</p>
        <h1><?= $h($channel['name'] ?? '') ?></h1>
        <p><?= $h($channel['description'] ?? '') ?></p>
        <a href="/admin#channels">Back to admin</a>
    </div>
    <?php if ($flash): ?>
        <div class="status-banner"><span>✓</span><div><strong>Updated</strong><p><?= $h($flash) ?></p></div></div>
    <?php endif; ?>
    <?php if ($canPost): ?>
        <form class="message-form" method="post" action="/channels/posts">
            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <input type="hidden" name="channel_id" value="<?= $h($channel['id'] ?? '') ?>">
            <label>Post <textarea required name="body" rows="4"></textarea></label>
            <button type="submit">Publish to channel</button>
        </form>
    <?php endif; ?>
    <div class="compact-list channel-members">
        <?php foreach ($members as $member): ?>
            <p><strong><?= $h(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></strong><?= $h($member['email'] ?? '') ?> <span><?= (int) ($member['can_post'] ?? 0) === 1 ? 'can post' : 'read only' ?></span></p>
        <?php endforeach; ?>
    </div>
    <div class="message-list">
        <?php foreach ($posts as $post): ?>
            <article class="message-item <?= (int) ($post['is_pinned'] ?? 0) === 1 ? 'pinned-post' : '' ?>">
                <div><strong><?= $h(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')) ?></strong><span><?= (int) ($post['is_pinned'] ?? 0) === 1 ? 'Pinned · ' : '' ?><?= $h($post['created_at'] ?? '') ?></span></div>
                <p><?= nl2br($h($post['deleted_at'] ? '[deleted]' : ($post['body'] ?? ''))) ?></p>
                <form class="inline-report" method="post" action="/reports">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="return_to" value="/channels?id=<?= $h($channel['id'] ?? '') ?>">
                    <input type="hidden" name="subject_type" value="channel_post">
                    <input type="hidden" name="subject_id" value="<?= $h($post['id'] ?? '') ?>">
                    <input type="hidden" name="reason" value="moderation_review">
                    <input name="details" placeholder="Optional note for moderators">
                    <button type="submit">Report</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($posts === []): ?><p class="empty-state">No posts yet.</p><?php endif; ?>
    </div>
</section>
