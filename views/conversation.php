<?php
/** @var array<string, mixed> $user */
/** @var array<string, mixed> $conversation */
/** @var array<int, array<string, mixed>> $messages */
/** @var string $csrf */
/** @var string|null $flash */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="conversation-page">
    <div class="conversation-header">
        <p class="eyebrow">Safeguarded messaging</p>
        <h1>Conversation #<?= $h($conversation['id'] ?? '') ?></h1>
        <p><?= $h($conversation['participants'] ?? '') ?></p>
        <a href="/admin#safeguarding">Back to admin</a>
    </div>
    <?php if ($flash): ?>
        <div class="status-banner"><span>✓</span><div><strong>Updated</strong><p><?= $h($flash) ?></p></div></div>
    <?php endif; ?>
    <div class="message-list">
        <?php foreach ($messages as $message): ?>
            <article class="message-item">
                <div><strong><?= $h(($message['first_name'] ?? '') . ' ' . ($message['last_name'] ?? '')) ?></strong><span><?= $h($message['created_at'] ?? '') ?></span></div>
                <p><?= nl2br($h($message['deleted_at'] ? '[deleted]' : ($message['body'] ?? ''))) ?></p>
                <form class="inline-report" method="post" action="/reports">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="return_to" value="/conversations?id=<?= $h($conversation['id'] ?? '') ?>">
                    <input type="hidden" name="subject_type" value="message">
                    <input type="hidden" name="subject_id" value="<?= $h($message['id'] ?? '') ?>">
                    <input type="hidden" name="reason" value="safeguarding_review">
                    <input name="details" placeholder="Optional note for safeguarding">
                    <button type="submit">Report</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($messages === []): ?><p class="empty-state">No messages yet.</p><?php endif; ?>
    </div>
    <form class="message-form" method="post" action="/conversations/messages">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="conversation_id" value="<?= $h($conversation['id'] ?? '') ?>">
        <label>Message <textarea required name="body" rows="4"></textarea></label>
        <button type="submit">Post message</button>
    </form>
</section>
