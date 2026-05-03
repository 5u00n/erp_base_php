<?php

/**
 * Front controller.
 * Boots the app, dispatches fast-route, runs the middleware stack, calls the controller.
 */

require __DIR__ . '/../vendor/autoload.php';

// Load .env (safeLoad = no error if file missing — env vars may be injected by host)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ── CORS ──────────────────────────────────────────────────────────────────────
$corsOrigin = $_ENV['CORS_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Serve uploaded PDFs directly ───────────────────────────────────────────────
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($requestPath, '/uploads/')) {
    $filePath = __DIR__ . '/..' . $requestPath;
    if (is_file($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

// ── Parse request body ─────────────────────────────────────────────────────────
$rawBody    = file_get_contents('php://input');
$parsedBody = [];
if ($rawBody !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $parsedBody = json_decode($rawBody, true) ?? [];
}

// ── Build normalised request context ──────────────────────────────────────────
$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[strtolower($name)] = $value;
}

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$queryParams = [];
parse_str($queryString, $queryParams);

$req = [
    'method'      => $_SERVER['REQUEST_METHOD'],
    'path'        => $requestPath,
    'headers'     => $headers,
    'body'        => $parsedBody,
    'query'       => $queryParams,
    'params'      => [],      // filled in after routing
    'user'        => null,
    'sessionUser' => null,
    'projectApi'  => null,
];

/**
 * SPA HTML shell with <base> + __APP_BASE__ (same root or sub-folder deploys).
 * When bootstrapped from the project root index.php, SCRIPT_NAME is .../index.php
 * (not .../public/index.php) — dirname would wrongly be the app base instead of
 * public/, so relative app.js / styles.css would load HTML and break module MIME checks.
 */
$serveSpaShell = static function (): void {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $publicFs   = realpath(__DIR__);
    $docRoot    = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($publicFs && $docRoot && $publicFs === $docRoot) {
        // DocumentRoot is this `public/` folder — assets are served from the URL dir of index.php.
        $dir = str_replace('\\', '/', dirname($scriptName));
        $publicPath = ($dir === '/' || $dir === '.') ? '/' : rtrim($dir, '/') . '/';
        $appBase    = $publicPath;
    } elseif (str_ends_with($scriptName, '/public/index.php')) {
        $publicPath = rtrim(dirname($scriptName), '/') . '/';
        $appBase    = rtrim(dirname(rtrim($publicPath, '/')), '/') . '/';
    } else {
        $publicPath = rtrim(dirname($scriptName), '/') . '/public/';
        $appBase    = rtrim(dirname(rtrim($publicPath, '/')), '/') . '/';
    }

    $html = file_get_contents(__DIR__ . '/index.html');
    // <base> must be the first URL-related child of <head> so relative href="styles.css" /
    // src="app.js" resolve against public/ even when the page URL is /projects/{id} etc.
    $inject = '<base href="' . htmlspecialchars($publicPath, ENT_QUOTES) . '">'
            . '<script>window.__APP_BASE__="' . addslashes($appBase) . '";</script>';
    $html = preg_replace('/<head\s*>/i', '$0' . $inject, $html, 1);

    header('Content-Type: text/html; charset=utf-8');
    echo $html;
};

// ── Dispatch ──────────────────────────────────────────────────────────────────
$routeDefinitions = require __DIR__ . '/../src/Routes/api.php';
$dispatcher = FastRoute\simpleDispatcher($routeDefinitions);

$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $requestPath);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // SPA fallback — serve the shell for any unknown GET path.
            $serveSpaShell();
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
        exit;

    case FastRoute\Dispatcher::FOUND:
        [$_status, [$middlewareList, [$controllerClass, $method]], $params] = $routeInfo;
        // The SPA route `/projects/:id` uses the same path as `GET /projects/{projectId}` (JSON API).
        // A full page refresh sends a document GET without `Authorization`; that would 401 before
        // the app loads. Browsers send `Sec-Fetch-Dest: document` (and usually `Sec-Fetch-Mode:
        // navigate`) for top-level navigations; same-origin `fetch()` does not.
        $secFetchDest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
        $secFetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
        $isDocumentNavigation = ($secFetchDest === 'document')
            || ($secFetchDest === '' && $secFetchMode === 'navigate');
        if (
            $_SERVER['REQUEST_METHOD'] === 'GET'
            && $controllerClass === \App\Controllers\ProjectController::class
            && $method === 'get'
            && $isDocumentNavigation
        ) {
            $serveSpaShell();
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        $req['params'] = $params;
        break;
}

// ── Middleware stack ─────────────────────────────────────────────────────────
foreach ($middlewareList as $mw) {
    if ($mw === 'requireAdmin') {
        // Inline admin check (avoids a separate class for a one-liner)
        if (!isset($req['user']) || $req['user']['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden']);
            exit;
        }
        continue;
    }

    if (is_array($mw)) {
        // [ClassName, constructorArg, ...]
        $class = $mw[0];
        $args  = array_slice($mw, 1);
        $instance = new $class(...$args);
    } else {
        $instance = new $mw();
    }

    $result = $instance($req);
    if ($result === false) {
        exit; // middleware already sent response
    }
}

// ── Controller ────────────────────────────────────────────────────────────────
$controller = new $controllerClass();
$controller->$method($req);
