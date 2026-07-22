<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill' => SkillTagResource::make($this->whenLoaded('skillTag')),
            'level' => $this->level,
        ];
    }
}
