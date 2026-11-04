<?php
declare(strict_types=1);

/**
 * Public policy pages and a minimal deletion-request intake.
 * Submissions are queued for staff review, never acted on automatically.
 */
final class PublicPolicyExperience
{
    public static function handles(string $route): bool
    {
        return in_array($route, ['/privacy', '/account-deletion'], true);
    }

    public static function render(string $route, string $basePath): never
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true) || ($method === 'POST' && $route !== '/account-deletion')) {
            header('Allow: GET, HEAD'.($route === '/account-deletion' ? ', POST' : ''));
            http_response_code(405);
            exit('Method not allowed');
        }
        require_once __DIR__.'/Auth.php';
        Auth::startSession();
        $_SESSION['public_deletion_csrf'] ??= bin2hex(random_bytes(32));
        $error = '';
        $success = false;
        if ($route === '/account-deletion' && $method === 'POST') {
            header('Cache-Control: no-store');
            $submittedToken = (string) ($_POST['csrf_token'] ?? '');
            $accountEmail = trim((string) ($_POST['account_email'] ?? ''));
            $relationship = (string) ($_POST['relationship'] ?? '');
            $details = trim((string) ($_POST['details'] ?? ''));
            if (!hash_equals((string) $_SESSION['public_deletion_csrf'], $submittedToken)) {
                $error = 'Your form session has expired. Reload the page and try again.';
            } elseif (!empty($_POST['website'])) {
                // Honeypot: do not enqueue spam.
                $success = true;
            } elseif (!filter_var($accountEmail, FILTER_VALIDATE_EMAIL) || strlen($accountEmail) > 254) {
                $error = 'Enter a valid email address associated with the CTSMD account.';
            } elseif (!in_array($relationship, ['self', 'parent_guardian', 'other'], true)) {
                $error = 'Select your relationship to the account.';
            } elseif (mb_strlen($details) > 500) {
                $error = 'Please keep additional information under 500 characters.';
            } else {
                try {
                    require_once __DIR__.'/Database.php';
                    require_once __DIR__.'/RateLimiter.php';
                    require_once __DIR__.'/MailService.php';
                    $db = Database::connect(dirname(__DIR__));
                    RateLimiter::enforce($db, 'public-deletion-request', RateLimiter::requestSubject($accountEmail), 4, 3600, 3600);
                    $body = "CTSMD Connect deletion request\\n\\n"
                        ."Account email: ".$accountEmail."\\n"
                        ."Requestor relationship: ".$relationship."\\n"
                        ."Optional details: ".($details === '' ? '(none)' : $details)."\\n\\n"
                        ."This request has NOT deleted any data. Verify the requestor's identity and guardian authority before taking action.";
                    $id = MailService::queue($db, null, 'arwentz01@gmail.com', 'CTSMD privacy requests', 'system', 'CTSMD Connect account deletion request', $body);
                    if ($id < 1) throw new RuntimeException('Unable to queue deletion request');
                    // Post/redirect/get prevents accidental resubmission on refresh.
                    $_SESSION['public_deletion_success'] = true;
                    $_SESSION['public_deletion_csrf'] = bin2hex(random_bytes(32));
                    header('Location: '.$basePath.'/account-deletion?submitted=1', true, 303);
                    exit;
                } catch (RateLimitExceeded $e) {
                    http_response_code(429);
                    header('Retry-After: '.max(1, $e->retryAfter));
                    $error = 'Too many requests. Please try again later.';
                } catch (Throwable $e) {
                    error_log('CTSMD deletion request queue failed: '.get_class($e));
                    http_response_code(503);
                    $error = 'We could not accept your request right now. Please try again later.';
                }
            }
        }
        if ($route === '/account-deletion' && $method === 'GET' && ($_GET['submitted'] ?? '') === '1' && !empty($_SESSION['public_deletion_success'])) {
            $success = true;
            unset($_SESSION['public_deletion_success']);
        }
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $verifiedEmail = 'arwentz01@gmail.com';
        $privacy = $route === '/privacy';
        $title = $privacy ? 'Privacy Policy' : 'Account & Data Deletion';
        $privacyUrl = $escape($basePath.'/privacy');
        $deletionUrl = $escape($basePath.'/account-deletion');
        $homeUrl = $escape($basePath.'/');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= $escape($title) ?> | CTSMD Connect</title>
