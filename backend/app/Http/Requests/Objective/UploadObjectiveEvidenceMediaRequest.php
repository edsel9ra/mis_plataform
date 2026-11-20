<?php

namespace App\Http\Requests\Objective;

use App\Models\Objective;
use App\Support\MediaUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class UploadObjectiveEvidenceMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective
            && (bool) $this->user()?->can('addEvidence', $objective);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => array_merge(['required'], MediaUploadRules::file()),
        ];
    }
}
