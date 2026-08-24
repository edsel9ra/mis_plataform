<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $relationship = $this->relationLoaded('relationship') ? $this->relationship : null;
        $user = $request->user();
        $isMentor = $user && $relationship?->mentor_id === $user->id;
        $isAttendee = $user && $this->relationLoaded('attendees')
            && $this->attendees->contains('user_id', $user->id);
        $isAdmin = $user?->isAdmin() === true;

        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'session_type' => $this->session_type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'meet_link' => $this->meet_link,
            'mentor_notes' => $this->when($isAdmin || $isMentor, $this->mentor_notes),
            'mentee_notes' => $this->when($isAdmin || $isAttendee, $this->mentee_notes),
            'created_at' => $this->created_at,
            'relationship' => RelationshipResource::make($this->whenLoaded('relationship')),
            'attendees' => SessionAttendeeResource::collection($this->whenLoaded('attendees')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
