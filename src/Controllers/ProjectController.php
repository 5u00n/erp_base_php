<?php

namespace App\Controllers;

use App\Models\ProjectModel;
use App\Models\ApiKeyModel;

class ProjectController
{
    public function list(array $req): void
    {
        $userId   = $req['user']['id'];
        $projects = ProjectModel::findByOwner($userId);

        $out = array_map(function ($p) {
            return [
                'id'        => $p['id'],
                'name'      => $p['name'],
                'slug'      => $p['slug'],
                'createdAt' => $p['created_at'],
                'updatedAt' => $p['updated_at'],
                '_count'    => ['apiKeys' => ApiKeyModel::countActive($p['id']) + ApiKeyModel::countRevoked($p['id'])],
            ];
        }, $projects);

        $this->json(['projects' => $out]);
    }

    public function create(array $req): void
    {
        $body = $req['body'] ?? [];
        $name = trim($body['name'] ?? '');
        $slug = trim($body['slug'] ?? '');

        if ($name === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        try {
            $id = $this->uuid();
            $p  = ProjectModel::create($id, $req['user']['id'], $name, $slug);
            $this->json([
                'project' => [
                    'id'        => $p['id'],
                    'name'      => $p['name'],
                    'slug'      => $p['slug'],
                    'settings'  => json_decode($p['settings'], true) ?? [],
                    'createdAt' => $p['created_at'],
                ],
            ], 201);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $this->json(['error' => 'slug_exists'], 409);
            } else {
                $this->json(['error' => 'internal_error'], 500);
            }
        }
    }

    public function get(array $req): void
    {
        $p = ProjectModel::findByOwnerAndId($req['user']['id'], $req['params']['projectId']);
        if (!$p) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $this->json([
            'project' => [
                'id'        => $p['id'],
                'name'      => $p['name'],
                'slug'      => $p['slug'],
                'settings'  => json_decode($p['settings'], true) ?? [],
                'createdAt' => $p['created_at'],
                'updatedAt' => $p['updated_at'],
            ],
        ]);
    }

    public function update(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $p0 = ProjectModel::findByOwnerAndId($req['user']['id'], $projectId);
        if (!$p0) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }

        $body   = $req['body'] ?? [];
        $fields = [];
        if (isset($body['name']) && trim($body['name']) !== '') {
            $fields['name'] = trim($body['name']);
        }
        if (isset($body['settings']) && is_array($body['settings'])) {
            $fields['settings'] = json_encode($body['settings']);
        }

        $p = ProjectModel::update($projectId, $fields);
        $this->json([
            'project' => [
                'id'        => $p['id'],
                'name'      => $p['name'],
                'slug'      => $p['slug'],
                'settings'  => json_decode($p['settings'], true) ?? [],
                'updatedAt' => $p['updated_at'],
            ],
        ]);
    }

    public function delete(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $p = ProjectModel::findByOwnerAndId($req['user']['id'], $projectId);
        if (!$p) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        ProjectModel::delete($projectId);
        http_response_code(204);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}
