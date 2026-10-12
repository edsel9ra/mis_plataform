<?php

namespace App\Http\Requests\Objective;

use App\Models\Objective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionObjectiveStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective
            && (bool) $this->user()?->can('transition', $objective);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'achieved', 'abandoned'])],
        ];
    }
}
