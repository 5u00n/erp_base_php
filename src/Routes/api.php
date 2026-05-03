<?php

/**
 * Route definitions for nikic/fast-route.
 *
 * Each route entry is:
 *   [METHOD, '/path', [MiddlewareList], [Controller::class, 'method']]
 *
 * This file returns a callable suitable for FastRoute\simpleDispatcher().
 * The front controller (public/index.php) reads this file, dispatches, then
 * runs the middleware stack before calling the controller method.
 */

use App\Controllers\AuthController;
use App\Controllers\ProjectController;
use App\Controllers\ApiKeyController;
use App\Controllers\TreeDataController;
use App\Controllers\AdminController;
use App\Controllers\ContactController;
use App\Controllers\PaymentController;
use App\Controllers\PdfJobController;
use App\Controllers\PushController;
use App\Controllers\DataStoreController;
use App\Controllers\EmailConfigController;
use App\Middleware\AuthenticateUser;
use App\Middleware\AuthenticateProjectAccess;
use App\Middleware\RequireScope;

$authUser    = [AuthenticateUser::class];
$authAdmin   = [AuthenticateUser::class, 'requireAdmin'];
$preRead     = [AuthenticateProjectAccess::class];
$preWrite    = [AuthenticateProjectAccess::class, [RequireScope::class, 'write']];

return function (\FastRoute\RouteCollector $r) use ($authUser, $authAdmin, $preRead, $preWrite) {

    // ── Auth ─────────────────────────────────────────────────────────────────
    $r->addRoute('POST', '/auth/register', [[], [AuthController::class, 'register']]);
    $r->addRoute('POST', '/auth/login',    [[], [AuthController::class, 'login']]);
    $r->addRoute('GET',  '/me',            [$authUser, [AuthController::class, 'me']]);
    $r->addRoute('PUT',  '/me/password',   [$authUser, [AuthController::class, 'changePassword']]);

    // ── Projects ──────────────────────────────────────────────────────────────
    $r->addRoute('GET',    '/projects',             [$authUser, [ProjectController::class, 'list']]);
    $r->addRoute('POST',   '/projects',             [$authUser, [ProjectController::class, 'create']]);
    $r->addRoute('GET',    '/projects/{projectId}', [$authUser, [ProjectController::class, 'get']]);
    $r->addRoute('PATCH',  '/projects/{projectId}', [$authUser, [ProjectController::class, 'update']]);
    $r->addRoute('DELETE', '/projects/{projectId}', [$authUser, [ProjectController::class, 'delete']]);

    // ── API Keys ─────────────────────────────────────────────────────────────
    $r->addRoute('GET',  '/projects/{projectId}/api-keys',             [$authUser, [ApiKeyController::class, 'list']]);
    $r->addRoute('POST', '/projects/{projectId}/api-keys',             [$authUser, [ApiKeyController::class, 'create']]);
    $r->addRoute('POST', '/projects/{projectId}/api-keys/{keyId}/revoke', [$authUser, [ApiKeyController::class, 'revoke']]);

    // ── Tree / JSON Data ─────────────────────────────────────────────────────
    $r->addRoute('GET',    '/projects/{projectId}/data', [$preRead,  [TreeDataController::class, 'get']]);
    $r->addRoute('PUT',    '/projects/{projectId}/data', [$preWrite, [TreeDataController::class, 'put']]);
    $r->addRoute('PATCH',  '/projects/{projectId}/data', [$preWrite, [TreeDataController::class, 'patch']]);
    $r->addRoute('DELETE', '/projects/{projectId}/data', [$preWrite, [TreeDataController::class, 'delete']]);

    // ── Admin ────────────────────────────────────────────────────────────────
    $r->addRoute('GET', '/admin/stats',                            [$authAdmin, [AdminController::class, 'stats']]);
    $r->addRoute('GET', '/admin/users',                            [$authAdmin, [AdminController::class, 'listUsers']]);
    $r->addRoute('GET', '/admin/projects',                         [$authAdmin, [AdminController::class, 'listProjects']]);
    $r->addRoute('GET', '/admin/projects/{projectId}/key-stats',   [$authAdmin, [AdminController::class, 'keyStats']]);

    // ── Contacts ─────────────────────────────────────────────────────────────
    $r->addRoute('GET',    '/projects/{projectId}/contacts',              [$preRead,  [ContactController::class, 'list']]);
    $r->addRoute('POST',   '/projects/{projectId}/contacts',              [$preWrite, [ContactController::class, 'create']]);
    $r->addRoute('DELETE', '/projects/{projectId}/contacts/{contactId}',  [$preWrite, [ContactController::class, 'delete']]);

    // ── Payments ─────────────────────────────────────────────────────────────
    $r->addRoute('GET',  '/projects/{projectId}/payments',         [$preRead,  [PaymentController::class, 'list']]);
    $r->addRoute('POST', '/projects/{projectId}/payments/intent',  [$preWrite, [PaymentController::class, 'createIntent']]);

    // ── PDF Jobs ─────────────────────────────────────────────────────────────
    $r->addRoute('GET',  '/projects/{projectId}/pdf/jobs', [$preRead,  [PdfJobController::class, 'list']]);
    $r->addRoute('POST', '/projects/{projectId}/pdf/jobs', [$preWrite, [PdfJobController::class, 'create']]);

    // ── Push ─────────────────────────────────────────────────────────────────
    $r->addRoute('GET',    '/projects/{projectId}/push/config',         [$preRead,  [PushController::class, 'getConfig']]);
    $r->addRoute('PUT',    '/projects/{projectId}/push/config',         [$preWrite, [PushController::class, 'putConfig']]);
    $r->addRoute('GET',    '/projects/{projectId}/push/subscriptions',  [$preRead,  [PushController::class, 'listSubscriptions']]);
    $r->addRoute('POST',   '/projects/{projectId}/push/subscribe',      [$preWrite, [PushController::class, 'subscribe']]);
    $r->addRoute('DELETE', '/projects/{projectId}/push/subscribe',      [$preWrite, [PushController::class, 'unsubscribe']]);
    $r->addRoute('POST',   '/projects/{projectId}/push/test',           [$preRead,  [PushController::class, 'test']]);

    // ── DataStore ────────────────────────────────────────────────────────────
    $r->addRoute('GET',  '/projects/{projectId}/db/config', [$preRead,  [DataStoreController::class, 'getConfig']]);
    $r->addRoute('PUT',  '/projects/{projectId}/db/config', [$preWrite, [DataStoreController::class, 'putConfig']]);
    $r->addRoute('POST', '/projects/{projectId}/db/test',   [$preRead,  [DataStoreController::class, 'testConnection']]);

    // ── Email ────────────────────────────────────────────────────────────────
    $r->addRoute('GET',  '/projects/{projectId}/email/config', [$preRead,  [EmailConfigController::class, 'getConfig']]);
    $r->addRoute('PUT',  '/projects/{projectId}/email/config', [$preWrite, [EmailConfigController::class, 'putConfig']]);
    $r->addRoute('POST', '/projects/{projectId}/email/test',   [$preRead,  [EmailConfigController::class, 'test']]);
    $r->addRoute('POST', '/projects/{projectId}/email/send',   [$preWrite, [EmailConfigController::class, 'send']]);
};
