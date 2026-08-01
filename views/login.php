<?php
/** @var string $csrf */
/** @var string|null $error */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="auth-page">
    <form class="auth-card" method="post" action="/login">
        <p class="eyebrow">CTSMD Connect</p>
        <h1>Sign in</h1>
        <?php if ($error): ?><p class="form-error"><?= $h($error) ?></p><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <label>Email <input required type="email" name="email" autocomplete="email"></label>
        <label>Password <input required type="password" name="password" autocomplete="current-password"></label>
        <button type="submit">Enter admin</button>
        <a href="/setup">Create first owner</a>
    </form>
</section>
