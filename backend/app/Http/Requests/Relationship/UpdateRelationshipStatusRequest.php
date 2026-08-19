<?php

namespace App\Http\Requests\Relationship;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRelationshipStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', 'in:active,paused,completed,canceled']];
    }
}
