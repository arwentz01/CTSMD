<?php

declare(strict_types=1);

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Response;
use App\Http\Router;
use App\Repository\AdminRepository;
use App\Repository\ChannelRepository;
use App\Repository\ModerationRepository;
use App\Repository\NotificationRepository;
use App\Repository\SafeguardingRepository;
use App\Support\Csrf;
use App\Support\Environment;
use App\View\View;

define('BASE_PATH', dirname(__DIR__));

if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $staticFile = __DIR__ . (is_string($requestedPath) ? $requestedPath : '');

    if (is_file($staticFile)) {
        return false;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

Environment::load(BASE_PATH . '/.env');
$app = require BASE_PATH . '/config/app.php';
$database = require BASE_PATH . '/config/database.php';
date_default_timezone_set($app['timezone']);
session_name($app['session_name']);
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

$pdo = Connection::make($database);
$auth = new Auth($pdo);
$admin = new AdminRepository($pdo);
$safeguarding = new SafeguardingRepository($pdo, $admin);
$channels = new ChannelRepository($pdo, $admin);
$notifications = new NotificationRepository($pdo, $admin);
$moderation = new ModerationRepository($pdo, $admin, $notifications);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
if ($app['base_path'] !== '' && str_starts_with($requestUri, $app['base_path'])) {
    $_SERVER['REQUEST_URI'] = substr($requestUri, strlen($app['base_path'])) ?: '/';
}

$flash = static function (?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }

    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_string($message) ? $message : null;
};

$requireUser = static function () use ($auth): array {
    $user = $auth->user();
    if ($user === null) {
        Response::redirect('/login');
    }

    return $user;
};
$requireAdmin = static function () use ($auth, $requireUser): array {
    $user = $requireUser();
    if (!$auth->hasAnyRole((int) $user['id'], ['owner', 'administrator', 'safeguarding_administrator'])) {
        Response::html(View::render('not-found', ['title' => 'Page not found', 'page' => '']), 404);
    }

    return $user;
};
$requireApiUser = static function () use ($auth): array {
    $user = $auth->user();
    if ($user === null) {
        Response::json(['data' => null, 'error' => ['message' => 'Authentication required.']], 401);
    }

    return $user;
};
$isAdminUser = static fn (array $user): bool => $auth->hasAnyRole(
    (int) $user['id'],
    ['owner', 'administrator', 'safeguarding_administrator']
);

