<?php
declare(strict_types=1);

/**
 * Public, read-only policy pages. These routes intentionally bypass membership
 * requirements and do not read or modify any account, child, or demo records.
 * Legal and operational review is required before publication.
 */
final class PublicPolicyExperience
{
    public static function handles(string $route): bool
    {
        return in_array($route, ['/privacy', '/account-deletion'], true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            header('Allow: GET, HEAD');
            http_response_code(405);
            exit('Method not allowed');
        }
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $email = trim((string) (getenv('CTSMD_PRIVACY_CONTACT_EMAIL') ?: ''));
        $verifiedEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $privacy = $route === '/privacy';
        $title = $privacy ? 'Privacy Policy' : 'Account & Data Deletion';
        $privacyUrl = $escape($basePath.'/privacy');
        $deletionUrl = $escape($basePath.'/account-deletion');
        $homeUrl = $escape($basePath.'/');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= $escape($title) ?> | CTSMD Connect</title>
<style>
:root{font:16px/1.65 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#30242a;background:#f8f6f5}*{box-sizing:border-box}body{margin:0}header{background:#591b30;color:#fff;padding:22px max(20px,calc((100vw - 900px)/2))}header a{color:white}header strong{display:block;font-size:1.3rem;letter-spacing:.01em}header small{color:#f0d8df}nav{display:flex;gap:20px;flex-wrap:wrap;margin-top:10px}nav a{font-weight:600}main{max-width:900px;margin:28px auto;padding:0 18px 35px}article{background:white;border:1px solid #e8e0e3;border-radius:14px;padding:clamp(20px,4vw,42px);box-shadow:0 5px 24px #30242a0a}h1{font-size:clamp(1.7rem,4vw,2.25rem);line-height:1.2;margin:0 0 12px}h2{font-size:1.18rem;margin-top:30px;color:#591b30}p,li{max-width:75ch}a{color:#772840}a:focus-visible{outline:3px solid #b88e38;outline-offset:3px}ul{padding-left:23px}.note{background:#fff7e7;border-left:4px solid #a77b28;padding:12px 17px;border-radius:5px}.meta{color:#645861;font-size:.9rem}footer{margin-top:24px;color:#655a60;font-size:.9rem}
</style>
</head>
<body>
<header><strong>CTSMD Connect</strong><small>Children's Theatre of Southern Maryland</small><nav aria-label="Policy navigation"><a href="<?= $homeUrl ?>">Home</a><a href="<?= $privacyUrl ?>">Privacy policy</a><a href="<?= $deletionUrl ?>">Account deletion</a></nav></header>
<main><article>
<?php if ($privacy): ?>
<h1>Privacy Policy</h1>
<p class="meta">Applies to CTSMD Connect on the web, iOS, and Android. Draft for organizational review, November 4, 2026.</p>
<p class="note"><strong>Release review required:</strong> This policy describes known application features. CTSMD must verify third-party processors, retention periods, contact information, and actual data flows before relying on it for an app-store submission.</p>
<h2>Who operates CTSMD Connect</h2>
<p>CTSMD Connect is a platform used by Children's Theatre of Southern Maryland to coordinate youth theatre participation, productions, volunteers, and family communication.</p>
<h2>Information handled by the service</h2>
<p>Depending on how you use the service, CTSMD Connect handles account identifiers and contact details, student and guardian relationships, production participation, auditions and casting, schedules, forms, messages, community posts, photos, videos, and other content submitted by members. Authorized workflows may also handle accommodation, allergy, emergency, pickup authorization, and incident or safeguarding information.</p>
<p>The mobile application may handle device notification permissions and push-notification tokens when you choose to register a device. The service also processes technical information needed for authentication, access controls, security, and reliable operation.</p>
<h2>Why information is used</h2>
<p>Information is used to administer membership and theatre activities; coordinate productions and volunteer work; communicate relevant updates; support family and student participation; manage authorized access; and respond to safety, safeguarding, administrative, and technical issues.</p>
<h2>Students, guardians, and access</h2>
<p>The service supports youth participants and their parents or guardians. Access to student information and communications depends on account relationships, production roles, and safeguarding permissions. Public pages and published materials may have different visibility rules from authenticated member areas.</p>
<h2>Service providers and external links</h2>
<p>CTSMD Connect relies on hosting and service infrastructure, and some functionality can open external websites or ticketing and media services. The organization must complete its vendor and data-sharing inventory before final publication of this policy. Following an external link may subject you to that provider's privacy practices.</p>
<h2>Security and retention</h2>
<p>CTSMD uses account authentication and role-based access controls to help restrict sensitive workflows. No internet service can guarantee absolute security. Information may need to be retained for operational, legal, financial, historical, or safeguarding reasons. The applicable retention schedule and deletion exceptions require organizational confirmation.</p>
<h2>Your choices and requests</h2>
<p>You can ask about your personal information and request correction or deletion through the process described on the <a href="<?= $deletionUrl ?>">account and data deletion page</a>. Requests involving a student may require confirmation of guardian authority. Some information may need to be retained to satisfy legitimate obligations.</p>
<h2>Contact</h2>
<?php if ($verifiedEmail !== ''): ?>
<p>For privacy questions, contact <a href="mailto:<?= $escape($verifiedEmail) ?>"><?= $escape($verifiedEmail) ?></a>.</p>
<?php else: ?>
<p>For privacy questions, contact your CTSMD organizational administrator. A dedicated privacy contact address must be confirmed before app-store submission.</p>
<?php endif; ?>
<h2>Changes to this policy</h2>
<p>This page will be updated when practices or requirements change. The organization will confirm an effective date when it approves the final policy.</p>
<?php else: ?>
<h1>Request account or data deletion</h1>
<p class="meta">CTSMD Connect, web and mobile accounts.</p>
<p>This page explains how to request deletion of a CTSMD Connect account and associated personal information. You do not have to sign in to read these instructions.</p>
<h2>How to make a request</h2>
<?php if ($verifiedEmail !== ''): ?>
<p>Email <a href="mailto:<?= $escape($verifiedEmail) ?>?subject=CTSMD%20Connect%20account%20deletion%20request"><?= $escape($verifiedEmail) ?></a> with the subject <strong>CTSMD Connect account deletion request</strong>. Include the email address associated with your account and whether the request concerns your account or a student under your care. Do not send passwords, medical information, or other sensitive records by email.</p>
<?php else: ?>
<p class="note"><strong>Deletion request intake is not yet configured.</strong> Contact your CTSMD organizational administrator to initiate a request. Before app-store publication, CTSMD must publish a verified deletion-request contact or web intake process on this page.</p>
<?php endif; ?>
<h2>Verification and review</h2>
<p>CTSMD must verify that a request comes from the account holder or an authorized parent or guardian before taking action. A request may require follow-up so that another person's information or legitimate family access is not affected.</p>
<h2>What deletion can include</h2>
<p>Following verification, CTSMD reviews account access, profile and contact information, and associated records for deletion or de-identification where appropriate. Information needed for safeguarding, compliance, financial records, or legitimate organizational obligations may have to be retained. The precise retention categories and periods will be confirmed in the final policy.</p>
<h2>What happens to shared information</h2>
<p>Messages, participation records, and posts may be intertwined with other members' records or organizational history. They will be reviewed according to applicable permissions, policies, and retention obligations rather than removed automatically without review.</p>
<h2>Privacy information</h2>
<p>Read the <a href="<?= $privacyUrl ?>">CTSMD Connect Privacy Policy</a> for more information about the service.</p>
<?php endif; ?>
</article><footer>CTSMD Connect · Children's Theatre of Southern Maryland</footer></main>
</body></html>
<?php
        exit;
    }
}
