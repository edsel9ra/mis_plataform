<?php

namespace App\Services\Progress;

use App\Models\Challenge;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserModuleCompletion;
use App\Services\Progress\Calculators\ChallengeModuleCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class ModuleCompletionService
{
    public function __construct(
        private ChallengeModuleCalculator $calculator,
    ) {}

    /**
     * Recalculate and persist the subject's module completion row from the
     * explicitly linked challenges (RF-012.2/.11). MUST be called inside the
     * caller's transaction so the triggering write and the completion row
     * commit or roll back atomically. Idempotent: creates the row only when
     * the derived state is `completed`, deletes it only when a row exists and
     * the state is not `completed`; audits each real change with the system
     * as actor.
     */
    public function recalculate(
        string $subjectId,
        string $pathModuleId,
        ?User $triggeredBy,
        string $triggerEvent,
        ?string $challengeId,
        CarbonInterface $now,
    ): void {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('ModuleCompletionService::recalculate must run inside a transaction.');
        }

        $module = PathModule::query()->whereKey($pathModuleId)->lockForUpdate()->first();

        $existing = UserModuleCompletion::query()
            ->where('user_id', $subjectId)
            ->where('path_module_id', $pathModuleId)
            ->lockForUpdate()
            ->first();

        $challenges = Challenge::query()
            ->where('mentee_id', $subjectId)
            ->where('path_module_id', $pathModuleId)
            ->get(['status'])
            ->map(fn (Challenge $challenge): array => ['status' => $challenge->status])
            ->all();

        $result = $this->calculator->moduleCompletion($challenges);

        $auditProperties = [
            'actor' => 'system',
            'user_id' => $subjectId,
            'path_module_id' => $pathModuleId,
            'result' => $result,
            'triggered_by' => [
                'actor_id' => $triggeredBy?->id,
                'event' => $triggerEvent,
                'challenge_id' => $challengeId,
            ],
            'occurred_at' => $now->toIso8601String(),
        ];

        if ($result === ChallengeModuleCalculator::MODULE_COMPLETED && $existing === null) {
            UserModuleCompletion::create([
                'user_id' => $subjectId,
                'path_module_id' => $pathModuleId,
                'completed_at' => $now,
            ]);

            $this->audit($module, 'module_completion.granted', $auditProperties);

            return;
        }

        if ($result !== ChallengeModuleCalculator::MODULE_COMPLETED && $existing !== null) {
            $auditProperties['previous_completed_at'] = $existing->completed_at?->toIso8601String();
            $existing->delete();

            $this->audit($module, 'module_completion.revoked', $auditProperties);
        }
    }

    /**
     * Audit every challenge-link removal and completion revocation for a
     * module that is being deleted (directly or through its learning path),
     * before the physical delete unlinks/cascades the rows (RF-012.10/.11).
     * Runs inside the caller's transaction (or opens one).
     */
    public function auditModuleTeardown(PathModule $module, ?User $actor, string $event, string $reason, CarbonInterface $now): void
    {
        DB::transaction(function () use ($module, $actor, $event, $reason, $now): void {
            Challenge::withTrashed()
                ->where('path_module_id', $module->id)
                ->get()
                ->each(function (Challenge $challenge) use ($module, $actor, $reason, $now): void {
                    $activity = activity('challenge')
                        ->performedOn($challenge)
                        ->event('challenge.module_link_changed')
                        ->withProperties([
                            'challenge_id' => $challenge->id,
                            'actor' => $actor?->id ?? 'system',
                            'before' => ['path_module_id' => $module->id],
                            'after' => ['path_module_id' => null],
                            'reason' => $reason,
                            'occurred_at' => $now->toIso8601String(),
                        ]);

                    if ($actor !== null) {
                        $activity->causedBy($actor);
                    }

                    $activity->log('challenge.module_link_changed');
                });

            UserModuleCompletion::query()
                ->where('path_module_id', $module->id)
                ->lockForUpdate()
                ->get()
                ->each(function (UserModuleCompletion $completion) use ($module, $actor, $event, $now): void {
                    $completion->delete();

                    $this->audit($module, 'module_completion.revoked', [
                        'actor' => 'system',
                        'user_id' => $completion->user_id,
                        'path_module_id' => $module->id,
                        'result' => ChallengeModuleCalculator::MODULE_NO_DATA,
                        'previous_completed_at' => $completion->completed_at?->toIso8601String(),
                        'triggered_by' => [
                            'actor_id' => $actor?->id,
                            'event' => $event,
                            'challenge_id' => null,
                        ],
                        'occurred_at' => $now->toIso8601String(),
                    ]);
                });
        });
    }

    /** @param array<string, mixed> $properties */
    private function audit(?PathModule $module, string $event, array $properties): void
    {
        $activity = activity('module_completion')->event($event)->withProperties($properties);

        if ($module !== null) {
            $activity->performedOn($module);
        }

        $activity->log($event);
    }
}
