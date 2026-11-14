<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class IndexMentorUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'plan_filter' => ['nullable', 'string', 'in:all,basic,premium,locked'],
        ];
    }
}
