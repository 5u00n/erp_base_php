<?php

namespace App\Controllers;

use App\Models\ContactModel;

class ContactController
{
    public function list(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $role      = $req['query']['role'] ?? null;
        $contacts  = ContactModel::listByProject($projectId, $role ?: null);

        $this->json([
            'contacts' => array_map(fn($c) => [
                'id'        => $c['id'],
                'email'     => $c['email'],
                'name'      => $c['name'],
                'meta'      => json_decode($c['meta'], true) ?? [],
                'createdAt' => $c['created_at'],
            ], $contacts),
        ]);
    }

    public function create(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $body      = $req['body'] ?? [];
        $email     = trim($body['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $meta = $body['meta'] ?? [];
        if (!is_array($meta)) $meta = [];
        if (!empty($body['role'])) {
            $meta['role'] = $body['role'];
        }

        try {
            $id = $this->uuid();
            $c  = ContactModel::create($id, $projectId, $email, $body['name'] ?? null, json_encode($meta));
            $this->json([
                'contact' => [
                    'id'        => $c['id'],
                    'email'     => $c['email'],
                    'name'      => $c['name'],
                    'meta'      => json_decode($c['meta'], true) ?? [],
                    'createdAt' => $c['created_at'],
                ],
            ], 201);
        } catch (\PDOException) {
            $this->json(['error' => 'duplicate_email'], 409);
        }
    }

    public function delete(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $contactId = $req['params']['contactId'];
        $n = ContactModel::delete($contactId, $projectId);
        if ($n === 0) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
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
