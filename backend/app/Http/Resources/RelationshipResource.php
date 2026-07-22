<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'match_score' => $this->match_score,
            'objectives' => $this->objectives,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'mentor' => MentorResource::make($this->whenLoaded('mentor')),
            'source' => $this->whenLoaded('source'),
            'sessions' => SessionResource::collection($this->whenLoaded('sessions')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'certificates' => CertificateResource::collection($this->whenLoaded('certificates')),
        ];
    }
}
