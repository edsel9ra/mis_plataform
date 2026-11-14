<?php

namespace App\Http\Resources;

use App\Services\Scheduling\Rules\SessionTypeRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookableRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'session_type' => SessionTypeRules::forRelationshipType($this->type),
            'is_collective' => ! in_array($this->source_type, ['user', \App\Models\User::class], true),
            'mentor' => [
                'id' => $this->mentor?->id,
                'full_name' => $this->mentor?->full_name,
            ],
            'source' => [
                'type' => $this->source_type,
                'name' => match ($this->source_type) {
                    'user', \App\Models\User::class => $this->source?->full_name,
                    'family_group', \App\Models\FamilyGroup::class => $this->source?->family_name,
                    'cohort', \App\Models\Cohort::class => $this->source?->name,
                    'company', \App\Models\Company::class => $this->source?->legal_name,
                    default => null,
                },
            ],
        ];
    }
}
