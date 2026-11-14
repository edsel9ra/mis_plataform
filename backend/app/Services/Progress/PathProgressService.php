<?php

namespace App\Services\Progress;

use App\Models\Challenge;
use App\Models\LearningPath;
use App\Models\PathModule;
use App\Models\User;
use App\Models\UserLearningProgress;
use App\Models\UserModuleCompletion;
use Illuminate\Support\Arr;
use App\Services\Progress\Calculators\ChallengeModuleCalculator;
use App\Services\Progress\Calculators\LearningMapCalculator;
use App\Services\Progress\Calculators\PathProgressCalculator;
use App\Services\Progress\Map\MapModule;
use App\Services\Progress\Map\MapModuleCompletion;
use App\Services\Progress\Map\MapPath;
use App\Services\Progress\Support\MetricValue;

class PathProgressService
{
    public function __construct(
        private SubjectLearningPaths $subjectPaths,
        private ChallengeModuleCalculator $challenges,
        private PathProgressCalculator $paths,
        private LearningMapCalculator $map,
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
        $data = $this->loadForSubject($subject);
        if ($data === null) {
            return [];
        }

        [$paths, $byModule, $challenges, $completions] = $data;

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

    /**
     * Individual learning map for a subject (RF-013.1–.4): consultable paths
     * with deterministically ordered modules and only the elements with an
     * explicit link. Single source for the own and mentor map reads, sharing
     * the batched queries of forSubject.
     *
     * @return array<int, MapPath>
     */
    public function mapForSubject(User $subject): array
    {
        $data = $this->loadForSubject($subject);
        if ($data === null) {
            return [];
        }

        [$paths, $byModule, $challenges, $completions] = $data;

        return $paths->map(function (LearningPath $path) use ($byModule, $challenges, $completions): MapPath {
            $moduleRows = ($byModule->get($path->id) ?? collect())
                ->map(fn (PathModule $module): array => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'order' => $module->order,
                ])
                ->all();

            $ordered = $this->map->orderModules($moduleRows);

            $placed = $this->map->placeChallenges(
                array_column($ordered, 'id'),
                $challenges->map(fn (Challenge $challenge): array => [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'status' => $challenge->status,
                    'path_module_id' => $challenge->path_module_id,
                ])->all(),
            );

            $modules = array_map(
                function (array $row) use ($challenges, $completions, $placed): MapModule {
                    $linked = $challenges->where('path_module_id', $row['id'])
                        ->map(fn (Challenge $challenge): array => ['status' => $challenge->status])
                        ->all();
                    $record = $completions->get($row['id']);

                    return new MapModule(
                        id: $row['id'],
                        title: $row['title'],
                        order: $row['order'],
                        activityRate: $this->challenges->activityRate($linked),
                        completion: new MapModuleCompletion(
                            state: $this->challenges->moduleCompletion($linked),
                            completedAt: $record?->completed_at?->toIso8601String(),
                        ),
                        elements: $placed[$row['id']] ?? [],
                    );
                },
                $ordered,
            );

            return new MapPath(
                id: $path->id,
                title: $path->title,
                versionKey: $this->map->pathVersionKey($path->id, $moduleRows),
                rate: $this->paths->pathRate(array_map(
                    fn (MapModule $module): array => [
                        'completion' => $module->completion->state,
                        'completed_record' => $completions->get($module->id) !== null,
                    ],
                    $modules,
                )),
                modules: $modules,
            );
        })->values()->all();
    }

