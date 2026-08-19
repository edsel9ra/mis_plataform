<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['sometimes', 'exists:mentorship_relationships,id'],
            'session_type' => ['sometimes', 'string', 'in:individual,family,group,corporate'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'status' => ['sometimes', 'string', 'in:scheduled,in_progress,completed,canceled'],
        ];
    }
}
