<?php

namespace App\Http\Requests\Objective;

use App\Models\MediaAsset;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $assetId = $this->input('media_asset_id');
            if (!$assetId) {
                return;
            }

            $objective = $this->route('objective');
            $asset = MediaAsset::find($assetId);

            $valid = $asset !== null
                && $objective instanceof Objective
                && $asset->purpose === 'objective_evidence'
                && $asset->mediaable instanceof Objective
                && $asset->mediaable->id === $objective->id
                && $asset->uploaded_by === $this->user()?->id
                && !ObjectiveEvidence::where('media_asset_id', $asset->id)->exists();

            if (!$valid) {
                $validator->errors()->add('media_asset_id', __('progress.messages.invalid_media_asset'));
            }
        });
    }
}
