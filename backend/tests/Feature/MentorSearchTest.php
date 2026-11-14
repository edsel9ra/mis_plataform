<?php

use App\Models\User;

uses()->group('security');

it('does not treat LIKE wildcards in mentor search as matching all mentors', function () {
    User::factory()->mentor()->create(['name' => 'Alice', 'is_active' => true]);
    User::factory()->mentor()->create(['name' => 'Bob', 'is_active' => true]);

    $all = $this->getJson('/api/v1/mentors');
    $all->assertOk();
    expect($all->json('total') ?? count($all->json('data')))->toBeGreaterThanOrEqual(2);

    $response = $this->getJson('/api/v1/mentors?search=%25');

    $response->assertOk();
    expect(count($response->json('data')))->toBe(0);
});
