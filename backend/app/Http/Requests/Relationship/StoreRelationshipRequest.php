<?php

namespace App\Http\Requests\Relationship;

use Illuminate\Foundation\Http\FormRequest;

class StoreRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:personal,familiar,grupal,empresa'],
            'source_type' => ['required', 'string', 'in:user,family_group,cohort,company'],
            'source_id' => ['required', 'string'],
            'mentor_id' => ['required', 'exists:users,id'],
            'objectives' => ['nullable', 'string'],
        ];
    }
}
