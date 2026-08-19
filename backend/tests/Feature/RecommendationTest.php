<?php

use App\Models\PersonalityAssessment;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;

uses()->group('recommendations');

function recommendationUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

function assessmentFor(User $user, array $factors): PersonalityAssessment
{
    return PersonalityAssessment::factory()->create([
        'user_id' => $user->id,
        'results' => ['factors' => $factors],
    ]);
}

it('recommends only mentors matching the user client type and Big Five scores', function () {
    $user = recommendationUser(['client_type' => 'personal']);
    $personalMentor = recommendationUser(['role' => 'mentor', 'client_type' => 'personal']);
    $businessMentor = recommendationUser(['role' => 'mentor', 'client_type' => 'empresa']);

    assessmentFor($user, ['O' => 80, 'C' => 70, 'E' => 60, 'A' => 75, 'N' => 30]);
    assessmentFor($personalMentor, ['O' => 78, 'C' => 68, 'E' => 62, 'A' => 74, 'N' => 32]);
    assessmentFor($businessMentor, ['O' => 80, 'C' => 70, 'E' => 60, 'A' => 75, 'N' => 30]);

    $response = $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/v1/personality/recommendations');

    $response->assertOk()
        ->assertJsonCount(1, 'recommended_mentors')
        ->assertJsonPath('recommended_mentors.0.id', $personalMentor->id)
        ->assertJsonPath('recommended_mentors.0.client_type', 'personal');
});

it('returns privacy-safe mentors and only sessions the user can access', function () {
    $user = recommendationUser(['client_type' => 'personal']);
    $mentor = recommendationUser([
        'role' => 'mentor',
        'client_type' => 'personal',
        'email' => 'private-mentor@example.com',
    ]);
    $otherUser = recommendationUser(['client_type' => 'personal']);

    assessmentFor($user, ['O' => 80, 'C' => 70, 'E' => 60, 'A' => 75, 'N' => 30]);
    PersonalityAssessment::factory()->create([
        'user_id' => $mentor->id,
        'answers' => ['private-answer'],
        'raw_scores' => ['private-score'],
        'results' => ['factors' => ['O' => 78, 'C' => 68, 'E' => 62, 'A' => 74, 'N' => 32]],
    ]);

    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $user->id,
        'mentor_id' => $mentor->id,
    ]);
    $authorizedSession = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'meet_link' => 'https://meet.example/private',
        'mentor_notes' => 'private mentor notes',
        'mentee_notes' => 'private mentee notes',
        'recording_url' => 'https://recording.example/private',
    ]);

    $otherRelationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $otherUser->id,
        'mentor_id' => $mentor->id,
    ]);
    Session::factory()->create(['relationship_id' => $otherRelationship->id]);

    $response = $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/v1/personality/recommendations');

    $response->assertOk()
        ->assertJsonCount(1, 'recommended_mentors')
        ->assertJsonCount(1, 'recommended_sessions')
        ->assertJsonPath('recommended_sessions.0.id', $authorizedSession->id)
        ->assertJsonMissingPath('recommended_mentors.0.email')
        ->assertJsonMissingPath('recommended_mentors.0.personality_assessment')
        ->assertJsonMissingPath('recommended_sessions.0.meet_link')
        ->assertJsonMissingPath('recommended_sessions.0.mentor_notes')
        ->assertJsonMissingPath('recommended_sessions.0.mentee_notes')
        ->assertJsonMissingPath('recommended_sessions.0.recording_url')
        ->assertJsonMissingPath('recommended_sessions.0.relationship.mentor.email');
});

it('uses privacy-safe summaries for session attendees', function () {
    $owner = recommendationUser();
    $attendee = recommendationUser([
        'email' => 'private-attendee@example.com',
        'birth_date' => '1990-01-01',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
    ]);
    $session = Session::factory()->create(['relationship_id' => $relationship->id]);
    SessionAttendee::create([
        'session_id' => $session->id,
        'user_id' => $attendee->id,
        'attended' => true,
    ]);

    $response = $this->withToken($owner->createToken('test')->plainTextToken)
        ->getJson("/api/v1/sessions/{$session->id}");

    $response->assertOk()
        ->assertJsonPath('attendees.0.user.id', $attendee->id)
        ->assertJsonMissingPath('attendees.0.user.email')
        ->assertJsonMissingPath('attendees.0.user.birth_date')
        ->assertJsonMissingPath('attendees.0.user.personality_assessment');
});
