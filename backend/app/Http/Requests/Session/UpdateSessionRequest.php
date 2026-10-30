<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Session;
use Illuminate\Support\Facades\Gate;

class UpdateSessionRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['prohibited'],
            'duration_minutes' => ['prohibited'],
            'timezone' => ['prohibited'],
            'end_at' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
