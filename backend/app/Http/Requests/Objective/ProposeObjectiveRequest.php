<?php

namespace App\Http\Requests\Objective;

use Illuminate\Foundation\Http\FormRequest;

class ProposeObjectiveRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'success_criteria' => ['required', 'string', 'max:20000'],
            // CA-1.3: a proposal to create an objective also needs a due date.
            'due_at' => ['required', 'date'],
            'relationship_id' => ['nullable', 'uuid'],

            // A proposer must not assign themselves an owner or status.
            'scope' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'owner_type' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'proposed_by' => ['prohibited'],
            'achieved_at' => ['prohibited'],
        ];
    }
}
