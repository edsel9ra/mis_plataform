<?php

use App\Models\Notification;
use App\Models\User;

uses()->group('rate-limits');

it('applies endpoint-specific rate limits', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'wrong',
    ])->assertStatus(429);

    $user = User::factory()->create();
    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)
            ->postJson('/api/v1/personality/calculate', [
                'answers' => [3],
                'test_version' => 'ipip-neo-120',
                'age' => 30,
                'sex' => 'N',
            ])->assertUnprocessable();
    }

    $this->actingAs($user)
        ->postJson('/api/v1/personality/calculate', [
            'answers' => [3],
            'test_version' => 'ipip-neo-120',
            'age' => 30,
            'sex' => 'N',
        ])->assertStatus(429);
});

it('caps paginated responses', function () {
    $user = User::factory()->create();
    Notification::create([
        'user_id' => $user->id,
        'type' => 'status_change',
        'data' => [],
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/notifications?per_page=999')
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});
