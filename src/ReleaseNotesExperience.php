<?php
declare(strict_types=1);

/**
 * Public release notes for native app testers and members. Update entries here
 * when a release is ready; native applications keep the same permanent link.
 */
final class ReleaseNotesExperience
{
    /** Upcoming app changes. No store version or date is promised yet. */
    private const NEXT_UPDATE = [
        'name'=>'Coming in the next app update',
        'intro'=>'We are working on improvements that make important theatre updates easier to find and act on. These features are not available in every installed app yet.',
        'sections'=>[
            ['title'=>'A clearer first-login experience','summary'=>'Your next steps appear on Home based on what is actually ready.','details'=>[
                'A Getting Started checklist helps new staff, parents and students understand where to begin.',
                'Completion is based on actual account and production records, not manually ticked boxes.',
                'Approval and production assignment steps are clearly labeled as waiting for CTSMD when staff action is needed.',
                'Each available action links to account, household, production or Help guidance.',
                'Once setup is ready, the checklist is no longer shown.'
                'You can hide the checklist for seven days and restore it from Home at any time.'
            ]],
            ['title'=>'Help for getting started','summary'=>'Clear, step-by-step guidance for families, students and staff.','details'=>[
                'The app Help screen opens the CTSMD website Help & Guides center for searchable instructions.',
                'New first-day guides walk staff, parents and students through sign-in, household setup, invitations, and where to find their next steps.',
                'Guides explain that account activation, membership approval, and production assignments happen separately.',
                'Instructions link directly to the relevant web pages, with topics filtered by staff responsibilities.'
            ]],
            ['title'=>'Casting responses for production teams','summary'=>'See accepted and declined casting offers without hunting for updates.','details'=>[
                'Authorized production staff can see new casting responses in Needs Your Attention.',
                'Open Action Center to review and acknowledge a response. Once acknowledged, it no longer appears as outstanding for that person.'
            ]],
            ['title'=>'New member applications','summary'=>'A shared list of new registrations waiting for CTSMD approval.','details'=>[
                'Staff with account-review permission will see a pending registration count on Home and in Action Center.',
                'Open an application directly from the alert to review membership access.',
                'When someone is approved or declined, the pending alert clears for everyone who can review applications.'
            ]],
            ['title'=>'Easier everyday navigation','summary'=>'Less clutter in conversations and clearer activity in Community.','details'=>[
                'A more compact Messages inbox, faster search, and easier ways to start a conversation.',
                'Clearer unread badges on Community channels, plus a New Posts divider and temporary highlights when you open a channel.',
                'Eligible Android test builds can show when a newer app update is available.'
            ]]
        ]
    ];

    private const RELEASES = [
        [
            'version'=>'0.2.0',
            'number'=>2,
            'name'=>'A simpler way to stay connected',
            'status'=>'Closed testing',
            'intro'=>'A cleaner app with easier navigation, clearer unread activity, and more room for the conversations and theatre updates that matter.',
            'sections'=>[
                [
                    'title'=>'Messages',
                    'summary'=>'A compact inbox, live search, and unread message counts at a glance.',
                    'details'=>[
                        'A cleaner inbox with compact conversation rows and a simple button to start a new message.',
                        'Search conversations as you type, including message text and people.',
                        'Search for recipients by name when starting a conversation, with a message box that stays above the keyboard.',
                        'Press and hold a conversation to archive or restore it. New incoming messages bring archived conversations back to your inbox.',
                        'Press and hold a message for deletion options, including removing your own unread message for everyone when permitted.',
                        'Use conversation options to report a message and include additional details for the safeguarding team.',
                        'Message times, read indicators, and unread conversation counts stay visible without extra action buttons.'
                    ]
                ],
                [
                    'title'=>'Community',
                    'summary'=>'Less scrolling, clearer posts, and burgundy highlights for unread activity.',
                    'details'=>[
                        'Community posts use a tighter, more readable feed with less empty space.',
                        'Unread posts are highlighted in burgundy; posts you have already read return to the normal appearance.',
                        'Opening a channel updates its read status, so you can see what is new when you return.',
                        'Browse Feed or Channels and open the original post to see its conversation.'
                    ]
                ],
                [
                    'title'=>'Easier navigation',
                    'summary'=>'Cleaner screens with less clutter and more room for what you need.',
                    'details'=>[
                        'Tighter layouts make conversations and Community posts easier to scan.',
                        'Common actions are easier to find, while less frequently used options stay out of the way.'
                    ]
                ],
                [
                    'title'=>'Your home and Action Center',
                    'summary'=>'Important theatre tasks and updates together in one place.',
                    'details'=>[
                        'See upcoming activity, forms, casting offers, and production tasks from Home and Action Center.',
                        'Staff with the appropriate permissions can review Community moderation concerns.',
                        'Authorized safeguarding team members can review reports about private messages.',
                        'Household setup is labeled Manage household once children are linked to your account.'
                    ]
                ],
                [
                    'title'=>'Sign-in and accounts',
                    'summary'=>'A clearer sign-in experience and easier account access.',
                    'details'=>[
                        'Updated sign-in design, password visibility, and more comfortable keyboard handling.',
                        'Quick links to create an account or reset a password.'
                    ]
                ],
                [
                    'title'=>'Notifications and updates',
                    'summary'=>'Device alerts and up-to-date release notes without another app rebuild.',
                    'details'=>[
                        'The app offers an option to enable device notifications and manage them later in Settings.',
                        'Eligible Android test builds can show an update icon when a newer closed-testing release is available.',
                        'Open What’s New from the app to read the latest release information on CTSMD.me, even when the wording changes between app builds.'
                    ]
                ]
            ]
        ],
        [
            'version'=>'0.1.0',
            'number'=>1,
            'name'=>'Welcome to CTSMD Connect',
            'status'=>'Initial release',
            'intro'=>'The first CTSMD Connect app brought our theatre community together in one place.',
            'sections'=>[
                [
                    'title'=>'Getting started',
                    'summary'=>'Your theatre community in your pocket.',
                    'details'=>[
                        'Sign in with your CTSMD account.',
                        'Find theatre activity, conversations, Community, and important updates.',
                        'Explore productions and schedules from the main navigation.'
                    ]
                ]
            ]
        ]
    ];

