<?php
/** @var string $csrf */
/** @var string|null $error */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="auth-page">
    <form class="auth-card" method="post" action="/setup">
        <p class="eyebrow">First run</p>
        <h1>Create owner</h1>
        <?php if ($error): ?><p class="form-error"><?= $h($error) ?></p><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <label>Email <input required type="email" name="email" autocomplete="email"></label>
        <div class="form-row">
            <label>First name <input required name="first_name" autocomplete="given-name"></label>
            <label>Last name <input required name="last_name" autocomplete="family-name"></label>
        </div>
        <label>Password <input required minlength="10" type="password" name="password" autocomplete="new-password"></label>
        <button type="submit">Create secure owner</button>
    </form>
</section>
