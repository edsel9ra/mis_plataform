<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ObjectiveFactory extends Factory
{
    protected $model = Objective::class;

    public function definition(): array
    {
        return [
            'scope' => 'individual',
            'owner_type' => 'user',
            'owner_id' => User::factory(),
            'relationship_id' => null,
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'success_criteria' => fake()->sentence(),
            'status' => 'not_started',
            'due_at' => fake()->optional()->dateTimeBetween('now', '+2 months'),
            'achieved_at' => null,
            'proposed_by' => null,
        ];
    }

    public function individual(User|string|null $owner = null): static
    {
        return $this->state(function () use ($owner) {
            $ownerId = $owner instanceof User ? $owner->id : $owner;

            return [
                'scope' => 'individual',
                'owner_type' => 'user',
                'owner_id' => $ownerId ?? User::factory(),
            ];
        });
    }

    public function collective(string $ownerType = 'family_group', ?string $ownerId = null): static
    {
        return $this->state(fn () => [
            'scope' => 'collective',
            'owner_type' => $ownerType,
            'owner_id' => $ownerId ?? $this->newCollectiveOwnerId($ownerType),
        ]);
    }

    public function forRelationship(?string $relationshipId): static
    {
        return $this->state(fn () => ['relationship_id' => $relationshipId]);
    }

    public function achieved(): static
    {
        return $this->state(fn () => [
            'status' => 'achieved',
            'achieved_at' => now(),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => 'abandoned',
            'achieved_at' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => 'in_progress',
            'due_at' => now()->subDays(3),
        ]);
    }

    private function newCollectiveOwnerId(string $ownerType): string
    {
        return match ($ownerType) {
            'cohort' => Cohort::create([
                'name' => fake()->word(),
                'created_by' => User::factory()->create()->id,
                'status' => 'active',
            ])->id,
            'company' => Company::create([
                'legal_name' => fake()->company(),
                'admin_id' => User::factory()->create()->id,
            ])->id,
            default => FamilyGroup::create([
                'head_user_id' => User::factory()->create()->id,
                'family_name' => fake()->lastName(),
            ])->id,
        };
    }
}
