<?php

namespace App\Services\Document;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

class DocxRenderer
{
    /**
     * Mirror the Preview/PDF section-card layout as closely as Word allows.
     *
     * @param  array{version?: int, sections?: list<array<string, mixed>>}  $structure
     * @param  array<string, mixed>  $content
     */
    public function render(array $structure, array $content = [], string $title = 'Document'): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(1.5),
            'marginRight' => Converter::cmToTwip(1.5),
            'pageSizeW' => Converter::cmToTwip(21.0),
            'pageSizeH' => Converter::cmToTwip(29.7),
        ]);

        $blocks = array_values(array_filter(
            $structure['sections'] ?? [],
            static fn ($block): bool => is_array($block),
        ));
        $total = count($blocks);

        foreach ($blocks as $index => $block) {
            $this->renderSectionCard($section, $block, $content);

            if ($index < $total - 1) {
                $section->addTextBreak(1);
            }
        }

        return $this->saveToBinary($phpWord);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $content
     */
    private function renderSectionCard(Section $section, array $block, array $content): void
    {
        $type = (string) ($block['type'] ?? 'text');
        $props = $this->mergeProps($block, $content);

        // No "Section X of Y" meta labels in Word — only content.
        match ($type) {
            'header' => $this->renderHeader($section, $props),
            'image' => $this->renderImage($section, $props),
            'two_column' => $this->renderTwoColumn($section, $props),
            'footer' => $this->renderFooter($section, $props),
            default => $this->renderText($section, $props),
        };
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function mergeProps(array $block, array $content): array
    {
        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
        $id = (string) ($block['id'] ?? '');

        if ($id !== '' && isset($content[$id]) && is_array($content[$id])) {
            return array_merge($props, $content[$id]);
        }

        return $props;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderHeader(mixed $cell, array $props): void
    {
        $title = trim((string) ($props['title'] ?? ''));
        $subtitle = trim((string) ($props['subtitle'] ?? ''));
        $align = $this->alignment($props['align'] ?? 'left');

        $cell->addTextBreak(1);

        if ($title !== '') {
            $cell->addText($title, [
                'name' => 'Arial',
                'size' => 26,
                'bold' => true,
                'color' => '0F172A',
            ], [
                'alignment' => $align,
                'spaceAfter' => 60,
            ]);
        }

        if ($subtitle !== '') {
            $cell->addText($subtitle, [
                'name' => 'Arial',
                'size' => 12,
                'italic' => true,
                'color' => '5B6775',
            ], [
                'alignment' => $align,
                'spaceAfter' => 100,
            ]);
        }

        $line = $cell->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 0,
            'unit' => TblWidth::TWIP,
            'width' => 9200,
        ]);
        $line->addRow(60);
        $line->addCell(2800, ['bgColor' => '0F766E'])->addText(' ', ['size' => 1]);
        $line->addCell(6400, ['bgColor' => 'E2E8F0'])->addText(' ', ['size' => 1]);

        $cell->addTextBreak(1);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderText(mixed $cell, array $props): void
    {
        $content = trim((string) ($props['content'] ?? ''));
        $align = $this->alignment($props['align'] ?? 'left');

        $cell->addTextBreak(1);

        foreach ($this->lines($content) as $line) {
            $cell->addText($line === '' ? ' ' : $line, [
                'name' => 'Arial',
                'size' => 12,
                'color' => '1E293B',
            ], [
                'alignment' => $align,
                'spaceAfter' => 100,
            ]);
        }

        $cell->addTextBreak(1);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderImage(mixed $cell, array $props): void
    {
        $src = (string) ($props['src'] ?? '');
        $alt = trim((string) ($props['alt'] ?? 'Image'));
        $caption = trim((string) ($props['caption'] ?? ''));
        $height = max(80, min(600, (int) ($props['height'] ?? 180)));

        $cell->addTextBreak(1);

        $imagePath = $this->resolveImagePath($src);
        if ($imagePath !== null) {
            try {
                $options = [
                    'width' => 480,
                    'height' => (int) round($height * 0.75),
                    'alignment' => Jc::CENTER,
                ];
                $cell->addImage($imagePath['path'], $options);
            } catch (\Throwable) {
                $this->addImagePlaceholder($cell, $alt !== '' ? $alt : 'Image');
            } finally {
                if ($imagePath['temporary'] && is_file($imagePath['path'])) {
                    @unlink($imagePath['path']);
                }
            }
        } else {
            $this->addImagePlaceholder($cell, $alt !== '' ? $alt : 'Image');
        }

        if ($caption !== '') {
            $cell->addText($caption, [
                'name' => 'Arial',
                'size' => 9,
                'italic' => true,
                'color' => '6B7785',
            ], [
                'alignment' => Jc::CENTER,
                'spaceBefore' => 80,
            ]);
        }

        $cell->addTextBreak(1);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderTwoColumn(mixed $cell, array $props): void
    {
        $left = trim((string) ($props['left'] ?? ''));
        $right = trim((string) ($props['right'] ?? ''));

        $cell->addTextBreak(1);

        $table = $cell->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 100,
            'unit' => TblWidth::TWIP,
            'width' => 9200,
        ]);
        $table->addRow();

        $style = [
            'valign' => 'top',
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
        ];

        $leftCell = $table->addCell(4400, $style);
        foreach ($this->lines($left) as $line) {
            $leftCell->addText($line === '' ? ' ' : $line, [
                'name' => 'Arial',
                'size' => 11,
                'color' => '1E293B',
            ], ['spaceAfter' => 40]);
        }

        $table->addCell(400)->addText(' ');

        $rightCell = $table->addCell(4400, $style);
        foreach ($this->lines($right) as $line) {
            $rightCell->addText($line === '' ? ' ' : $line, [
                'name' => 'Arial',
                'size' => 11,
                'color' => '1E293B',
            ], ['spaceAfter' => 40]);
        }

        $cell->addTextBreak(1);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderFooter(mixed $cell, array $props): void
    {
        $text = trim((string) ($props['text'] ?? ''));
        $showPage = ! empty($props['showPageNumber']);

        $cell->addTextBreak(1);

        $table = $cell->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'borderTopSize' => 6,
            'borderTopColor' => 'D8E0EA',
            'cellMargin' => 40,
            'unit' => TblWidth::TWIP,
            'width' => 9200,
        ]);
        $table->addRow();
        $table->addCell(6000)->addText($text, [
            'name' => 'Arial',
            'size' => 9,
            'color' => '6B7785',
        ], ['spaceBefore' => 80]);

        $right = $table->addCell(3200);
        if ($showPage) {
            $right->addText('Page', [
                'name' => 'Arial',
                'size' => 9,
                'color' => '6B7785',
            ], [
                'alignment' => Jc::END,
                'spaceBefore' => 80,
            ]);
        }

        $cell->addTextBreak(1);
    }

    private function addImagePlaceholder(mixed $cell, string $alt): void
    {
        $cell->addText($alt, [
            'name' => 'Arial',
            'size' => 11,
            'color' => '6B7785',
            'italic' => true,
        ], ['alignment' => Jc::CENTER, 'spaceBefore' => 120, 'spaceAfter' => 120]);
    }

    private function alignment(mixed $align): string
    {
        return match ((string) $align) {
            'center' => Jc::CENTER,
            'right' => Jc::END,
            default => Jc::START,
        };
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * @return array{path: string, temporary: bool}|null
     */
    private function resolveImagePath(string $src): ?array
    {
        if ($src === '' || $src === 'null') {
            return null;
        }

        $path = parse_url($src, PHP_URL_PATH) ?? '';
        if (preg_match('#/storage/(.+)$#', $path, $matches) === 1) {
            $local = storage_path('app/public/'.$matches[1]);
            if (is_file($local)) {
                return ['path' => $local, 'temporary' => false];
            }
        }

        if (str_starts_with($src, 'data:image/')) {
            if (preg_match('#^data:image/(\w+);base64,(.+)$#', $src, $matches) !== 1) {
                return null;
            }
            $binary = base64_decode($matches[2], true);
            if ($binary === false) {
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'img_');
            if ($tmp === false) {
                return null;
            }
            $out = $tmp.'.'.$matches[1];
            rename($tmp, $out);
            file_put_contents($out, $binary);

            return ['path' => $out, 'temporary' => true];
        }

        if (preg_match('#^https?://#i', $src) === 1) {
            $context = stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 1]]);
            $bytes = @file_get_contents($src, false, $context);
            if ($bytes === false || $bytes === '') {
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'img_');
            if ($tmp === false) {
                return null;
            }
            file_put_contents($tmp, $bytes);

            return ['path' => $tmp, 'temporary' => true];
        }

        return null;
    }

    private function saveToBinary(PhpWord $phpWord): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create temporary DOCX file.');
        }

        $docxPath = $tempPath.'.docx';
        rename($tempPath, $docxPath);

        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);
            $binary = file_get_contents($docxPath);
            if ($binary === false) {
                throw new \RuntimeException('Unable to read generated DOCX file.');
            }

            return $binary;
        } finally {
            if (is_file($docxPath)) {
                unlink($docxPath);
            }
        }
    }
}
