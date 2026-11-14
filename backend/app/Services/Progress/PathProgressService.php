<?php

namespace App\Services\Progress;

use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserModuleCompletion;
use Illuminate\Support\Arr;
use App\Services\Progress\Calculators\ChallengeModuleCalculator;
use App\Services\Progress\Calculators\PathProgressCalculator;

class PathProgressService
{
    public function __construct(
        private SubjectLearningPaths $subjectPaths,
        private ChallengeModuleCalculator $challenges,
        private PathProgressCalculator $paths,
    ) {}

    /**
     * Per-path challenge/module metrics for a subject (RF-012.1-.6). Uses a
     * fixed set of batched queries (paths, modules, linked challenges,
     * completion rows) regardless of the amount of data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forSubject(User $subject): array
    {
        $pathIds = $this->subjectPaths->consultablePathIds($subject);
        if ($pathIds === []) {
            return [];
        }

        $paths = LearningPath::query()
            ->whereIn('id', $pathIds)
            ->get()
            ->keyBy('id');

        $modules = PathModule::query()
            ->whereIn('learning_path_id', $pathIds)
            ->orderBy('order')
            ->get();

        $moduleIds = $modules->pluck('id')->all();

        $challenges = $moduleIds === [] ? collect() : Challenge::query()
            ->where('mentee_id', $subject->id)
            ->whereIn('path_module_id', $moduleIds)
            ->get(['id', 'path_module_id', 'status']);

        $completions = $moduleIds === [] ? collect() : UserModuleCompletion::query()
            ->where('user_id', $subject->id)
            ->whereIn('path_module_id', $moduleIds)
            ->get()
            ->keyBy('path_module_id');

        $byModule = $modules->groupBy('learning_path_id');

        return $paths->map(function ($path) use ($byModule, $challenges, $completions): array {
            $moduleEntries = ($byModule->get($path->id) ?? collect())->map(
                function (PathModule $module) use ($challenges, $completions): array {
                    $linked = $challenges->where('path_module_id', $module->id)
                        ->map(fn (Challenge $challenge): array => ['status' => $challenge->status])
                        ->all();

                    $row = $completions->get($module->id);
                    $state = $this->challenges->moduleCompletion($linked);

                    return [
                        'id' => $module->id,
                        'title' => $module->title,
                        'position' => $module->order,
                        'activity_rate' => $this->challenges->activityRate($linked),
                        'completion' => [
                            'state' => $state,
                            'completed_at' => $row?->completed_at?->toIso8601String(),
                        ],
                        '_record' => $row !== null,
                    ];
                }
            );

            return [
                'learning_path' => [
                    'id' => $path->id,
                    'title' => $path->title,
                ],
                'rate' => $this->paths->pathRate($moduleEntries->map(
                    fn (array $entry): array => [
                        'completion' => $entry['completion']['state'],
                        'completed_record' => $entry['_record'],
                    ]
                )->all()),
                'modules' => $moduleEntries->map(
                    fn (array $entry): array => Arr::except($entry, ['_record'])
                )->values()->all(),
            ];
        })->values()->all();
    }
}
