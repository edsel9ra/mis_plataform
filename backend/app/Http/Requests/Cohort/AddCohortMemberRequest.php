<?php

namespace App\Http\Requests\Cohort;

use Illuminate\Foundation\Http\FormRequest;

class AddCohortMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
        ];
    }
}
