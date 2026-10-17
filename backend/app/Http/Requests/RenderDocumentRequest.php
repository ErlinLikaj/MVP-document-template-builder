<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RenderDocumentRequest extends FormRequest
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
            'template_id' => ['nullable', 'integer', Rule::exists('templates', 'id')],
            'content' => ['nullable', 'array'],
            'structure' => ['nullable', 'array'],
            'structure.version' => ['nullable', 'integer', 'min:1'],
            'structure.sections' => ['nullable', 'array'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('template_id') && ! $this->filled('structure')) {
                $validator->errors()->add('template_id', 'Provide a template_id or a structure payload.');
            }
        });
    }
}
