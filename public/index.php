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

// ── Dispatch ──────────────────────────────────────────────────────────────────
$routeDefinitions = require __DIR__ . '/../src/Routes/api.php';
$dispatcher = FastRoute\simpleDispatcher($routeDefinitions);

$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $requestPath);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // SPA fallback — serve the shell for any unknown GET path.
            // Inject a <base> tag + window.__APP_BASE__ so the vanilla JS SPA works
            // correctly whether the project is at the server root or in a sub-folder
            // (e.g. http://localhost/erp_base_php/).
            //
            // SCRIPT_NAME example: /erp_base_php/public/index.php
            //   publicPath = /erp_base_php/public/   ← where styles.css / app.js live
            //   appBase    = /erp_base_php/           ← prefix for all API calls & SPA routes
            $scriptDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            $publicPath = rtrim($scriptDir, '/') . '/';
            $appBase    = rtrim(dirname(rtrim($scriptDir, '/')), '/') . '/';

            $html = file_get_contents(__DIR__ . '/index.html');
            $inject = '<base href="' . htmlspecialchars($publicPath, ENT_QUOTES) . '">'
                    . '<script>window.__APP_BASE__="' . addslashes($appBase) . '";</script>';
            $html = str_replace('</head>', $inject . '</head>', $html);

            header('Content-Type: text/html; charset=utf-8');
            echo $html;
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
        header('Content-Type: application/json; charset=utf-8');
        [$_status, [$middlewareList, [$controllerClass, $method]], $params] = $routeInfo;
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
