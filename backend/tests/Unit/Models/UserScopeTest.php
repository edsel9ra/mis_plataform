<?php

use App\Models\User;

uses()->group('models');

it('filters users by client type', function () {
    User::factory()->create(['client_type' => 'personal']);
    User::factory()->create(['client_type' => 'empresa']);

    $result = User::byClientType('personal')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->client_type)->toBe('personal');
});

it('filters users by role', function () {
    User::factory()->create(['role' => 'mentor']);
    User::factory()->create(['role' => 'mentee']);

    $result = User::byRole('mentor')->get();

    expect($result)->toHaveCount(1);
});

it('filters active users', function () {
    User::factory()->create(['is_active' => true]);
    User::factory()->create(['is_active' => false]);

    $result = User::active()->get();

    expect($result)->toHaveCount(1);
});

it('searches users by name or email', function () {
    User::factory()->create(['name' => 'Alice Wonderland', 'email' => 'alice@test.com']);
    User::factory()->create(['name' => 'Bob', 'email' => 'bob@test.com']);

    $result = User::search('alice')->get();

    expect($result)->toHaveCount(1);
});
