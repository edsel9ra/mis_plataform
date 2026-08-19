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
            'status' => ['sometimes', 'string', 'in:scheduled,in_progress,completed,canceled'],
            'mentor_notes' => ['nullable', 'string'],
            'mentee_notes' => ['nullable', 'string'],
        ];
    }
}
