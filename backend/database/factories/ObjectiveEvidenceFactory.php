<?php

namespace Database\Factories;

use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ObjectiveEvidenceFactory extends Factory
{
    protected $model = ObjectiveEvidence::class;

    public function definition(): array
    {
        return [
            'objective_id' => Objective::factory(),
            'user_id' => User::factory(),
            'note' => fake()->sentence(),
            'media_asset_id' => null,
        ];
    }
}
