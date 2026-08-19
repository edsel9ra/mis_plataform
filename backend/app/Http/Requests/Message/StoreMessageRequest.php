<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['required', 'exists:mentorship_relationships,id'],
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', 'string', 'in:text,file,system'],
            'attachment_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
