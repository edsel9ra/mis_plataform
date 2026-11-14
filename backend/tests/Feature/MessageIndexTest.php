<?php

use App\Models\Message;
use App\Models\MentorshipRelationship;
use App\Models\User;

uses()->group('messages');

it('filters messages by relationship_id', function () {
    $owner = User::factory()->create();
    $mentor = User::factory()->mentor()->create();

    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => $mentor->id,
    ]);
    $other = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => User::factory()->mentor()->create()->id,
    ]);

    Message::create(['relationship_id' => $relationship->id, 'sender_id' => $owner->id, 'content' => 'first']);
    Message::create(['relationship_id' => $other->id, 'sender_id' => $owner->id, 'content' => 'second']);

    $response = $this->withToken($owner->createToken('test')->plainTextToken)
        ->getJson("/api/v1/messages?relationship_id={$relationship->id}");

    $response->assertOk();
    $contents = collect($response->json('data'))->pluck('content');
    expect($contents)->toContain('first')->not->toContain('second');
});

it('forbids filtering by a relationship the user does not belong to', function () {
    $owner = User::factory()->create();
    $mentor = User::factory()->mentor()->create();
    $outsider = User::factory()->create();

    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => $mentor->id,
    ]);

    $this->withToken($outsider->createToken('test')->plainTextToken)
        ->getJson("/api/v1/messages?relationship_id={$relationship->id}")
        ->assertForbidden();
});
