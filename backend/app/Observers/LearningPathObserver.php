<?php

namespace App\Observers;

use App\Models\LearningPath;
use App\Services\Progress\ModuleCompletionService;
use Illuminate\Support\Facades\DB;

class LearningPathObserver
{
    public function __construct(
        private ModuleCompletionService $moduleCompletion,
    ) {}

    /**
     * Deleting a learning path removes its modules through the database
     * cascade, which fires no Eloquent events: every module is torn down
     * (link-removal and completion-revocation audits) and physically deleted
     * here, inside the same transaction.
     */
    public function deleting(LearningPath $path): void
    {
        DB::transaction(function () use ($path): void {
            $path->modules()->get()->each(function ($module) {
                $this->moduleCompletion->auditModuleTeardown(
                    $module,
                    auth()->user(),
                    'learning_path.deleted',
                    'learning_path_deleted',
                    now(),
                );
            });

            DB::table('path_modules')->where('learning_path_id', $path->id)->delete();
        });
    }
}
