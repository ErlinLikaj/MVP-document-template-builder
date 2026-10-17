<?php

namespace App\Http\Requests;

use App\Support\ComponentCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'structure' => ['required', 'array'],
            'structure.version' => ['nullable', 'integer', 'min:1'],
            'structure.sections' => ['required', 'array', 'min:1'],
            'structure.sections.*.id' => ['required', 'string', 'max:100'],
            'structure.sections.*.type' => ['required', 'string', Rule::in(ComponentCatalog::types())],
            'structure.sections.*.grid' => ['required', 'array'],
            'structure.sections.*.grid.colStart' => ['required', 'integer', 'min:1', 'max:12'],
            'structure.sections.*.grid.colSpan' => ['required', 'integer', 'min:1', 'max:12'],
            'structure.sections.*.props' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
