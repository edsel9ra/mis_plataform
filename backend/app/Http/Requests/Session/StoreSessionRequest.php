<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['required', 'exists:mentorship_relationships,id'],
            'session_type' => ['required', 'string', 'in:individual,family,group,corporate'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['exists:users,id'],
        ];
    }
}
