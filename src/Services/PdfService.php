<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF generation service using dompdf.
 * Mirrors the Node.js renderPdf() helper in server/src/lib/pdf.ts.
 */
class PdfService
{
    private string $uploadsDir;

    public function __construct(string $uploadsDir)
    {
        $this->uploadsDir = rtrim($uploadsDir, '/');
    }

    /**
     * Renders an HTML template to PDF and writes it to uploads/{projectId}/{jobId}.pdf.
     * Returns the relative public URL path.
     */
    public function render(
        string $projectId,
        string $jobId,
        ?string $templateId,
        array $inputMeta
    ): string {
        $html = $this->buildHtml($templateId, $inputMeta);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $dir = $this->uploadsDir . '/' . $projectId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename  = $jobId . '.pdf';
        $localPath = $dir . '/' . $filename;
        file_put_contents($localPath, $pdf->output());

        return '/uploads/' . $projectId . '/' . $filename;
    }

    private function buildHtml(?string $templateId, array $inputMeta): string
    {
        $title = htmlspecialchars($inputMeta['title'] ?? 'Document', ENT_QUOTES);
        $rows  = '';
        foreach ($inputMeta as $k => $v) {
            $key = htmlspecialchars((string) $k, ENT_QUOTES);
            $val = htmlspecialchars(is_array($v) ? json_encode($v) : (string) $v, ENT_QUOTES);
            $rows .= "<tr><th>$key</th><td>$val</td></tr>";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="utf-8">
          <title>$title</title>
          <style>
            body { font-family: Helvetica, sans-serif; margin: 40px; }
            h1   { font-size: 22px; margin-bottom: 16px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
            th { background: #f0f0f0; width: 30%; }
          </style>
        </head>
        <body>
          <h1>$title</h1>
          <p style="color:#888;font-size:12px">Template: {$templateId}</p>
          <table>$rows</table>
        </body>
        </html>
        HTML;
    }
}
