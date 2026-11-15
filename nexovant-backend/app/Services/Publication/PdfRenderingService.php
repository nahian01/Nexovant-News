<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\Publication;
use App\Models\PublicationTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfRenderingService
{
    /**
     * Render and persist executive publication PDF document.
     */
    public function render(Publication $publication, ?PublicationTemplate $template = null): string
    {
        $template = $template ?? $publication->template ?? PublicationTemplate::where('is_default', true)->first();
        if (! $template) {
            $template = PublicationTemplate::first() ?? new PublicationTemplate([
                'page_size' => 'a4',
                'orientation' => 'portrait',
                'brand_config' => [
                    'logo_text' => 'NEXOVANT',
                    'header_text' => 'NEXOVANT GLOBAL INTELLIGENCE',
                    'footer_text' => 'VERIFIED INTELLIGENCE • ALL RIGHTS RESERVED',
                ],
                'confidentiality_label' => 'PREMIUM EXECUTIVE INTELLIGENCE',
            ]);
        }

        $html = view('publications.pdf_dossier', [
            'publication' => $publication,
            'template' => $template,
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper($template->page_size ?? 'a4', $template->orientation ?? 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('defaultFont', 'Helvetica');

        $binary = $pdf->output();

        // Ensure storage directory exists
        $relativeDir = "publications/{$publication->id}";
        $filename = Str::slug($publication->title) . '-edition.pdf';
        $fullPath = "{$relativeDir}/{$filename}";

        Storage::disk('local')->put($fullPath, $binary);

        $fileSize = strlen($binary);
        $pageCount = $this->calculatePageCount($binary);

        $publication->update([
            'file_path' => $fullPath,
            'file_size_bytes' => $fileSize,
            'page_count' => $pageCount,
        ]);

        return $fullPath;
    }

    /**
     * Read actual page count from generated PDF binary stream.
     */
    protected function calculatePageCount(string $pdfBinary): int
    {
        $count = preg_match_all("/\/Page\W/", $pdfBinary, $matches);
        return $count > 0 ? $count : 1;
    }
}
