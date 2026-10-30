<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;

class CancelSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'not_regex:/^\s*$/u'],
        ];
    }
}
