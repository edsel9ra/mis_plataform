<?php

namespace App\Http\Requests\Relationship;

use Illuminate\Foundation\Http\FormRequest;

class IndexRelationshipContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'mentor_id' => ['nullable', 'uuid'],
        ];
    }
}
