<?php

namespace App\Http\Requests;

use App\Support\ComponentCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'structure' => ['sometimes', 'required', 'array'],
            'structure.version' => ['nullable', 'integer', 'min:1'],
            'structure.sections' => ['required_with:structure', 'array', 'min:1'],
            'structure.sections.*.id' => ['required_with:structure.sections', 'string', 'max:100'],
            'structure.sections.*.type' => ['required_with:structure.sections', 'string', Rule::in(ComponentCatalog::types())],
            'structure.sections.*.grid' => ['required_with:structure.sections', 'array'],
            'structure.sections.*.grid.colStart' => ['required_with:structure.sections.*.grid', 'integer', 'min:1', 'max:12'],
            'structure.sections.*.grid.colSpan' => ['required_with:structure.sections.*.grid', 'integer', 'min:1', 'max:12'],
            'structure.sections.*.props' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
