<?php

namespace Database\Factories;

use App\Models\EvidenceDeletionActorLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvidenceDeletionActorLinkFactory extends Factory
{
    protected $model = EvidenceDeletionActorLink::class;

    public function definition(): array
    {
        return [
            'event_id' => EvidenceDeletionEvent::factory(),
            'actor_id' => User::factory(),
        ];
    }
}