$router = new Router();
$router->get('/', static fn () => Response::html(View::render('home', [
    'title' => 'A safer stage for connection',
    'page' => 'home',
    'app' => $app,
])));
$router->get('/login', static fn () => Response::html(View::render('login', [
    'title' => 'Sign in',
    'page' => 'login',
    'csrf' => Csrf::token(),
    'error' => null,
    'app' => $app,
])));
$router->post('/login', static function () use ($auth): never {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::html(View::render('login', ['title' => 'Sign in', 'page' => 'login', 'csrf' => Csrf::token(), 'error' => 'Please try signing in again.']), 419);
    }

    if ($auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        Response::redirect('/dashboard');
    }

    Response::html(View::render('login', ['title' => 'Sign in', 'page' => 'login', 'csrf' => Csrf::token(), 'error' => 'Those credentials did not match an active account.']), 422);
});
$router->post('/logout', static function () use ($auth): never {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    $auth->logout();
    Response::redirect('/');
});
$router->get('/setup', static function () use ($admin): never {
    if ($admin->hasUsers()) {
        Response::redirect('/login');
    }

    $app = require BASE_PATH . '/config/app.php';
    Response::html(View::render('setup', [
        'title' => 'Create owner',
        'page' => 'setup',
        'csrf' => Csrf::token(),
        'error' => null,
        'app' => $app,
    ]));
});
$router->post('/setup', static function () use ($admin, $auth): never {
    if ($admin->hasUsers()) {
        Response::redirect('/login');
    }

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::html(View::render('setup', ['title' => 'Create owner', 'page' => 'setup', 'csrf' => Csrf::token(), 'error' => 'Please try again.']), 419);
    }

    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 10) {
        Response::html(View::render('setup', ['title' => 'Create owner', 'page' => 'setup', 'csrf' => Csrf::token(), 'error' => 'Use at least 10 characters for the password.']), 422);
    }

    $admin->createOwner((string) ($_POST['email'] ?? ''), (string) ($_POST['first_name'] ?? ''), (string) ($_POST['last_name'] ?? ''), $password);
    $auth->attempt((string) ($_POST['email'] ?? ''), $password);
    Response::redirect('/admin');
});
$router->get('/accept-invite', static fn () => Response::html(View::render('accept-invite', [
    'title' => 'Accept invite',
    'page' => 'login',
    'csrf' => Csrf::token(),
    'token' => (string) ($_GET['token'] ?? ''),
    'error' => null,
])));
$router->post('/accept-invite', static function () use ($admin, $auth): never {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::html(View::render('accept-invite', ['title' => 'Accept invite', 'page' => 'login', 'csrf' => Csrf::token(), 'token' => (string) ($_POST['token'] ?? ''), 'error' => 'Please try again.']), 419);
    }

    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 10 || !$admin->acceptInvitation((string) ($_POST['token'] ?? ''), $password)) {
        Response::html(View::render('accept-invite', ['title' => 'Accept invite', 'page' => 'login', 'csrf' => Csrf::token(), 'token' => (string) ($_POST['token'] ?? ''), 'error' => 'That invite is invalid, expired, or the password is too short.']), 422);
    }

    Response::redirect('/login');
});
$router->get('/admin', static function () use ($admin, $safeguarding, $channels, $moderation, $notifications, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    Response::html(View::render('admin', [
        'title' => 'Admin overview',
        'page' => 'admin',
        'csrf' => Csrf::token(),
        'user' => $user,
        'counts' => $admin->counts(),
        'users' => $admin->users(),
        'adults' => $safeguarding->activeAdults(),
        'students' => $safeguarding->activeStudents(),
        'guardianLinks' => $safeguarding->guardianLinks(),
        'conversations' => $safeguarding->conversations(),
        'channels' => $channels->channels(),
        'reports' => $moderation->reports(),
        'notifications' => $notifications->pending(),
        'flash' => $flash(),
        'inviteUrl' => null,
    ]));
});
$router->get('/dashboard', static function () use ($auth, $admin, $safeguarding, $channels, $moderation, $notifications, $isAdminUser, $requireUser): never {
    $user = $requireUser();
    Response::html(View::render('dashboard', [
        'title' => 'Dashboard',
        'page' => 'dashboard',
        'user' => $user,
        'isAdmin' => $isAdminUser($user),
        'counts' => $admin->counts(),
        'roles' => $auth->roleCodes((int) $user['id']),
        'channels' => $channels->channels(),
        'recentPosts' => $channels->recentPosts(),
        'conversations' => $isAdminUser($user) ? $safeguarding->conversations() : $safeguarding->conversationsForUser((int) $user['id']),
        'reportCounts' => $moderation->counts(),
        'pendingNotifications' => $notifications->pendingCount(),
    ]));
});
$router->post('/admin/invitations', static function () use ($admin, $flash, $requireAdmin, $app): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    $roleCodes = $_POST['roles'] ?? [];
    $roleCodes = is_array($roleCodes) ? array_values(array_filter($roleCodes, 'is_string')) : ['general_member'];
    if ($roleCodes === []) {
        $roleCodes = ['general_member'];
    }

    $token = $admin->invite(
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['first_name'] ?? ''),
        (string) ($_POST['last_name'] ?? ''),
        isset($_POST['is_student']),
        $roleCodes,
        (int) $user['id']
    );
    $flash('Invite link created: ' . $app['url'] . '/accept-invite?token=' . $token);
    Response::redirect('/admin');
});
$router->post('/admin/channels', static function () use ($channels, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    try {
        $channelId = $channels->createChannel(
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['description'] ?? ''),
            (string) ($_POST['type'] ?? ''),
            (string) ($_POST['posting_policy'] ?? ''),
            (int) $user['id']
        );
        $flash('Channel #' . $channelId . ' created.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/admin#channels');
});
$router->post('/admin/channel-posts', static function () use ($channels, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    try {
        $postId = $channels->createPost(
            (int) ($_POST['channel_id'] ?? 0),
            (int) $user['id'],
            (string) ($_POST['body'] ?? ''),
            isset($_POST['is_pinned']),
            true
        );
        $flash('Channel post #' . $postId . ' published.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/admin#channels');
});
$router->post('/admin/channel-members', static function () use ($channels, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    try {
        $channels->addMember(
            (int) ($_POST['channel_id'] ?? 0),
            (int) ($_POST['user_id'] ?? 0),
            isset($_POST['can_post']),
            (int) $user['id']
        );
        $flash('Channel membership updated.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/admin#channels');
});
$router->post('/admin/reports/status', static function () use ($moderation, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    try {
        $moderation->updateStatus((int) ($_POST['report_id'] ?? 0), (string) ($_POST['status'] ?? ''), (int) $user['id']);
        $flash('Report status updated.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/admin#moderation');
});
$router->post('/admin/guardian-links', static function () use ($safeguarding, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    try {
        $safeguarding->linkGuardian(
            (int) ($_POST['guardian_user_id'] ?? 0),
            (int) ($_POST['student_user_id'] ?? 0),
            (string) ($_POST['relationship_label'] ?? ''),
            (int) $user['id']
        );
        $flash('Guardian link approved.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/admin#safeguarding');
});
$router->post('/admin/safeguarded-conversations', static function () use ($safeguarding, $flash, $requireAdmin): never {
    $user = $requireAdmin();
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/admin');
    }

    try {
        $conversationId = $safeguarding->createSafeguardedConversation(
            (int) ($_POST['adult_user_id'] ?? 0),
            (int) ($_POST['student_user_id'] ?? 0),
            (int) $user['id']
        );
        $flash('Safeguarded conversation #' . $conversationId . ' created with required guardian visibility.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/admin#safeguarding');
});
$router->get('/conversations', static function () use ($safeguarding, $flash, $requireUser): never {
    $user = $requireUser();
    $conversationId = (int) ($_GET['id'] ?? 0);
    $conversation = $safeguarding->conversationForUser($conversationId, (int) $user['id']);

    if ($conversation === null) {
        Response::html(View::render('not-found', ['title' => 'Page not found', 'page' => '']), 404);
    }

    Response::html(View::render('conversation', [
        'title' => 'Conversation',
        'page' => 'messages',
        'csrf' => Csrf::token(),
        'user' => $user,
        'conversation' => $conversation,
        'messages' => $safeguarding->messagesForUser($conversationId, (int) $user['id']),
        'flash' => $flash(),
    ]));
});
$router->post('/conversations/messages', static function () use ($safeguarding, $flash, $requireUser): never {
    $user = $requireUser();
    $conversationId = (int) ($_POST['conversation_id'] ?? 0);

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/conversations?id=' . $conversationId);
    }

    try {
        $safeguarding->postMessage($conversationId, (int) $user['id'], (string) ($_POST['body'] ?? ''));
        $flash('Message posted.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/conversations?id=' . $conversationId);
});
$router->post('/reports', static function () use ($moderation, $flash, $requireUser): never {
    $user = $requireUser();
    $returnTo = (string) ($_POST['return_to'] ?? '/');

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect($returnTo);
    }

    try {
        $moderation->report(
            (int) $user['id'],
            (string) ($_POST['subject_type'] ?? ''),
            (int) ($_POST['subject_id'] ?? 0),
            (string) ($_POST['reason'] ?? ''),
            (string) ($_POST['details'] ?? '')
        );
        $flash('Report submitted for safeguarding review.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect($returnTo);
});
$router->get('/channels', static function () use ($channels, $isAdminUser, $flash, $requireUser): never {
    $user = $requireUser();
    $channelId = (int) ($_GET['id'] ?? 0);
    $channel = $channels->channel($channelId);

    if ($channel === null) {
        Response::html(View::render('not-found', ['title' => 'Page not found', 'page' => '']), 404);
    }

    Response::html(View::render('channel', [
        'title' => (string) $channel['name'],
        'page' => 'channels',
        'csrf' => Csrf::token(),
        'user' => $user,
        'channel' => $channel,
        'posts' => $channels->posts($channelId),
        'members' => $channels->channelMembers($channelId),
        'canPost' => $channels->canPost($channelId, (int) $user['id'], $isAdminUser($user)),
        'flash' => $flash(),
    ]));
});
$router->post('/channels/posts', static function () use ($channels, $isAdminUser, $flash, $requireUser): never {
    $user = $requireUser();
    $channelId = (int) ($_POST['channel_id'] ?? 0);

    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Response::redirect('/channels?id=' . $channelId);
    }

    try {
        $channels->createPost($channelId, (int) $user['id'], (string) ($_POST['body'] ?? ''), false, $isAdminUser($user));
        $flash('Post published.');
    } catch (Throwable $throwable) {
        $flash($throwable->getMessage());
    }

    Response::redirect('/channels?id=' . $channelId);
});
$router->get('/api/v1/me', static function () use ($auth, $requireApiUser): never {
    $user = $requireApiUser();
    Response::json([
        'data' => [
            'user' => $user,
            'roles' => $auth->roleCodes((int) $user['id']),
        ],
        'error' => null,
    ]);
});
$router->get('/api/v1/channels', static function () use ($channels, $requireApiUser): never {
    $requireApiUser();
    Response::json(['data' => ['channels' => $channels->channels()], 'error' => null]);
});
$router->get('/api/v1/channel', static function () use ($channels, $isAdminUser, $requireApiUser): never {
    $user = $requireApiUser();
    $channelId = (int) ($_GET['id'] ?? 0);
    $channel = $channels->channel($channelId);

    if ($channel === null) {
        Response::json(['data' => null, 'error' => ['message' => 'Channel not found.']], 404);
    }

    Response::json([
        'data' => [
            'channel' => $channel,
            'posts' => $channels->posts($channelId),
            'members' => $channels->channelMembers($channelId),
            'can_post' => $channels->canPost($channelId, (int) $user['id'], $isAdminUser($user)),
        ],
        'error' => null,
    ]);
});
$router->get('/api/v1/conversations', static function () use ($safeguarding, $requireApiUser): never {
    $user = $requireApiUser();
    Response::json([
        'data' => ['conversations' => $safeguarding->conversationsForUser((int) $user['id'])],
        'error' => null,
    ]);
});
$router->get('/api/v1/conversation', static function () use ($safeguarding, $requireApiUser): never {
    $user = $requireApiUser();
    $conversationId = (int) ($_GET['id'] ?? 0);
    $conversation = $safeguarding->conversationForUser($conversationId, (int) $user['id']);

    if ($conversation === null) {
        Response::json(['data' => null, 'error' => ['message' => 'Conversation not found.']], 404);
    }

    Response::json([
        'data' => [
            'conversation' => $conversation,
            'messages' => $safeguarding->messagesForUser($conversationId, (int) $user['id']),
        ],
        'error' => null,
    ]);
});
$router->get('/health', static function () use ($database): never {
    $databaseStatus = 'ok';

    try {
        Connection::make($database)->query('SELECT 1');
    } catch (Throwable) {
        $databaseStatus = 'unavailable';
    }

    Response::json([
        'status' => $databaseStatus === 'ok' ? 'ok' : 'degraded',
        'service' => 'ctsmd-connect',
        'version' => 'build-010',
        'checks' => [
            'database' => $databaseStatus,
        ],
        'time' => gmdate(DATE_ATOM),
    ], $databaseStatus === 'ok' ? 200 : 503);
});

$result = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
if ($result === null) {
    Response::html(View::render('not-found', ['title' => 'Page not found', 'page' => '']), 404);
}
