<?php

namespace App\Actions;

use App\Models\Challenge;
use App\Models\PathModule;
use App\Models\User;
use App\Services\Progress\ModuleCompletionService;
use App\Services\Progress\SubjectLearningPaths;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetChallengePathModuleAction
{
    public function __construct(
        private SubjectLearningPaths $subjectPaths,
        private ModuleCompletionService $moduleCompletion,
    ) {}

    /**
     * Set or remove (null) a challenge's explicit path-module link.
     * Authorization happens fully on the server: the actor must pass
     * ChallengePolicy::linkModule (titular mentee on an active relationship
     * or the assigned mentor under the workspace manage rule), the target
     * module's path must be consultable by the challenge's subject, and the
     * currently linked module's path must be consultable too before any
     * change or removal. Every denial throws ModelNotFoundException without
     * modifying or revealing the link (RF-012.8-.10).
     */
    public function execute(Challenge $challenge, User $actor, ?string $pathModuleId, CarbonInterface $now): Challenge
    {
        return DB::transaction(function () use ($challenge, $actor, $pathModuleId, $now): Challenge {
            $locked = Challenge::query()
                ->whereKey($challenge->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (Gate::forUser($actor)->denies('linkModule', $locked)) {
                throw (new ModelNotFoundException)->setModel(Challenge::class, [$locked->getKey()]);
            }

            $subject = $locked->mentee;

            $target = null;
            if ($pathModuleId !== null) {
                $target = PathModule::query()
                    ->with('learningPath')
                    ->whereKey($pathModuleId)
                    ->first();

                if ($target === null || ! $this->subjectPaths->canConsult($subject, $target->learningPath)) {
                    throw (new ModelNotFoundException)->setModel(PathModule::class, [$pathModuleId]);
                }
            }

            $current = $locked->pathModule()->with('learningPath')->first();
            if ($current !== null && ! $this->subjectPaths->canConsult($subject, $current->learningPath)) {
                throw (new ModelNotFoundException)->setModel(PathModule::class);
            }

            $newId = $target?->id;
            if ($locked->path_module_id === $newId) {
                return $locked;
            }

            $before = $locked->path_module_id;
            $locked->forceFill(['path_module_id' => $newId])->save();

            activity('challenge')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('challenge.module_link_changed')
                ->withProperties([
                    'challenge_id' => $locked->id,
                    'actor' => $actor->id,
                    'before' => ['path_module_id' => $before],
                    'after' => ['path_module_id' => $newId],
                    'occurred_at' => $now->toIso8601String(),
                ])
                ->log('challenge.module_link_changed');

            // Recalculate previous and new modules in ascending id order to
            // keep the lock order stable and avoid deadlocks.
            collect([$before, $newId])
                ->filter()
                ->unique()
                ->sort()
                ->each(fn (string $moduleId) => $this->moduleCompletion->recalculate(
                    $subject->id,
                    $moduleId,
                    $actor,
                    'challenge.module_link_changed',
                    $locked->id,
                    $now,
                ));

            return $locked;
        });
    }
}
