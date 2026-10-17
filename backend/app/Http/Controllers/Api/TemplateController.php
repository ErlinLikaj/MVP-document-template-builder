<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $templates = Template::query()
            ->latest()
            ->get();

        return TemplateResource::collection($templates);
    }

    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = Template::query()->create($request->validated());

        return (new TemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Template $template): TemplateResource
    {
        return new TemplateResource($template);
    }

    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $template->update($request->validated());

        return new TemplateResource($template->refresh());
    }

    public function destroy(Template $template): Response
    {
        $template->delete();

        return response()->noContent();
    }
}
