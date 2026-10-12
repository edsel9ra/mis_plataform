<?php

namespace App\Http\Requests\Cohort;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCohortMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
