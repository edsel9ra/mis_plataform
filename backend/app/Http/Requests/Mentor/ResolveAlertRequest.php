<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['required', 'uuid'],
        ];
    }
}
