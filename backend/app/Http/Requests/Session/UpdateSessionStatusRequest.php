<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string'],
            'reason' => ['required_if:status,canceled', 'string', 'not_regex:/^\s*$/u'],
            'mentor_notes' => ['nullable', 'string'],
            'mentee_notes' => ['nullable', 'string'],
        ];
    }
}
