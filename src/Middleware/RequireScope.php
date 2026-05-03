<?php

namespace App\Middleware;

/**
 * Scope enforcement middleware.
 * JWT project owners always pass through.
 * API keys with empty scopes are unrestricted.
 * API keys with non-empty scopes must include the required scope.
 *
 * Mirrors server/src/plugins/auth.ts → requireScope()
 */
class RequireScope
{
    private string $scope;

    public function __construct(string $scope)
    {
        $this->scope = $scope;
    }

    public function __invoke(array &$req): bool
    {
        if (!isset($req['projectApi'])) {
            return true; // JWT owner — unrestricted
        }
        $scopes = $req['projectApi']['scopes'] ?? [];
        if (count($scopes) === 0) {
            return true; // empty = unrestricted legacy key
        }
        if (!in_array($this->scope, $scopes, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'insufficient_scope']);
            exit;
        }
        return true;
    }
}
