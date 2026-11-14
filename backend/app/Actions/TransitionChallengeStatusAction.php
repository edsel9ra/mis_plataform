<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\User;
use App\Services\Progress\ModuleCompletionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionChallengeStatusAction
{
    public function __construct(
        private ModuleCompletionService $moduleCompletion,
    ) {}

    /**
     * Validate and apply a challenge status transition (RF-012.7).
     * Allowed transitions are audited with actor, moment and previous/new
     * status inside the same transaction that writes them; self-transitions
     * and rejected ones leave the challenge untouched and unaudited.
     */
    public function execute(Challenge $challenge, User $actor, string $to, CarbonInterface $now): Challenge
    {
        return DB::transaction(function () use ($challenge, $actor, $to, $now): Challenge {
            $locked = Challenge::query()->whereKey($challenge->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => ["A challenge cannot transition from {$locked->status} to {$to}."],
                ]);
            }

            if ($locked->status === $to) {
                return $locked;
            }

            $before = $locked->status;
            $locked->status = $to;
            if ($to === 'completed') {
                $locked->completed_at = $now;
            }
            $locked->save();

            activity('challenge')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('challenge.status_transition')
                ->withProperties([
                    'challenge_id' => $locked->id,
                    'actor' => $actor->id,
                    'before' => ['status' => $before],
                    'after' => ['status' => $locked->status],
                    'occurred_at' => $now->toIso8601String(),
                ])
                ->log('challenge.status_transition');

            if ($locked->path_module_id !== null && $locked->mentee_id !== null) {
                $this->moduleCompletion->recalculate(
                    $locked->mentee_id,
                    $locked->path_module_id,
                    $actor,
                    'challenge.status_transition',
                    $locked->id,
                    $now,
                );
            }

            return $locked;
        });
    }
}
