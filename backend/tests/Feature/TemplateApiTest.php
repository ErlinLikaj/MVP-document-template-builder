<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_available_components(): void
    {
        $response = $this->getJson('/api/components');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['type', 'label', 'description', 'defaultGrid', 'defaultProps'],
                ],
            ]);

        $types = collect($response->json('data'))->pluck('type');

        $this->assertTrue($types->contains('header'));
        $this->assertTrue($types->contains('text'));
        $this->assertTrue($types->contains('image'));
        $this->assertTrue($types->contains('two_column'));
        $this->assertTrue($types->contains('footer'));
    }

    public function test_it_can_create_and_fetch_a_template(): void
    {
        $payload = [
            'name' => 'Invoice Layout',
            'description' => 'Simple invoice',
            'structure' => [
                'version' => 1,
                'sections' => [
                    [
                        'id' => 'h1',
                        'type' => 'header',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => ['title' => 'Invoice', 'subtitle' => '', 'align' => 'left'],
                    ],
                ],
            ],
        ];

        $create = $this->postJson('/api/templates', $payload);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Invoice Layout');

        $id = $create->json('data.id');

        $this->getJson("/api/templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.structure.sections.0.type', 'header');
    }

    public function test_it_can_render_preview_html(): void
    {
        $template = Template::factory()->create([
            'name' => 'Preview Doc',
        ]);

        $response = $this->post('/api/render/preview', [
            'template_id' => $template->id,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('section-block', $response->getContent());
    }

    public function test_it_can_generate_pdf_and_docx(): void
    {
        $template = Template::factory()->create([
            'name' => 'Export Sample',
        ]);

        $pdf = $this->post('/api/render/pdf', [
            'template_id' => $template->id,
        ]);

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertNotEmpty($pdf->streamedContent());

        $docx = $this->post('/api/render/docx', [
            'template_id' => $template->id,
        ]);

        $docx->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $docx->headers->get('Content-Type'),
        );
        $this->assertNotEmpty($docx->streamedContent());
    }
}
