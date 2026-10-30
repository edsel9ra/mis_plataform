<?php

namespace App\Http\Requests\Session;

use App\Models\Session;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RescheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        Gate::authorize('manage', Session::query()->findOrFail((string) $this->route('id')));

        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => [
                'sometimes',
                'integer',
                'min:'.config('scheduling.min_duration_minutes', 15),
                'max:'.config('scheduling.max_duration_minutes', 480),
            ],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
