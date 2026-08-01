<?php
/** @var string $csrf */
/** @var string $token */
/** @var string|null $error */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="auth-page">
    <form class="auth-card" method="post" action="/accept-invite">
        <p class="eyebrow">Invitation</p>
        <h1>Activate account</h1>
        <?php if ($error): ?><p class="form-error"><?= $h($error) ?></p><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="token" value="<?= $h($token) ?>">
        <label>Password <input required minlength="10" type="password" name="password" autocomplete="new-password"></label>
        <button type="submit">Accept invite</button>
    </form>
</section>
