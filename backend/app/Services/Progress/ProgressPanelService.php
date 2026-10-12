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
use App\Services\PlanEntitlementService;
use App\Services\Progress\Calculators\ActivityMetricsCalculator;
use App\Services\Progress\Calculators\CommitmentMetricsCalculator;
use App\Services\Progress\Calculators\CompetencyMetricsCalculator;
use App\Services\Progress\Calculators\ObjectiveMetricsCalculator;
use App\Services\Progress\Calculators\SessionMetricsCalculator;
use App\Services\Progress\Support\MetricValue;
use App\Services\Progress\Support\PeriodResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * T-070 — orchestrates the individual progress panel (RF-3..RF-8).
 *
 * Every block is built inside a guard: if a source fails, only that block is
 * marked `incomplete` and the rest of the panel is still returned (case 15).
 * Failures are logged without personal data (P11).
 */
class ProgressPanelService
{
    public function __construct(
        private ObjectiveMetricsCalculator $objectives,
        private SessionMetricsCalculator $sessions,
        private ActivityMetricsCalculator $activities,
        private CompetencyMetricsCalculator $competencies,
        private CommitmentMetricsCalculator $commitments,
        private PlanEntitlementService $entitlements,
    ) {}

    /**
     * The cached payload is viewer-independent (RNF-5): `allow_proposal` is
     * applied afterwards so the same subject+month can be reused across
     * viewers. Redis is never the source of truth — observers invalidate it.
     *
     * @return array<string, mixed>
     */
    public function build(User $subject, ?User $viewer = null, ?CarbonInterface $now = null, ?string $timezone = null): array
    {
        $now = $now ? CarbonImmutable::instance($now) : CarbonImmutable::now();
        $timezone ??= (string) config('app.timezone', 'UTC');

        $panel = Cache::remember(
            $this->cacheKey($subject, $now, $timezone),
            (int) config('progress.cache_ttl', 300),
            fn (): array => $this->compute($subject, $now, $timezone),
        );

        if (isset($panel['objectives'])) {
            $panel['objectives']['allow_proposal'] = $viewer !== null && $viewer->id === $subject->id;
        }

        return $panel;
    }

