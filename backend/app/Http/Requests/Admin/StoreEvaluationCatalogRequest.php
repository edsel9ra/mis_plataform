<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'module_id' => ['nullable', 'exists:path_modules,id'],
            'slug' => ['required', 'string', 'max:120', 'unique:assessments,slug'],
            'title' => ['required', 'array'],
            'title.es' => ['required', 'string', 'max:200'],
            'title.en' => ['nullable', 'string', 'max:200'],
            'title.pt' => ['nullable', 'string', 'max:200'],
            'short_name' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'category' => ['nullable', 'string', 'max:80'],
            'client_types' => ['nullable', 'array'],
            'client_types.*' => ['string', 'in:personal,familiar,grupal,empresa'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['nullable'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*' => ['required', 'string'],
            'questions.*.dimension' => ['nullable', 'string', 'max:120'],
            'questions.*.reverse_score' => ['nullable', 'boolean'],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
