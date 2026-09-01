<?php

namespace App\Http\Requests\Mentee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmotionalCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user() && !$this->user()->isMentor();
    }

    public function rules(): array
    {
        return [
            'mood_score' => ['required', 'integer', 'between:1,5'],
            'stress_score' => ['nullable', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:10000'],
            'checked_in_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