    public static function render(string $basePath): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            http_response_code(405);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = ($basePath ?: '') . '/';
        $releases = self::RELEASES;
        $preview = self::NEXT_UPDATE;
        ?><!doctype html>
<html lang="en">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="theme-color" content="#8b233b">
 <meta name="description" content="See the latest improvements and release history for CTSMD Connect.">
 <title>What's New | CTSMD Connect</title>
 <style>
 :root{color-scheme:light;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#292323;background:#f7f4f1}
 *{box-sizing:border-box}body{margin:0}a{color:inherit}a:focus-visible,summary:focus-visible{outline:3px solid #b58a33;outline-offset:3px}
 .masthead{background:linear-gradient(135deg,#540f25,#8b233b);color:#fff;padding:32px 20px}
 .shell{width:min(100%,770px);margin:auto}.eyebrow{font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#f2c879;font-weight:800}
 h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(32px,6vw,44px);margin:10px 0 12px}
 .lead{font-size:16px;line-height:1.6;color:#f7e8e8;max-width:570px;margin:0}
 main{padding:26px 18px 50px}.release{border:1px solid #e4dcd5;background:white;border-radius:17px;overflow:hidden;margin-bottom:18px}
 .release.preview{border-color:#9a4055}
 .release>summary{list-style:none;display:flex;align-items:flex-start;gap:12px;padding:20px;cursor:pointer}
 .release>summary::-webkit-details-marker,.section>summary::-webkit-details-marker{display:none}
 .release>summary:after,.section>summary:after{content:"+";margin-left:auto;font-size:26px;color:#8b233b;line-height:1}
 .release[open]>summary:after,.section[open]>summary:after{content:"−"}
 .meta{font-size:11px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;color:#8b233b}
 h2{font-size:21px;line-height:1.25;margin:6px 0}.status{font-size:12px;font-weight:700;color:#685d59}
 .releaseBody{padding:0 18px 20px;border-top:1px solid #eee6df}.intro{font-size:14px;line-height:1.6;color:#685d59}
 .section{background:#f7f4f1;border-radius:11px;margin-top:10px;overflow:hidden}
 .section>summary{display:flex;align-items:center;gap:12px;padding:14px;cursor:pointer;list-style:none}
 .section>summary>span{flex:1}.section strong{display:block;font-size:15px}.section small{display:block;color:#685d59;line-height:1.5;font-size:13px;margin-top:3px}
 .details{border-top:1px solid #e4dcd5;margin:0;padding:12px 18px 14px 34px;font-size:14px;line-height:1.65}
 .details li{padding:4px 0}.footer{font-size:12px;line-height:1.7;color:#685d59;padding:4px 2px}
 @media(min-width:700px){.masthead{padding:48px 24px}main{padding-top:30px}}
 </style>
</head>
<body>
 <header class="masthead"><div class="shell"><div class="eyebrow">CTSMD Connect · Release notes</div><h1>What's New</h1><p class="lead">See what's changing in CTSMD Connect. Open a release or a topic to explore the details.</p></div></header>
 <main class="shell">
 <details class="release preview" open>
  <summary><div>
   <div class="meta">NEXT APP UPDATE · PREVIEW</div>
   <h2><?= $esc($preview['name']) ?></h2>
   <div class="status">Not released yet · Version and date to be announced</div>
  </div></summary>
  <div class="releaseBody">
   <p class="intro"><?= $esc($preview['intro']) ?></p>
   <?php foreach ($preview['sections'] as $section): ?>
   <details class="section">
    <summary><span><strong><?= $esc($section['title']) ?></strong><small><?= $esc($section['summary']) ?></small></span></summary>
    <ul class="details"><?php foreach ($section['details'] as $item): ?><li><?= $esc($item) ?></li><?php endforeach; ?></ul>
   </details>
   <?php endforeach; ?>
  </div>
 </details>
 <?php foreach ($releases as $index => $release): ?>
 <details class="release" <?= $index === 0 ? 'open' : '' ?>>
  <summary><div>
   <div class="meta">Release <?= (int)$release['number'] ?> · Version <?= $esc($release['version']) ?></div>
   <h2><?= $esc($release['name']) ?></h2>
   <div class="status"><?= $esc($release['status']) ?></div>
  </div></summary>
  <div class="releaseBody">
   <p class="intro"><?= $esc($release['intro']) ?></p>
   <?php foreach ($release['sections'] as $section): ?>
   <details class="section">
    <summary><span><strong><?= $esc($section['title']) ?></strong><small><?= $esc($section['summary']) ?></small></span></summary>
    <ul class="details"><?php foreach ($section['details'] as $item): ?><li><?= $esc($item) ?></li><?php endforeach; ?></ul>
   </details>
   <?php endforeach; ?>
  </div>
 </details>
 <?php endforeach; ?>
 <p class="footer">The next update is a preview, not an available release. Release 2 is for our closed-testing group. Features become available when the corresponding app version and supporting services are updated. <a href="<?= $esc($url) ?>">Return to CTSMD Connect</a>.</p>
 </main>
</body></html><?php
        exit;
    }
}
