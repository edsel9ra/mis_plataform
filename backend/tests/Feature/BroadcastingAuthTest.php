<?php

use App\Models\MentorshipRelationship;
use App\Models\User;

uses()->group('security');

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'fake-app-key',
        'broadcasting.connections.reverb.secret' => 'fake-app-secret',
        'broadcasting.connections.reverb.app_id' => 'fake-app-id',
    ]);
});

function broadcastAuthRequest($test, string $token, string $channel)
{
    auth()->forgetGuards();

    return $test->withToken($token)->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

it('authorizes the user private channel for its owner', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    broadcastAuthRequest($this, $token, "private-user.{$user->id}")
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('rejects another user private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    broadcastAuthRequest($this, $token, "private-user.{$other->id}")
        ->assertForbidden();
});

it('authorizes a relationship channel for participants and rejects outsiders', function () {
    $owner = User::factory()->create();
    $mentor = User::factory()->mentor()->create();
    $outsider = User::factory()->create();

    $relationship = MentorshipRelationship::factory()->create([
        'source_type' => 'user',
        'source_id' => $owner->id,
        'mentor_id' => $mentor->id,
    ]);

    broadcastAuthRequest($this, $owner->createToken('test')->plainTextToken, "private-relationship.{$relationship->id}")
        ->assertOk();
    broadcastAuthRequest($this, $mentor->createToken('test')->plainTextToken, "private-relationship.{$relationship->id}")
        ->assertOk();
    broadcastAuthRequest($this, $outsider->createToken('test')->plainTextToken, "private-relationship.{$relationship->id}")
        ->assertForbidden();
});

it('requires authentication for broadcasting auth', function () {
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-user.'.fake()->uuid(),
    ])->assertUnauthorized();
});
