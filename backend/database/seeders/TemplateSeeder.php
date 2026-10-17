<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        Template::query()->create([
            'name' => 'Company Proposal',
            'description' => 'Example proposal template with header, body, image, two columns, and footer.',
            'structure' => [
                'version' => 1,
                'sections' => [
                    [
                        'id' => 'sec-header',
                        'type' => 'header',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'title' => 'Project Proposal',
                            'subtitle' => 'Prepared for Acme Corp',
                            'align' => 'left',
                        ],
                    ],
                    [
                        'id' => 'sec-intro',
                        'type' => 'text',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'content' => 'This proposal outlines the scope, timeline, and deliverables for the engagement.',
                            'align' => 'left',
                        ],
                    ],
                    [
                        'id' => 'sec-image',
                        'type' => 'image',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'src' => '',
                            'alt' => 'Architecture diagram',
                            'caption' => 'High-level solution overview',
                            'height' => 160,
                        ],
                    ],
                    [
                        'id' => 'sec-columns',
                        'type' => 'two_column',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'left' => "Scope\n- Discovery\n- Design\n- Implementation",
                            'right' => "Timeline\n- Week 1-2: Discovery\n- Week 3-5: Build\n- Week 6: Delivery",
                        ],
                    ],
                    [
                        'id' => 'sec-footer',
                        'type' => 'footer',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'text' => 'Confidential — Template Builder MVP',
                            'showPageNumber' => true,
                        ],
                    ],
                ],
            ],
            'settings' => [
                'pageSize' => 'A4',
            ],
        ]);
    }
}
