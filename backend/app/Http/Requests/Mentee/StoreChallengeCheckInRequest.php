<?php

namespace App\Http\Requests\Mentee;

use Illuminate\Foundation\Http\FormRequest;

class StoreChallengeCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user() && !$this->user()->isMentor();
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('attention_score') && $this->has('attention')) {
            $this->merge(['attention_score' => $this->input('attention')]);
        }
    }

    public function rules(): array
    {
        $mentor = $this->user()?->isMentor() === true;

        return [
            'attention_score' => [$mentor ? 'nullable' : 'required', 'integer', 'between:1,5'],
            'status' => [$mentor ? 'required' : 'nullable', 'in:not_started,in_progress,completed,blocked'],
            'note' => ['nullable', 'string', 'max:10000'],
            'challenge_id' => ['nullable', 'uuid'],
            'checked_in_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
