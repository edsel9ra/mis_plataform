<?php

namespace App\Services\Progress;

use App\Models\Challenge;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\PersonalityAssessment;
use App\Models\Session;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserSkill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * T-084 — forgets the cached panel of every subject affected by a change
 * (RNF-5). Registered on Objective, Session, Challenge, UserLearningProgress,
 * PersonalityAssessment and UserSkill.
 *
 * Only the panel payload is cached; collective aggregates are derived on read,
 * so forgetting the member panels is enough.
 */
class ProgressCacheObserver
{
    public function saved(object $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(object $model): void
    {
        $this->invalidate($model);
    }

    public function restored(object $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(object $model): void
    {
        foreach ($this->affectedUserIds($model) as $userId) {
            $this->forget($userId);
        }
    }

    /**
     * @return array<int, string>
     */
    private function affectedUserIds(object $model): array
    {
        if ($model instanceof Objective) {
            if ($model->owner_type === 'user') {
                return [(string) $model->owner_id];
            }

            if (in_array($model->owner_type, ['family_group', 'cohort', 'company'], true)) {
                return app(EligibleParticipantsResolver::class)
                    ->memberIds((string) $model->owner_type, (string) $model->owner_id);
            }

            return [];
        }

        if ($model instanceof Session) {
            $relationship = MentorshipRelationship::query()->find($model->relationship_id);

            if ($relationship && in_array($relationship->source_type, ['user', User::class], true)) {
                return [(string) $relationship->source_id];
            }

            return [];
        }

        if ($model instanceof Challenge) {
            return $model->mentee_id ? [(string) $model->mentee_id] : [];
        }

        if ($model instanceof UserLearningProgress || $model instanceof PersonalityAssessment || $model instanceof UserSkill) {
            return $model->user_id ? [(string) $model->user_id] : [];
        }

        return [];
    }

    private function forget(string $userId): void
    {
        $bucket = CarbonImmutable::now()->format('Y-m');

        Cache::forget('progress:panel:'.$userId.':'.$bucket);
    }
}
