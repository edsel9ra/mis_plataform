<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class IndexMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['sometimes', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
