<?php

namespace App\Http\Requests\Matching;

use Illuminate\Foundation\Http\FormRequest;

class MatchingCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'mentor_id' => ['required', 'exists:users,id'],
            'client_type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'context_id' => ['required', 'string'],
        ];
    }
}
