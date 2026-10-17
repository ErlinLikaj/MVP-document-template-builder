<?php

namespace App\Services\Document;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfRenderer
{
    public function __construct(private HtmlRenderer $htmlRenderer) {}

    /**
     * @param  array{version?: int, sections?: list<array<string, mixed>>}  $structure
     * @param  array<string, mixed>  $content
     */
    public function render(array $structure, array $content = [], string $title = 'Document'): string
    {
        // Embed local uploads as base64. Fetching http://localhost from Dompdf
        // deadlocks php artisan serve (single-threaded).
        $html = $this->htmlRenderer
            ->forPdf()
            ->render($structure, $content, $title);

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
