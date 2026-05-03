<?php

namespace App\Controllers;

use App\Models\PdfJobModel;
use App\Services\PdfService;

class PdfJobController
{
    public function list(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $jobs      = PdfJobModel::listByProject($projectId);

        $this->json([
            'jobs' => array_map(fn($j) => [
                'id'         => $j['id'],
                'status'     => $j['status'],
                'outputUrl'  => $j['output_url'],
                'templateId' => $j['template_id'],
                'createdAt'  => $j['created_at'],
                'updatedAt'  => $j['updated_at'],
            ], $jobs),
        ]);
    }

    public function create(array $req): void
    {
        $projectId  = $req['params']['projectId'];
        $body       = $req['body'] ?? [];
        $templateId = $body['templateId'] ?? null;
        $input      = is_array($body['input'] ?? null) ? $body['input'] : [];
        if (!empty($body['title'])) {
            $input['title'] = $body['title'];
        }

        $id  = $this->uuid();
        $job = PdfJobModel::create($id, $projectId, $templateId, json_encode($input));

        $uploadsDir = dirname(__DIR__, 2) . '/uploads';
        $pdfSvc     = new PdfService($uploadsDir);

        try {
            $outputUrl   = $pdfSvc->render($projectId, $job['id'], $templateId, $input);
            $finalStatus = 'completed';
        } catch (\Throwable $e) {
            $outputUrl   = null;
            $finalStatus = 'failed';
            error_log('PDF generation failed: ' . $e->getMessage());
        }

        $updated = PdfJobModel::updateStatus($job['id'], $finalStatus, $outputUrl);

        $this->json([
            'job' => [
                'id'         => $updated['id'],
                'status'     => $updated['status'],
                'outputUrl'  => $updated['output_url'],
                'templateId' => $updated['template_id'],
                'createdAt'  => $updated['created_at'],
                'updatedAt'  => $updated['updated_at'],
            ],
        ], 201);
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
