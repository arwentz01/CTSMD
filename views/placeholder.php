<?php
/** @var string $eyebrow */
/** @var string $heading */
/** @var string $body */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="placeholder-page">
    <p class="eyebrow"><?= $h($eyebrow) ?></p>
    <h1><?= $h($heading) ?></h1>
    <p><?= $h($body) ?></p>
    <div class="future-strip">
        <a href="/events"><strong>Events</strong><span>Schedules and attendance</span></a>
        <a href="/playbills"><strong>Playbills</strong><span>Programs and archives</span></a>
        <a href="/registrations"><strong>Registrations</strong><span>Auditions and forms</span></a>
        <a href="/website"><strong>Website</strong><span>Public site integration</span></a>
    </div>
    <a class="button button-primary" href="/dashboard">Back to dashboard <span>→</span></a>
</section>
