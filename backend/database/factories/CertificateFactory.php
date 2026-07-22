<?php

namespace Database\Factories;

use App\Models\MentorshipRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'relationship_id' => MentorshipRelationship::factory(),
            'type' => $this->faker->randomElement(['completion', 'skill', 'mentorship_hours']),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'metadata' => [],
            'ipfs_cid' => $this->faker->sha256(),
            'ipfs_uri' => 'https://gateway.pinata.cloud/ipfs/' . $this->faker->sha256(),
            'blockchain_tx_hash' => '0x' . $this->faker->sha256(),
            'blockchain_contract_address' => '0x' . $this->faker->sha256(),
            'issued_at' => now(),
            'revoked' => false,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn(array $attrs) => ['revoked' => true]);
    }
}
