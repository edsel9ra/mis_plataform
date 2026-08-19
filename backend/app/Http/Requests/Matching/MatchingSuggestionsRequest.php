<?php

namespace App\Http\Requests\Matching;

use Illuminate\Foundation\Http\FormRequest;

class MatchingSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'context_id' => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
