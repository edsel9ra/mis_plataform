<?php

use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserSkill;
use Database\Seeders\RolePermissionSeeder;

uses()->group('mentor-skills');

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->mentor = User::factory()->mentor()->create();
    $this->mentor->assignRole('mentor');

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('admin');

    $this->tag = SkillTag::create([
        'name' => 'Facilitación sistémica',
        'category' => 'profesional',
        'is_active' => true,
    ]);
    $this->secondTag = SkillTag::create([
        'name' => 'Acompañamiento familiar',
        'category' => 'familiar',
        'is_active' => true,
    ]);

    $this->mentorToken = $this->mentor->createToken('test')->plainTextToken;
    $this->adminToken = $this->admin->createToken('test')->plainTextToken;
});

it('allows a mentor to replace their own specialties', function () {
    $response = $this->withToken($this->mentorToken)
        ->putJson('/api/v1/mentor/skills', [
            'skills' => [
                ['skill_tag_id' => $this->tag->id, 'level' => 'expert'],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('skills.0.skill.name', 'Facilitación sistémica')
        ->assertJsonPath('skills.0.level', 'expert');

    $this->assertDatabaseHas('user_skills', [
        'user_id' => $this->mentor->id,
        'skill_tag_id' => $this->tag->id,
        'level' => 'expert',
    ]);
});

it('allows an admin to replace a mentor specialties', function () {
    $response = $this->withToken($this->adminToken)
        ->putJson("/api/v1/admin/mentors/{$this->mentor->id}/skills", [
            'skills' => [
                ['skill_tag_id' => $this->secondTag->id, 'level' => 'advanced'],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('skills.0.skill.name', 'Acompañamiento familiar');

    $this->assertDatabaseHas('user_skills', [
        'user_id' => $this->mentor->id,
        'skill_tag_id' => $this->secondTag->id,
        'level' => 'advanced',
    ]);
});

it('removes specialties omitted from the replacement payload', function () {
    UserSkill::create([
        'user_id' => $this->mentor->id,
        'skill_tag_id' => $this->tag->id,
        'level' => 'intermediate',
    ]);

    $this->withToken($this->mentorToken)
        ->putJson('/api/v1/mentor/skills', ['skills' => []])
        ->assertOk();

    expect(UserSkill::where('user_id', $this->mentor->id)->count())->toBe(0);
});

it('denies a non mentor and a mentor editing another mentor specialties', function () {
    $mentee = User::factory()->create();
    $menteeToken = $mentee->createToken('test')->plainTextToken;
    $otherMentor = User::factory()->mentor()->create();
    $otherMentor->assignRole('mentor');

    $this->withToken($menteeToken)
        ->putJson('/api/v1/mentor/skills', ['skills' => []])
        ->assertForbidden();

    $this->withToken($this->mentorToken)
        ->putJson("/api/v1/admin/mentors/{$otherMentor->id}/skills", ['skills' => []])
        ->assertForbidden();
});

it('rejects invalid or inactive specialty assignments', function () {
    $inactiveTag = SkillTag::create([
        'name' => 'Especialidad archivada',
        'category' => 'demo',
        'is_active' => false,
    ]);

    $response = $this->withToken($this->mentorToken)
        ->putJson('/api/v1/mentor/skills', [
            'skills' => [
                ['skill_tag_id' => $inactiveTag->id, 'level' => 'expert'],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['skills.0.skill_tag_id']);

    $this->withToken($this->mentorToken)
        ->putJson('/api/v1/mentor/skills', [
            'skills' => [
                ['skill_tag_id' => $this->tag->id, 'level' => 'master'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['skills.0.level']);
});

it('lists only active specialty tags for mentors', function () {
    SkillTag::create([
        'name' => 'Especialidad archivada',
        'category' => 'demo',
        'is_active' => false,
    ]);

    $this->withToken($this->mentorToken)
        ->getJson('/api/v1/skill-tags')
        ->assertOk()
        ->assertJsonPath('0.is_active', true)
        ->assertJsonMissing(['name' => 'Especialidad archivada']);
});

it('allows admins to create and archive catalog specialties', function () {
    $createResponse = $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/skill-tags', [
            'name' => '  Nueva especialidad  ',
            'category' => '  bienestar ',
        ]);

    $createResponse->assertCreated()
        ->assertJsonPath('name', 'Nueva especialidad')
        ->assertJsonPath('category', 'bienestar');

    $tag = SkillTag::where('name', 'Nueva especialidad')->firstOrFail();

    $this->withToken($this->adminToken)
        ->deleteJson("/api/v1/admin/skill-tags/{$tag->id}")
        ->assertOk()
        ->assertJsonPath('skill_tag.is_active', false);

    expect($tag->fresh()->is_active)->toBeFalse();

    $this->withToken($this->adminToken)
        ->putJson("/api/v1/admin/skill-tags/{$tag->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('is_active', true);
});

it('returns specialties using the public resource contract', function () {
    UserSkill::create([
        'user_id' => $this->mentor->id,
        'skill_tag_id' => $this->tag->id,
        'level' => 'advanced',
    ]);

    $this->getJson("/api/v1/mentors/{$this->mentor->id}")
        ->assertOk()
        ->assertJsonPath('skills.0.skill.name', 'Facilitación sistémica')
        ->assertJsonPath('skills.0.level', 'advanced');
});