    /**
     * Comparable individual rates for a set of subjects (RF-013.6): one
     * challenge-activity rate over each subject's linked challenges plus one
     * rate per consultable path, with its current definition version key so
     * the caller can group same path+version contributions. Same fixed set
     * of batched queries regardless of the subject count — the collective
     * aggregate resolves a whole cohort with a single resolution.
     *
     * @param  array<int, string>  $subjectIds
     * @return array<string, array{
     *     activity: MetricValue,
     *     paths: array<int, array{path_id: string, version_key: string, title: string|array, rate: MetricValue}>
     * }>
     */
    public function ratesForSubjects(array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        [$pathIdsBySubject, $paths, $byModule, $challenges, $completions] = $this->loadForSubjects($subjectIds);

        $result = [];

        foreach ($subjectIds as $subjectId) {
            $pathIds = $pathIdsBySubject[$subjectId] ?? [];

            $moduleIds = $byModule->only($pathIds)
                ->flatten(1)
                ->pluck('id')
                ->all();

            $memberChallenges = $challenges
                ->where('mentee_id', $subjectId)
                ->whereIn('path_module_id', $moduleIds);

            $memberCompletions = $completions
                ->where('user_id', $subjectId)
                ->keyBy('path_module_id');

            $pathRates = [];

            foreach ($pathIds as $pathId) {
                $path = $paths->get($pathId);

                if ($path === null) {
                    continue;
                }

                $moduleRows = ($byModule->get($pathId) ?? collect())
                    ->map(fn (PathModule $module): array => [
                        'id' => $module->id,
                        'title' => $module->title,
                        'order' => $module->order,
                    ])
                    ->all();

                $moduleStates = array_map(
                    function (array $row) use ($memberChallenges, $memberCompletions): array {
                        $linked = $memberChallenges->where('path_module_id', $row['id'])
                            ->map(fn (Challenge $challenge): array => ['status' => $challenge->status])
                            ->all();

                        return [
                            'completion' => $this->challenges->moduleCompletion($linked),
                            'completed_record' => $memberCompletions->get($row['id']) !== null,
                        ];
                    },
                    $this->map->orderModules($moduleRows),
                );

                $pathRates[] = [
                    'path_id' => $path->id,
                    'version_key' => $this->map->pathVersionKey($path->id, $moduleRows),
                    'title' => $path->title,
                    'rate' => $this->paths->pathRate($moduleStates),
                ];
            }

            $result[$subjectId] = [
                'activity' => $this->challenges->activityRate(
                    $memberChallenges->map(fn (Challenge $challenge): array => ['status' => $challenge->status])->all()
                ),
                'paths' => $pathRates,
            ];
        }

        return $result;
    }

    /**
     * Shared batched load: consultable paths, their modules, the subject's
     * linked challenges and module completions — a fixed set of queries
     * regardless of the amount of data.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection}|null
     */
    private function loadForSubject(User $subject): ?array
    {
        [$pathIdsBySubject, $paths, $byModule, $challenges, $completions] = $this->loadForSubjects([$subject->id]);

        $pathIds = $pathIdsBySubject[$subject->id] ?? [];

        if ($pathIds === []) {
            return null;
        }

        return [
            $paths->only($pathIds),
            $byModule->only($pathIds),
            $challenges->where('mentee_id', $subject->id)->values(),
            $completions->where('user_id', $subject->id)->keyBy('path_module_id'),
        ];
    }

    /**
     * Multi-subject batched load behind loadForSubject and ratesForSubjects:
     * the consultable path ids of every subject plus the union of paths,
     * modules, linked challenges and completions, each in one query.
     *
     * @param  array<int, string>  $subjectIds
     * @return array{0: array<string, array<int, string>>, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection, 4: \Illuminate\Support\Collection}
     */
    private function loadForSubjects(array $subjectIds): array
    {
        $progress = UserLearningProgress::query()
            ->whereIn('user_id', $subjectIds)
            ->get(['user_id', 'learning_path_id']);

        $pathIdsBySubject = array_fill_keys($subjectIds, []);

        foreach ($progress->groupBy('user_id') as $userId => $rows) {
            $pathIdsBySubject[$userId] = $rows->pluck('learning_path_id')->unique()->values()->all();
        }

        $allPathIds = $progress->pluck('learning_path_id')->unique()->values()->all();

        if ($allPathIds === []) {
            return [$pathIdsBySubject, collect(), collect(), collect(), collect()];
        }

        $paths = LearningPath::query()
            ->whereIn('id', $allPathIds)
            ->get()
            ->keyBy('id');

        $modules = PathModule::query()
            ->whereIn('learning_path_id', $allPathIds)
            ->orderBy('order')
            ->get();

        $moduleIds = $modules->pluck('id')->all();

        $challenges = $moduleIds === [] ? collect() : Challenge::query()
            ->whereIn('mentee_id', $subjectIds)
            ->whereIn('path_module_id', $moduleIds)
            ->get(['id', 'mentee_id', 'path_module_id', 'status', 'title']);

        $completions = $moduleIds === [] ? collect() : UserModuleCompletion::query()
            ->whereIn('user_id', $subjectIds)
            ->whereIn('path_module_id', $moduleIds)
            ->get();

        return [$pathIdsBySubject, $paths, $modules->toBase()->groupBy('learning_path_id'), $challenges, $completions];
    }
}
