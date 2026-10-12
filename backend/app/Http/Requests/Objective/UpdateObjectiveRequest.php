<?php

namespace App\Http\Requests\Objective;

use App\Models\Objective;
use Illuminate\Foundation\Http\FormRequest;

class UpdateObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective
            && (bool) $this->user()?->can('update', $objective);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'success_criteria' => ['sometimes', 'required', 'string', 'max:20000'],
            'due_at' => ['nullable', 'date'],

            // Ownership, scope and status are immutable through this endpoint.
            'scope' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'owner_type' => ['prohibited'],
            'relationship_id' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'proposed_by' => ['prohibited'],
            'achieved_at' => ['prohibited'],
        ];
    }
}
