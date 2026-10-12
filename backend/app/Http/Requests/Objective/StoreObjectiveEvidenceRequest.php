<?php

namespace App\Http\Requests\Objective;

use App\Models\Objective;
use Illuminate\Foundation\Http\FormRequest;

class StoreObjectiveEvidenceRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:20000', 'required_without:media_asset_id'],
            'media_asset_id' => ['nullable', 'uuid', 'exists:media_assets,id'],
        ];
    }
}
