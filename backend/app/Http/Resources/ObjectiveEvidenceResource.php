<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObjectiveEvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'objective_id' => $this->objective_id,
            'user_id' => $this->user_id,
            'note' => $this->note,
            'media_asset_id' => $this->media_asset_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->full_name,
            ]),
            'media_asset' => $this->whenLoaded('mediaAsset', fn () => MediaAssetResource::make($this->mediaAsset)),
        ];
    }
}
