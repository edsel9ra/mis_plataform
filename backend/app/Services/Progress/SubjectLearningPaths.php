<?php

namespace App\Services\Progress;

use App\Models\LearningPath;
use App\Models\User;
use App\Models\UserLearningProgress;

class SubjectLearningPaths
{
    /**
     * A subject can consult a learning path when it has been assigned or
     * started for them, i.e. a user_learning_progress row exists for the
     * pair regardless of its status (RF-012.8/.9, reusing the Spec 001
     * progress-panel notion of the subject's authorized paths).
     */
    public function canConsult(User $subject, LearningPath $path): bool
    {
        return UserLearningProgress::query()
            ->where('user_id', $subject->id)
            ->where('learning_path_id', $path->getKey())
            ->exists();
    }

    /**
     * @return array<int, string> ids of the paths the subject can consult
     */
    public function consultablePathIds(User $subject): array
    {
        return UserLearningProgress::query()
            ->where('user_id', $subject->id)
            ->pluck('learning_path_id')
            ->all();
    }
}
