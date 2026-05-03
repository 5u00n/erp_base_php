<?php

namespace App\Controllers;

use App\Models\ProjectModel;
use App\Services\TreeService;

class TreeDataController
{
    private TreeService $tree;

    public function __construct()
    {
        $this->tree = new TreeService();
    }

    public function get(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $dotPath   = $req['query']['path'] ?? null;

        try {
            $doc  = ProjectModel::getTreeData($projectId);
            $data = $this->tree->getPath($doc, $dotPath ?: null);
            $this->json(['data' => $data]);
        } catch (\Throwable) {
            $this->json(['error' => 'not_found'], 404);
        }
    }

    public function put(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];

        if (!array_key_exists('value', $body)) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        try {
            $doc  = ProjectModel::getTreeData($projectId);
            $path = $body['path'] ?? '';

            if ($path === '' || $path === null) {
                if (!is_array($body['value']) || array_is_list($body['value'])) {
                    $this->json(['error' => 'replace_requires_object'], 400);
                    return;
                }
                $updated = $this->tree->replaceDocument($body['value']);
            } else {
                $updated = $this->tree->setPath($doc, (string) $path, $body['value']);
            }

            ProjectModel::setTreeData($projectId, $updated);
            $this->json(['data' => $updated]);
        } catch (\Throwable) {
            $this->json(['error' => 'not_found'], 404);
        }
    }

    public function patch(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];

        if (!is_array($body)) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        try {
            $doc     = ProjectModel::getTreeData($projectId);
            $updated = $this->tree->patchMerge($doc, $body);
            ProjectModel::setTreeData($projectId, $updated);
            $this->json(['data' => $updated]);
        } catch (\Throwable) {
            $this->json(['error' => 'not_found'], 404);
        }
    }

    public function delete(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $dotPath   = $req['query']['path'] ?? '';

        if ($dotPath === '') {
            $this->json(['error' => 'path_required'], 400);
            return;
        }

        try {
            $doc     = ProjectModel::getTreeData($projectId);
            $updated = $this->tree->deletePath($doc, $dotPath);
            ProjectModel::setTreeData($projectId, $updated);
            $this->json(['data' => $updated]);
        } catch (\Throwable) {
            $this->json(['error' => 'not_found'], 404);
        }
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