<style>
:root{font:16px/1.65 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#30242a;background:#f8f6f5}*{box-sizing:border-box}body{margin:0}header{background:#591b30;color:#fff;padding:22px max(20px,calc((100vw - 900px)/2))}header a{color:white}header strong{display:block;font-size:1.3rem;letter-spacing:.01em}header small{color:#f0d8df}nav{display:flex;gap:20px;flex-wrap:wrap;margin-top:10px}nav a{font-weight:600}main{max-width:900px;margin:28px auto;padding:0 18px 35px}article{background:white;border:1px solid #e8e0e3;border-radius:14px;padding:clamp(20px,4vw,42px);box-shadow:0 5px 24px #30242a0a}h1{font-size:clamp(1.7rem,4vw,2.25rem);line-height:1.2;margin:0 0 12px}h2{font-size:1.18rem;margin-top:30px;color:#591b30}p,li{max-width:75ch}a{color:#772840}a:focus-visible{outline:3px solid #b88e38;outline-offset:3px}ul{padding-left:23px}.note{background:#fff7e7;border-left:4px solid #a77b28;padding:12px 17px;border-radius:5px}.meta{color:#645861;font-size:.9rem}.request-form{display:grid;gap:12px;margin:22px 0}label{font-weight:650}input,select,textarea{font:inherit;padding:11px 12px;width:100%;border:1px solid #a99ba1;border-radius:8px;background:#fff}textarea{min-height:95px;resize:vertical}button{background:#591b30;color:white;font:inherit;font-weight:700;border:0;border-radius:8px;padding:12px 20px;cursor:pointer}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #b88e38;outline-offset:2px}.feedback{border-left:4px solid #8f243e;padding:12px 16px;background:#fff5f5}.success{border-color:#427450;background:#f1f8f1}.hp{position:absolute;left:-9999px}footer{margin-top:24px;color:#655a60;font-size:.9rem}
</style>
</head>
<body>
<header><strong>CTSMD Connect</strong><small>Children's Theatre of Southern Maryland</small><nav aria-label="Policy navigation"><a href="<?= $homeUrl ?>">Home</a><a href="<?= $privacyUrl ?>">Privacy policy</a><a href="<?= $deletionUrl ?>">Account deletion</a></nav></header>
<main><article>
<?php if ($privacy): ?>
<h1>Privacy Policy</h1>
<p class="meta">Applies to CTSMD Connect on the web, iOS, and Android. Updated November 4, 2026.</p>
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
<p>CTSMD Connect relies on hosting and service infrastructure, and some functionality can open external websites or ticketing and media services. Following an external link may subject you to that provider's privacy practices.</p>
<h2>Security and retention</h2>
<p>CTSMD uses account authentication and role-based access controls to help restrict sensitive workflows. No internet service can guarantee absolute security. Information may need to be retained for operational, legal, financial, historical, or safeguarding reasons. Retention depends on the type of record and any applicable operational, legal, or safeguarding obligations.</p>
<h2>Your choices and requests</h2>
<p>You can ask about your personal information and request correction or deletion through the process described on the <a href="<?= $deletionUrl ?>">account and data deletion page</a>. Requests involving a student may require confirmation of guardian authority. Some information may need to be retained to satisfy legitimate obligations.</p>
<h2>Contact</h2>
<p>For privacy questions, contact <a href="mailto:<?= $escape($verifiedEmail) ?>"><?= $escape($verifiedEmail) ?></a>.</p>
<h2>Changes to this policy</h2>
<p>This page may be updated as practices or legal requirements change. The update date above reflects the latest posted revision.</p>
<?php else: ?>
<h1>Request account or data deletion</h1>
<p class="meta">CTSMD Connect, web and mobile accounts.</p>
<p>This page explains how to request deletion of a CTSMD Connect account and associated personal information. You do not have to sign in to read these instructions.</p>
<h2>Submit a deletion request</h2>
<p>Use this form to request account or associated data deletion. Your request will be emailed to CTSMD for review. <strong>Submitting this form does not automatically delete any information.</strong> Do not include passwords, medical details, or other sensitive records.</p>
<?php if ($success): ?><p role="status" class="feedback success"><strong>Request received.</strong> Your deletion request has been queued for review. CTSMD may contact you to verify your identity or guardian authority.</p><?php endif; ?>
<?php if ($error !== ''): ?><p role="alert" class="feedback"><?= $escape($error) ?></p><?php endif; ?>
<form class="request-form" method="post" action="<?= $deletionUrl ?>">
<input type="hidden" name="csrf_token" value="<?= $escape((string) $_SESSION['public_deletion_csrf']) ?>">
<div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" autocomplete="off" tabindex="-1"></label></div>
<label for="account-email">Email address associated with the account</label>
<input id="account-email" type="email" name="account_email" maxlength="254" autocomplete="email" required value="<?= $escape((string) ($_POST['account_email'] ?? '')) ?>">
<label for="relationship">I am requesting on behalf of</label>
<select id="relationship" name="relationship" required>
<option value="">Choose one</option>
<option value="self" <?= ($_POST['relationship'] ?? '') === 'self' ? 'selected' : '' ?>>My own account</option>
<option value="parent_guardian" <?= ($_POST['relationship'] ?? '') === 'parent_guardian' ? 'selected' : '' ?>>A child I am the parent or guardian of</option>
<option value="other" <?= ($_POST['relationship'] ?? '') === 'other' ? 'selected' : '' ?>>Another account or situation</option>
</select>
<label for="details">Additional information (optional)</label>
<textarea id="details" name="details" maxlength="500" placeholder="Only include information necessary to identify your request."><?= $escape((string) ($_POST['details'] ?? '')) ?></textarea>
<button type="submit">Submit deletion request</button>
</form>
<p>For questions, you may also email <a href="mailto:<?= $escape($verifiedEmail) ?>"><?= $escape($verifiedEmail) ?></a>.</p>
<h2>Verification and review</h2>
<p>CTSMD must verify that a request comes from the account holder or an authorized parent or guardian before taking action. A request may require follow-up so that another person's information or legitimate family access is not affected.</p>
<h2>What deletion can include</h2>
<p>Following verification, CTSMD reviews account access, profile and contact information, and associated records for deletion or de-identification where appropriate. Information needed for safeguarding, compliance, financial records, or legitimate organizational obligations may have to be retained. </p>
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
