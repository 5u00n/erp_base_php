<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\ProjectModel;
use App\Models\ApiKeyModel;
use App\Models\ContactModel;
use App\Models\PaymentModel;
use App\Models\PdfJobModel;
use App\Models\PushSubscriptionModel;

class AdminController
{
    public function stats(array $req): void
    {
        $this->json([
            'users'    => UserModel::count(),
            'projects' => ProjectModel::count(),
            'apiKeys'  => [
                'active' => ApiKeyModel::countTotalActive(),
                'total'  => ApiKeyModel::countTotal(),
            ],
            'contacts' => ContactModel::count(),
            'payments' => PaymentModel::count(),
            'pdfJobs'  => [
                'total'     => PdfJobModel::count(),
                'completed' => PdfJobModel::countCompleted(),
            ],
            'pushSubscriptions' => PushSubscriptionModel::countTotal(),
        ]);
    }

    public function listUsers(array $req): void
    {
        $users = UserModel::listAll();
        $out = array_map(fn($u) => [
            'id'        => $u['id'],
            'email'     => $u['email'],
            'role'      => $u['role'],
            'createdAt' => $u['created_at'],
            '_count'    => ['projects' => UserModel::countProjects($u['id'])],
        ], $users);
        $this->json(['users' => $out]);
    }

    public function listProjects(array $req): void
    {
        $projects = ProjectModel::listAllForAdmin();
        $out = array_map(fn($p) => [
            'id'        => $p['id'],
            'name'      => $p['name'],
            'slug'      => $p['slug'],
            'createdAt' => $p['created_at'],
            'owner'     => ['id' => $p['owner_id'], 'email' => $p['owner_email']],
            'counts'    => [
                'apiKeys'           => ApiKeyModel::countActive($p['id']) + ApiKeyModel::countRevoked($p['id']),
                'contacts'          => ContactModel::count(),
                'payments'          => PaymentModel::count(),
                'pdfJobs'           => PdfJobModel::count(),
                'pushSubscriptions' => PushSubscriptionModel::countByProject($p['id']),
            ],
        ], $projects);
        $this->json(['projects' => $out]);
    }

    public function keyStats(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $p = ProjectModel::findById($projectId);
        if (!$p) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }
        $this->json([
            'projectId' => $projectId,
            'keys' => [
                'active'  => ApiKeyModel::countActive($projectId),
                'revoked' => ApiKeyModel::countRevoked($projectId),
            ],
        ]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
