<?php

namespace App\Http\Resources;

use App\Models\MentorshipRelationship;
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
        $canViewAllAttendance = $isMentor || ($user
            && ! $isAdmin
            && $relationship
            && in_array(MentorshipRelationship::canonicalSourceType($relationship->source_type), [
                'family_group',
                'cohort',
                'company',
            ], true)
            && MentorshipRelationship::userCanManageSource(
                $user,
                $relationship->source_type,
                $relationship->source_id,
            ));

        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'session_type' => $this->session_type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'cancellation' => $this->when($this->status === 'canceled', [
                'reason' => $this->cancellation_reason,
                'at' => $this->canceled_at,
                'by' => $this->canceled_by,
                'late' => $this->canceled_late,
                'lead_minutes' => $this->cancellation_lead_minutes,
            ]),
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'meet_link' => $this->status === 'canceled' ? null : $this->meet_link,
            'mentor_notes' => $this->when($isAdmin || $isMentor, $this->mentor_notes),
            'mentee_notes' => $this->when($isAdmin || $isAttendee, $this->mentee_notes),
            'created_at' => $this->created_at,
            'relationship' => RelationshipResource::make($this->whenLoaded('relationship')),
            'attendees' => $this->when($this->relationLoaded('attendees'), function () use ($canViewAllAttendance, $user) {
                $attendees = $this->attendees;

                if (! $canViewAllAttendance) {
                    $attendees = $attendees
                        ->where('user_id', $user?->id)
                        ->values();
                }

                return SessionAttendeeResource::collection($attendees);
            }),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
