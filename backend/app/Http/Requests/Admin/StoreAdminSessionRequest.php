<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['required', 'exists:mentorship_relationships,id'],
            'session_type' => ['required', 'string', 'in:individual,family,group,corporate'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.config('scheduling.min_duration_minutes', 15),
                'max:'.config('scheduling.max_duration_minutes', 480),
            ],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'status' => ['sometimes', 'string', 'in:scheduled'],
        ];
    }
}
