<?php

/**
 * Bootstraps the app when DocumentRoot is this folder (project root).
 * Without this, a request to "/" often matches a directory, the rewrite
 * rule's "!-d" condition skips, and Apache returns 403 (no index).
 */
require __DIR__ . '/public/index.php';