    public function cacheKey(User $subject, CarbonInterface $now, string $timezone = 'UTC'): string
    {
        $range = PeriodResolver::currentMonthRange($now, $timezone);

        return 'progress:panel:'.$subject->id.':'.$range['from']->format('Y-m');
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(User $subject, CarbonInterface $now, string $timezone): array
    {
        $incomplete = [];
        $panel = ['period' => $this->currentState($now, $timezone)];

        $panel['objectives'] = $this->guard('objectives', $incomplete, fn (): array => $this->objectivesBlock($subject, $now, $timezone));
        $panel['sessions'] = $this->guard('sessions', $incomplete, fn (): array => $this->sessionsBlock($subject, $now, $timezone));
        $panel['activities'] = $this->guard('activities', $incomplete, fn (): array => $this->activitiesBlock($subject, $now, $timezone));
        $panel['competencies'] = $this->guard('competencies', $incomplete, fn (): array => $this->competenciesBlock($subject));
        $panel['commitments'] = $this->guard('commitments', $incomplete, fn (): array => $this->commitmentsBlock($subject, $now, $timezone));

        $panel['incomplete_blocks'] = $incomplete;

        return $panel;
    }

    /**
     * @param  array<int, string>  $incomplete
     * @return array<string, mixed>
     */
    private function guard(string $block, array &$incomplete, callable $builder): array
    {
        try {
            return $builder();
        } catch (Throwable $exception) {
            $incomplete[] = $block;

            Log::warning('Progress panel block failed', [
                'block' => $block,
                'exception' => $exception::class,
            ]);

            return [
                'state' => 'incomplete',
                'incomplete' => true,
                'metric' => MetricValue::incomplete(null),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function objectivesBlock(User $subject, CarbonInterface $now, string $timezone): array
    {
        $objectives = Objective::query()
            ->forOwner('user', $subject->id)
            ->individual()
            ->get()
            ->map(fn (Objective $objective): array => $objective->toArray())
            ->all();

        $metric = $this->objectives->achievement($objectives, $this->currentState($now, $timezone));
        $classification = $this->objectives->classify($objectives, $now);

        return [
            'state' => $metric->state,
            'metric' => $metric->toArray(),
            'by_status' => $this->objectives->byStatus($objectives),
            'overdue' => $classification['overdue'],
            'allow_proposal' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionsBlock(User $subject, CarbonInterface $now, string $timezone): array
    {
        $relationshipIds = MentorshipRelationship::query()->forUser($subject)->pluck('id')->all();

        $sessions = $relationshipIds === []
            ? collect()
            : Session::query()->whereIn('relationship_id', $relationshipIds)->with('attendees')->get();

        $attendeeMap = [];

        foreach ($sessions as $session) {
            foreach ($session->attendees as $attendee) {
                $attendeeMap[$session->id][$attendee->user_id] = ['attended' => (bool) $attendee->attended];
            }
        }

        $arrays = $sessions->map(fn (Session $session): array => $session->toArray())->all();

        $metric = $this->sessions->realized($arrays, $attendeeMap, $subject->id, $now, $timezone);

        return [
            'state' => $metric->state,
            'metric' => $metric->toArray(),
            'scheduled' => $this->sessions->scheduled($arrays, $now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activitiesBlock(User $subject, CarbonInterface $now, string $timezone): array
    {
        $challenges = Challenge::query()
            ->where('mentee_id', $subject->id)
            ->get()
            ->map(fn (Challenge $challenge): array => $challenge->toArray())
            ->all();

        $paths = UserLearningProgress::query()
            ->where('user_id', $subject->id)
            ->get()
            ->map(fn (UserLearningProgress $progress): array => $progress->toArray())
            ->all();

        $classification = $this->activities->classify($challenges, $paths, $now);

        $metric = MetricValue::make($classification['total_records'], $classification['pending_count'], [
            'source' => 'challenges+paths',
            'period' => $this->currentState($now, $timezone),
        ]);

        return [
            'state' => $metric->state,
            'metric' => $metric->toArray(),
            'pending' => $classification['pending'],
            'overdue' => $classification['overdue'],
            'completed' => $classification['completed'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function competenciesBlock(User $subject): array
    {
        $skills = UserSkill::query()
            ->where('user_id', $subject->id)
            ->with('skillTag')
            ->get()
            ->map(fn (UserSkill $skill): array => [
                'id' => $skill->id,
                'tag' => $skill->skillTag?->name,
                'category' => $skill->skillTag?->category,
                'level' => $skill->level,
            ])
            ->all();

        $ocean = PersonalityAssessment::query()->where('user_id', $subject->id)->first();

        $oceanArray = $ocean === null ? null : [
            'factors' => $ocean->getOceanScores(),
            'completed_at' => $ocean->completed_at?->toIso8601String(),
            'test_version' => $ocean->test_version,
        ];

        $access = $this->entitlements->forModel($subject, 'personal');
        $entitled = $this->entitlements->allows($access['plan'], PlanEntitlementService::OCEAN);

        return $this->competencies->build($skills, $oceanArray, $entitled);
    }

    /**
     * @return array<string, mixed>
     */
    private function commitmentsBlock(User $subject, CarbonInterface $now, string $timezone): array
    {
        $relationshipIds = MentorshipRelationship::query()->forUser($subject)->pluck('id')->all();

        $sessions = $relationshipIds === []
            ? []
            : Session::query()->whereIn('relationship_id', $relationshipIds)
                ->get()
                ->map(fn (Session $session): array => $session->toArray())
                ->all();

        $challenges = Challenge::query()
            ->where('mentee_id', $subject->id)
            ->get()
            ->map(fn (Challenge $challenge): array => $challenge->toArray())
            ->all();

        $objectives = Objective::query()
            ->forOwner('user', $subject->id)
            ->individual()
            ->get()
            ->map(fn (Objective $objective): array => $objective->toArray())
            ->all();

        $window = (int) config('progress.commitments_window_days', 7);

        $items = $this->commitments->build($sessions, $challenges, $objectives, $now, $window, $timezone);

        $metric = MetricValue::make(count($items), count($items), [
            'source' => 'sessions+challenges+objectives',
            'period' => PeriodResolver::resolve(PeriodResolver::NEXT_7_DAYS, $now, $timezone),
        ]);

        return [
            'state' => $metric->state,
            'metric' => $metric->toArray(),
            'items' => $items,
        ];
    }

    /**
     * @return array{type: string, from: ?string, to: ?string, label: string}
     */
    private function currentState(CarbonInterface $now, string $timezone): array
    {
        return PeriodResolver::resolve(PeriodResolver::CURRENT_STATE, $now, $timezone);
    }
}
