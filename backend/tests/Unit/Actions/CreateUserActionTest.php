<?php

use App\Actions\CreateUserAction;
use App\Enums\ClientType;
use App\Models\User;

uses()->group('actions');

it('creates a user with given data', function () {
    $action = app(CreateUserAction::class);

    $user = $action->execute([
        'name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'client_type' => ClientType::Personal->value,
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('john@example.com')
        ->and($user->role)->toBe('mentee');
});

it('creates a user with custom role', function () {
    $action = app(CreateUserAction::class);

    $user = $action->execute([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'client_type' => ClientType::Personal->value,
    ], 'mentor');

    expect($user->role)->toBe('mentor');
});

it('hashes the password', function () {
    $action = app(CreateUserAction::class);

    $user = $action->execute([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'Secret123!',
        'client_type' => ClientType::Personal->value,
    ]);

    expect($user->password)->not->toBe('Secret123!');
});
