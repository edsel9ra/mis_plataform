<?php

namespace App\Actions;

use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;

class CreateSessionAction
{
    public function execute(array $data): Session
    {
        $relationship = MentorshipRelationship::findOrFail($data['relationship_id']);

        $session = Session::create([
            'relationship_id' => $data['relationship_id'],
            'session_type' => $data['session_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'],
            'status' => 'scheduled',
        ]);

        $attendeeIds = $data['attendee_ids'] ?? [];
        $attendeeIds[] = $relationship->mentor_id;

        foreach (array_unique($attendeeIds) as $userId) {
            SessionAttendee::create([
                'session_id' => $session->id,
                'user_id' => $userId,
            ]);
        }

        $session->load(['relationship.mentor', 'attendees.user']);

        return $session;
    }
}
