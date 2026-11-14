<?php

namespace App\Observers;

use App\Models\PathModule;
use App\Services\Progress\ModuleCompletionService;

class PathModuleObserver
{
    public function __construct(
        private ModuleCompletionService $moduleCompletion,
    ) {}

    public function deleting(PathModule $module): void
    {
        $this->moduleCompletion->auditModuleTeardown(
            $module,
            auth()->user(),
            'path_module.deleted',
            'path_module_deleted',
            now(),
        );
    }
}
