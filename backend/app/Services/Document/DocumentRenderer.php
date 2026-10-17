<?php

namespace App\Services\Document;

use App\Models\Template;
use InvalidArgumentException;

class DocumentRenderer
{
    public function __construct(
        private HtmlRenderer $htmlRenderer,
        private PdfRenderer $pdfRenderer,
        private DocxRenderer $docxRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function preview(Template $template, array $content = []): string
    {
        // Same HTML pipeline as PDF (embedded local images + shared CSS).
        return $this->htmlRenderer
            ->forPreview()
            ->render(
                $template->structure ?? [],
                $content,
                $template->name,
            );
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{binary: string, filename: string, mime: string}
     */
    public function export(Template $template, string $format, array $content = []): array
    {
        $structure = $template->structure ?? [];
        $slug = str($template->name)->slug()->toString() ?: 'document';

        return match (strtolower($format)) {
            'pdf' => [
                'binary' => $this->pdfRenderer->render($structure, $content, $template->name),
                'filename' => "{$slug}.pdf",
                'mime' => 'application/pdf',
            ],
            'docx' => [
                'binary' => $this->docxRenderer->render($structure, $content, $template->name),
                'filename' => "{$slug}.docx",
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            default => throw new InvalidArgumentException("Unsupported format [{$format}]."),
        };
    }
}
