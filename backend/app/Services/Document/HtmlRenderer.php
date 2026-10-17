<?php

namespace App\Services\Document;

class HtmlRenderer
{
    /**
     * @param  bool  $embedLocalImages  Embed /storage images as base64 (needed for Dompdf).
     * @param  bool  $showSectionLabels  Builder meta like "Section 3 of 6 · image" (preview only).
     */
    public function __construct(
        private bool $embedLocalImages = false,
        private bool $showSectionLabels = false,
    ) {}

    /**
     * Same markup + CSS for Preview and PDF so output matches 1:1.
     *
     * @param  array{version?: int, sections?: list<array<string, mixed>>}  $structure
     * @param  array<string, mixed>  $content
     */
    public function render(array $structure, array $content = [], string $title = 'Document'): string
    {
        $sections = $structure['sections'] ?? [];
        $total = count($sections);
        $blocks = '';

        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $blocks .= $this->renderSection($section, $content, $index, $total);
        }

        $escapedTitle = e($title);
        $css = $this->sharedCss();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$escapedTitle}</title>
    <style>{$css}</style>
</head>
<body>
    <div class="document">
        {$blocks}
    </div>
</body>
</html>
HTML;
    }

    public function forPdf(): self
    {
        return new self(embedLocalImages: true, showSectionLabels: false);
    }

    /**
     * Preview embeds local uploads; optional section labels for builder context.
     */
    public function forPreview(): self
    {
        return new self(embedLocalImages: true, showSectionLabels: false);
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $content
     */
    private function renderSection(array $section, array $content, int $index, int $total): string
    {
        $type = (string) ($section['type'] ?? 'text');
        $props = is_array($section['props'] ?? null) ? $section['props'] : [];
        $grid = is_array($section['grid'] ?? null) ? $section['grid'] : [];
        $id = (string) ($section['id'] ?? '');

        if ($id !== '' && isset($content[$id]) && is_array($content[$id])) {
            $props = array_merge($props, $content[$id]);
        }

        $colStart = max(1, min(12, (int) ($grid['colStart'] ?? 1)));
        $colSpan = max(1, min(12 - $colStart + 1, (int) ($grid['colSpan'] ?? 12)));
        $leftCols = $colStart - 1;
        $rightCols = 12 - $leftCols - $colSpan;
        $leftPct = round(($leftCols / 12) * 100, 4);
        $spanPct = round(($colSpan / 12) * 100, 4);
        $rightPct = round(($rightCols / 12) * 100, 4);

        $inner = match ($type) {
            'header' => $this->renderHeader($props),
            'image' => $this->renderImage($props),
            'two_column' => $this->renderTwoColumn($props),
            'footer' => $this->renderFooter($props),
            default => $this->renderText($props),
        };

        $labelRow = '';
        if ($this->showSectionLabels) {
            $sectionNo = $index + 1;
            $typeLabel = e(str_replace('_', ' ', $type));
            $gridHint = "col {$colStart} · span {$colSpan}/12";
            $labelRow = <<<HTML
    <tr>
        <td class="section-label">Section {$sectionNo} of {$total} · {$typeLabel} · {$gridHint}</td>
    </tr>
HTML;
        }

        // Width follows the 12-col resize; no card frame in export.
        $card = <<<HTML
<table class="section-block" width="100%" cellspacing="0" cellpadding="0">
{$labelRow}
    <tr>
        <td class="section-body">{$inner}</td>
    </tr>
</table>
HTML;

        $leftCell = $leftCols > 0
            ? "<td class=\"grid-spacer\" width=\"{$leftPct}%\">&nbsp;</td>"
            : '';
        $rightCell = $rightCols > 0
            ? "<td class=\"grid-spacer\" width=\"{$rightPct}%\">&nbsp;</td>"
            : '';

        return <<<HTML
<table class="grid-row" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        {$leftCell}
        <td class="grid-slot" width="{$spanPct}%" valign="top">{$card}</td>
        {$rightCell}
    </tr>
</table>
HTML;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderHeader(array $props): string
    {
        $align = $this->alignClass($props['align'] ?? 'left');
        $title = e((string) ($props['title'] ?? ''));
        $subtitle = e((string) ($props['subtitle'] ?? ''));
        $subtitleHtml = $subtitle !== '' ? "<p class=\"subtitle\">{$subtitle}</p>" : '';

        return <<<HTML
<div class="header {$align}">
    <h1>{$title}</h1>
    {$subtitleHtml}
    <table class="accent-line" width="100%" cellspacing="0" cellpadding="0"><tr>
        <td class="accent-strong" width="30%">&nbsp;</td>
        <td class="accent-soft" width="70%">&nbsp;</td>
    </tr></table>
</div>
HTML;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderText(array $props): string
    {
        $align = $this->alignClass($props['align'] ?? 'left');
        $content = nl2br(e((string) ($props['content'] ?? '')));

        return "<div class=\"text {$align}\"><p>{$content}</p></div>";
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderImage(array $props): string
    {
        $src = (string) ($props['src'] ?? '');
        $alt = e((string) ($props['alt'] ?? 'Image'));
        $caption = e((string) ($props['caption'] ?? ''));
        $height = max(80, (int) ($props['height'] ?? 180));
        $captionHtml = $caption !== '' ? "<div class=\"caption\">{$caption}</div>" : '';

        $resolved = $this->resolveImageSrc($src);

        if ($resolved !== null) {
            $safeSrc = e($resolved);

            return "<div class=\"image-wrap\" style=\"height:{$height}px;\"><img src=\"{$safeSrc}\" alt=\"{$alt}\" style=\"width:100%;height:{$height}px;object-fit:cover;\"></div>{$captionHtml}";
        }

        return "<div class=\"image-placeholder\" style=\"height:{$height}px;line-height:{$height}px;\">{$alt}</div>{$captionHtml}";
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderTwoColumn(array $props): string
    {
        $left = nl2br(e((string) ($props['left'] ?? '')));
        $right = nl2br(e((string) ($props['right'] ?? '')));

        return <<<HTML
<table class="two-column" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td class="col" width="48%" valign="top">{$left}</td>
        <td width="4%">&nbsp;</td>
        <td class="col" width="48%" valign="top">{$right}</td>
    </tr>
</table>
HTML;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderFooter(array $props): string
    {
        $text = e((string) ($props['text'] ?? ''));
        $page = ! empty($props['showPageNumber']) ? 'Page' : '';

        return <<<HTML
<table class="footer" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td>{$text}</td>
        <td class="footer-right">{$page}</td>
    </tr>
</table>
HTML;
    }

    private function resolveImageSrc(string $src): ?string
    {
        if ($src === '' || $src === 'null') {
            return null;
        }

        if (str_starts_with($src, 'data:image/')) {
            return $src;
        }

        $localPath = $this->localStoragePath($src);
        if ($localPath !== null && $this->embedLocalImages) {
            $mime = mime_content_type($localPath) ?: 'image/jpeg';
            $bytes = file_get_contents($localPath);
            if ($bytes !== false) {
                return 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }

        if (! $this->embedLocalImages && $this->isSafeImageSrc($src)) {
            return $src;
        }

        // Prefer local file even for preview when available (avoids host mismatch).
        if ($localPath !== null) {
            $mime = mime_content_type($localPath) ?: 'image/jpeg';
            $bytes = file_get_contents($localPath);
            if ($bytes !== false) {
                return 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        }

        return null;
    }

    private function localStoragePath(string $src): ?string
    {
        $path = parse_url($src, PHP_URL_PATH) ?: $src;

        if (preg_match('#/storage/(.+)$#', $path, $matches) !== 1) {
            return null;
        }

        $local = storage_path('app/public/'.$matches[1]);

        return is_file($local) ? $local : null;
    }

    private function alignClass(mixed $align): string
    {
        return match ((string) $align) {
            'center' => 'align-center',
            'right' => 'align-right',
            default => 'align-left',
        };
    }

    private function isSafeImageSrc(string $src): bool
    {
        return (bool) preg_match('#^(https?://|data:image/)#i', $src);
    }

    private function sharedCss(): string
    {
        // Clean export styles: no card frames / gray page chrome.
        return <<<'CSS'
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px 32px;
            background: #ffffff;
            color: #18212b;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            line-height: 1.55;
            font-size: 14px;
        }
        .document { width: 100%; max-width: 760px; margin: 0 auto; }
        .grid-row { width: 100%; border-collapse: collapse; margin: 0 0 16px 0; }
        .grid-spacer { font-size: 1px; line-height: 1px; }
        .grid-slot { vertical-align: top; }
        .section-block {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: transparent;
            border: none;
        }
        .section-label {
            padding: 0 0 8px;
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #7b8796;
            background: transparent;
            border: none;
        }
        .section-body { padding: 0; }
        .header h1 {
            margin: 0 0 8px;
            font-size: 28px;
            line-height: 1.2;
            color: #0f172a;
        }
        .header .subtitle { margin: 0 0 14px; color: #5b6775; font-size: 13px; font-style: italic; }
        .accent-line { margin-top: 4px; border-collapse: collapse; }
        .accent-strong { background: #0f766e; height: 4px; font-size: 1px; line-height: 4px; }
        .accent-soft { background: #e2e8f0; height: 4px; font-size: 1px; line-height: 4px; }
        .text p { margin: 0; font-size: 14px; color: #1e293b; }
        .image-wrap { text-align: center; }
        .image-wrap img { max-width: 100%; height: auto; display: inline-block; }
        .image-placeholder {
            border: none;
            background: transparent;
            color: #6b7785;
            text-align: center;
            font-size: 13px;
        }
        .caption { margin-top: 8px; font-size: 11px; color: #6b7785; text-align: center; font-style: italic; }
        .two-column { width: 100%; border-collapse: collapse; }
        .two-column .col {
            background: transparent;
            border: none;
            padding: 0 8px 0 0;
            font-size: 13px;
            color: #1e293b;
        }
        .footer {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #d8e0ea;
            margin-top: 4px;
        }
        .footer td {
            padding-top: 12px;
            font-size: 11px;
            color: #6b7785;
        }
        .footer-right { text-align: right; }
        .align-center { text-align: center; }
        .align-right { text-align: right; }
        .align-left { text-align: left; }
CSS;
    }
}
