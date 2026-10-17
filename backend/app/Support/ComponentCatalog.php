<?php

namespace App\Support;

class ComponentCatalog
{
    /**
     * Available builder blocks for the visual template editor.
     *
     * @return list<array{
     *     type: string,
     *     label: string,
     *     description: string,
     *     defaultGrid: array{colStart: int, colSpan: int},
     *     defaultProps: array<string, mixed>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'type' => 'header',
                'label' => 'Header',
                'description' => 'Page or section title with optional subtitle.',
                'defaultGrid' => ['colStart' => 1, 'colSpan' => 12],
                'defaultProps' => [
                    'title' => 'Document Title',
                    'subtitle' => '',
                    'align' => 'left',
                ],
            ],
            [
                'type' => 'text',
                'label' => 'Text block',
                'description' => 'Paragraph of body text.',
                'defaultGrid' => ['colStart' => 1, 'colSpan' => 12],
                'defaultProps' => [
                    'content' => 'Enter your text here.',
                    'align' => 'left',
                ],
            ],
            [
                'type' => 'image',
                'label' => 'Image placeholder',
                'description' => 'Image slot with optional caption.',
                'defaultGrid' => ['colStart' => 1, 'colSpan' => 12],
                'defaultProps' => [
                    'src' => '',
                    'alt' => 'Image',
                    'caption' => '',
                    'height' => 180,
                ],
            ],
            [
                'type' => 'two_column',
                'label' => 'Two-column layout',
                'description' => 'Side-by-side left and right content.',
                'defaultGrid' => ['colStart' => 1, 'colSpan' => 12],
                'defaultProps' => [
                    'left' => 'Left column content',
                    'right' => 'Right column content',
                ],
            ],
            [
                'type' => 'footer',
                'label' => 'Footer',
                'description' => 'Footer line with optional page note.',
                'defaultGrid' => ['colStart' => 1, 'colSpan' => 12],
                'defaultProps' => [
                    'text' => 'Confidential',
                    'showPageNumber' => true,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_column(self::all(), 'type');
    }
}
