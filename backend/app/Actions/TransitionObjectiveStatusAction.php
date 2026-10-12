<?php

namespace App\Actions;

use App\Enums\ObjectiveStatus;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TransitionObjectiveStatusAction
{
    /**
     * Validate and apply a status transition, keeping `achieved_at` in sync.
     * The activity log records the author, date and previous -> new values
     * (RF-2, P5).
     */
    public function execute(User $actor, Objective $objective, string $status): Objective
    {
        $target = ObjectiveStatus::tryFrom($status);

        if ($target === null) {
            throw ValidationException::withMessages([
                'status' => [__('objectives.validation.invalid_transition', [
                    'from' => $objective->status->value,
                    'to' => $status,
                ])],
            ]);
        }

        if (! $objective->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => [__('objectives.validation.invalid_transition', [
                    'from' => $objective->status->value,
                    'to' => $target->value,
                ])],
            ]);
        }

        $objective->status = $target;
        $objective->achieved_at = $target === ObjectiveStatus::Achieved ? now() : null;
        $objective->save();

        return $objective->refresh();
    }
}
