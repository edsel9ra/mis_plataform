<?php

use App\Models\LearningPath;
use App\Models\LearningResource;
use App\Models\PathModule;
use App\Models\User;

uses()->group('content-access');

it('only exposes active, client-compatible and entitled learning content', function () {
    $user = User::factory()->create(['client_type' => 'personal']);
    $activePath = LearningPath::create([
        'title' => ['es' => 'Personal path'],
        'client_type' => 'personal',
        'level' => 'beginner',
        'is_active' => true,
    ]);
    $inactivePath = LearningPath::create([
        'title' => ['es' => 'Inactive path'],
        'client_type' => 'personal',
        'is_active' => false,
    ]);
    $otherPath = LearningPath::create([
        'title' => ['es' => 'Other path'],
        'client_type' => 'empresa',
        'is_active' => true,
    ]);
    $module = PathModule::create([
        'learning_path_id' => $activePath->id,
        'title' => 'Module',
        'order' => 1,
    ]);
    $otherModule = PathModule::create([
        'learning_path_id' => $otherPath->id,
        'title' => 'Other module',
        'order' => 1,
    ]);
    $freeResource = LearningResource::create([
        'module_id' => $module->id,
        'type' => 'link',
        'title' => ['es' => 'Free'],
        'url' => 'https://example.com/free',
        'is_free' => true,
    ]);
    $paidResource = LearningResource::create([
        'module_id' => $module->id,
        'type' => 'link',
        'title' => ['es' => 'Paid'],
        'url' => 'https://example.com/paid',
        'is_free' => false,
    ]);
    $inactiveModule = PathModule::create([
        'learning_path_id' => $inactivePath->id,
        'title' => 'Inactive module',
    ]);
    $inactiveResource = LearningResource::create([
        'module_id' => $inactiveModule->id,
        'type' => 'link',
        'title' => ['es' => 'Inactive'],
        'url' => 'https://example.com/inactive',
        'is_free' => true,
    ]);

    $this->actingAs($user)
        ->getJson('/api/v1/learning-paths')
        ->assertOk()
        ->assertJsonFragment(['id' => $activePath->id])
        ->assertJsonMissing(['id' => $inactivePath->id])
        ->assertJsonMissing(['id' => $otherPath->id])
        ->assertJsonMissing(['id' => $paidResource->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/resources?per_page=999')
        ->assertOk()
        ->assertJsonPath('per_page', 100)
        ->assertJsonFragment(['id' => $freeResource->id])
        ->assertJsonMissing(['id' => $paidResource->id])
        ->assertJsonMissing(['id' => $inactiveResource->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/learning-paths/{$inactivePath->id}")
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson("/api/v1/resources/{$paidResource->id}")
        ->assertNotFound();

    $this->actingAs($user)
        ->putJson("/api/v1/learning-paths/{$activePath->id}/progress", [
            'module_id' => $otherModule->id,
            'progress' => 20,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['module_id']);
});
