<?php

namespace App\Services\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

final class DocumentPdf
{
    public function render(array $record): string
    {
        $directory = storage_path('app/private/commercial-pdf');
        if (is_link($directory)) {
            throw new \RuntimeException('Unsafe PDF directory.');
        }
        File::ensureDirectoryExists($directory, 0700, true);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', $directory);
        $options->set('fontCache', $directory);
        $options->set('chroot', [public_path('branding'), base_path('vendor/dompdf'), $directory]);
        $pdf = new Dompdf($options);
        $logo = public_path('branding/homya-logo-v1.png');
        $logoData = is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null;
        $pdf->loadHtml(view('documents.pdf', ['record' => $record, 'data' => $record['data'], 'issuer' => $record['issuer'], 'logo' => $logoData])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->getCanvas()->page_text(35, 812, 'HOMYA · '.DocumentData::TYPES[$record['data']['type']].' · '.($record['number'] ?? 'BROUILLON NON ÉMIS'), $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.25, 0.25, 0.25]);
        $pdf->getCanvas()->page_text(490, 812, '{PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.25, 0.25, 0.25]);

        return $pdf->output();
    }
}
