<?php

use App\Models\User;

uses()->group('security');

it('returns 404 for an invalid uuid on public routes', function () {
    $this->getJson('/api/v1/mentors/not-a-uuid')->assertNotFound();
    $this->getJson('/api/v1/plans/not-a-uuid')->assertNotFound();
});

it('returns 404 for an invalid uuid on authenticated routes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/sessions/not-a-uuid')->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/relationships/not-a-uuid')->assertNotFound();
});

it('returns 404 for an invalid uuid on bound resource routes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/challenges/not-a-uuid')->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/relationships/not-a-uuid/check-ins')->assertNotFound();
});

it('returns 422 without leaking SQL for an invalid uuid query filter', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/resources?module_id=bad');

    $response->assertUnprocessable();
    expect($response->getContent())->not->toContain('SQLSTATE');
});
