<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\Certificate;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\MentorshipRelationship;
use App\Models\Message;
use App\Models\PersonalityAssessment;
use App\Models\Review;
use App\Models\Session;
use App\Models\SessionAttendee;
use App\Models\User;

uses()->group('privacy');

it('returns privacy-safe family and cohort members', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $member = User::factory()->create([
        'email' => 'private-member@example.com',
        'birth_date' => '1980-01-01',
    ]);
    PersonalityAssessment::factory()->create([
        'user_id' => $member->id,
        'answers' => ['private-answer'],
        'raw_scores' => ['private-score'],
    ]);

    $family = FamilyGroup::create([
        'head_user_id' => $owner->id,
        'family_name' => 'Safe Family',
    ]);
    FamilyMember::create([
        'family_group_id' => $family->id,
        'user_id' => $member->id,
        'full_name' => $member->full_name,
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/family-groups/{$family->id}")
        ->assertOk()
        ->assertJsonPath('members.0.user.id', $member->id)
        ->assertJsonMissingPath('members.0.user.email')
        ->assertJsonMissingPath('members.0.user.personality_assessment')
        ->assertJsonMissingPath('members.0.user.answers')
        ->assertJsonMissingPath('members.0.user.raw_scores');

    $cohort = Cohort::create([
        'created_by' => $owner->id,
        'name' => 'Safe Cohort',
        'max_members' => 10,
    ]);
    CohortMember::create([
        'cohort_id' => $cohort->id,
        'user_id' => $member->id,
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/cohorts/{$cohort->id}")
        ->assertOk()
        ->assertJsonPath('members.0.user.id', $member->id)
        ->assertJsonMissingPath('members.0.user.email')
        ->assertJsonMissingPath('members.0.user.personality_assessment');
});

it('returns privacy-safe messages and reviews', function () {
    $owner = User::factory()->create();
    $mentor = User::factory()->mentor()->create([
        'email' => 'private-mentor@example.com',
    ]);
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $message = Message::create([
        'relationship_id' => $relationship->id,
        'sender_id' => $mentor->id,
        'content' => 'Private content',
        'type' => 'text',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'status' => 'completed',
        'mentor_notes' => 'Mentor private notes',
        'mentee_notes' => 'Mentee private notes',
    ]);
    SessionAttendee::create([
        'session_id' => $session->id,
        'user_id' => $owner->id,
    ]);
    Review::create([
        'session_id' => $session->id,
        'rater_id' => $mentor->id,
        'rating' => 5,
        'comment' => 'Private review',
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/messages/{$message->id}")
        ->assertOk()
        ->assertJsonPath('sender.id', $mentor->id)
        ->assertJsonMissingPath('sender.email')
        ->assertJsonMissingPath('sender.personality_assessment');

    $this->actingAs($owner)
        ->getJson("/api/v1/reviews/session/{$session->id}")
        ->assertOk()
        ->assertJsonPath('data.0.rater.id', $mentor->id)
        ->assertJsonMissingPath('data.0.rater.email')
        ->assertJsonMissingPath('data.0.user');

    $this->actingAs($owner)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonMissingPath('mentor_notes')
        ->assertJsonPath('mentee_notes', 'Mentee private notes');

    $this->actingAs($mentor)
        ->getJson("/api/v1/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonPath('mentor_notes', 'Mentor private notes')
        ->assertJsonMissingPath('mentee_notes');
});

it('only allows reviews after a session is completed', function () {
    $owner = User::factory()->create();
    $mentor = User::factory()->mentor()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => $mentor->id,
        'status' => 'active',
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->postJson('/api/v1/reviews', [
            'session_id' => $session->id,
            'rating' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'La sesion debe estar completada antes de valorarla.');
});

it('does not expose certificate recipient contact data to the mentor', function () {
    $recipient = User::factory()->create([
        'email' => 'certificate-recipient@example.com',
        'birth_date' => '1980-01-01',
    ]);
    $mentor = User::factory()->mentor()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $recipient->id,
        'mentor_id' => $mentor->id,
        'status' => 'completed',
    ]);
    $certificate = Certificate::create([
        'user_id' => $recipient->id,
        'relationship_id' => $relationship->id,
        'type' => 'completion',
        'title' => 'Completion certificate',
        'metadata' => ['subject_hash' => 'safe-hash'],
        'issued_at' => now(),
    ]);

    $this->actingAs($mentor)
        ->getJson("/api/v1/certificates/{$certificate->id}")
        ->assertOk()
        ->assertJsonPath('user.id', $recipient->id)
        ->assertJsonMissingPath('user.email')
        ->assertJsonMissingPath('user.birth_date')
        ->assertJsonMissingPath('user.sex');
});
