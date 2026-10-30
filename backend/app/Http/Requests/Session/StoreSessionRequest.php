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
            'relationship_id' => ['required', 'uuid'],
            'session_type' => ['required', 'string', 'in:individual,family,group,corporate'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.config('scheduling.min_duration_minutes', 15),
                'max:'.config('scheduling.max_duration_minutes', 480),
            ],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'attendee_ids' => ['prohibited'],
        ];
    }
}
