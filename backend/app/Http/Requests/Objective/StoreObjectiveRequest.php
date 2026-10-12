<?php

namespace App\Http\Requests\Objective;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $collective = fn (): bool => $this->input('scope') === 'collective';

        return [
            'scope' => ['required', Rule::in(['individual', 'collective'])],
            'owner_id' => ['required', 'uuid'],
            'owner_type' => [
                'nullable',
                'string',
                Rule::in(['family_group', 'cohort', 'company']),
                Rule::requiredIf($collective),
                'prohibited_if:scope,individual',
            ],
            'relationship_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'success_criteria' => ['required', 'string', 'max:20000'],
            'due_at' => ['nullable', 'date'],

            // Non-administrable fields must never be supplied by the client.
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'proposed_by' => ['prohibited'],
            'achieved_at' => ['prohibited'],
        ];
    }
}
