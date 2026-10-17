<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenderDocumentRequest;
use App\Models\Template;
use App\Services\Document\DocumentRenderer;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenderController extends Controller
{
    public function __construct(private DocumentRenderer $renderer) {}

    public function preview(RenderDocumentRequest $request): Response
    {
        $template = $this->resolveTemplate($request);
        $html = $this->renderer->preview(
            $template,
            $request->validated('content') ?? [],
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function pdf(RenderDocumentRequest $request): StreamedResponse
    {
        return $this->download($request, 'pdf');
    }

    public function docx(RenderDocumentRequest $request): StreamedResponse
    {
        return $this->download($request, 'docx');
    }

    private function download(RenderDocumentRequest $request, string $format): StreamedResponse
    {
        $template = $this->resolveTemplate($request);
        $export = $this->renderer->export(
            $template,
            $format,
            $request->validated('content') ?? [],
        );

        return response()->streamDownload(
            function () use ($export): void {
                echo $export['binary'];
            },
            $export['filename'],
            [
                'Content-Type' => $export['mime'],
            ],
        );
    }

    private function resolveTemplate(RenderDocumentRequest $request): Template
    {
        if ($request->filled('template_id')) {
            $template = Template::query()->findOrFail($request->validated('template_id'));

            if ($request->filled('structure')) {
                $template->structure = $request->validated('structure');
            }

            if ($request->filled('name')) {
                $template->name = $request->validated('name');
            }

            return $template;
        }

        $template = new Template([
            'name' => $request->validated('name') ?: 'Preview',
            'structure' => $request->validated('structure'),
        ]);

        return $template;
    }
}
