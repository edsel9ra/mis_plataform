<?php

namespace Database\Factories;

use App\Models\FeedbackEntry;
use App\Models\FeedbackThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedbackEntryFactory extends Factory
{
    protected $model = FeedbackEntry::class;

    public function definition(): array
    {
        return [
            'feedback_thread_id' => FeedbackThread::factory(),
            'author_id' => User::factory()->mentor(),
            'body' => fake()->paragraph(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FeedbackEntry $entry): void {
            $thread = $entry->thread()->first();
            if ($thread) {
                $entry->forceFill(['author_id' => $thread->mentor_id])->saveQuietly();
            }
        });
    }
}
