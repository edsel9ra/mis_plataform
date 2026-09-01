<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\ChallengeSubmission;
use App\Models\EmotionalCheckIn;
use App\Models\MentorshipRelationship;
use App\Models\NeurocognitiveAlert;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MentorAlertDetectionService
{
    public function __construct(
        private PlanEntitlementService $entitlements,
    ) {}

    public function detect(): int
    {
        $now = now();
        $stagnationDays = (int) config('mentor.alerts.stagnation_days', 7);
        $disengagementDays = (int) config('mentor.alerts.disengagement_days', 7);
        $created = 0;

        MentorshipRelationship::query()
            ->where('type', 'personal')
            ->whereIn('source_type', ['user', User::class])
            ->where('status', 'active')
            ->with('source')
            ->chunkById(100, function (Collection $relationships) use (&$created, $now, $stagnationDays, $disengagementDays) {
                foreach ($relationships as $relationship) {
                    $mentee = $relationship->source;
                    if (!$mentee || !$mentee->is_active) {
                        continue;
                    }

                    $access = $this->entitlements->forModel($mentee, 'personal');
                    if (!$this->entitlements->allowsAccess($access, PlanEntitlementService::ATTENTION_TRACKING)) {
                        continue;
                    }

                    $lastActivity = $this->lastMenteeActivity($relationship);
                    if (!$lastActivity || $lastActivity->lte($now->copy()->subDays($disengagementDays))) {
                        $created += $this->createAlert(
                            $relationship,
                            'disengagement',
                            $now,
                            $lastActivity,
                            $disengagementDays,
                        );
                    }

                    $activeChallenges = Challenge::query()
                        ->where('relationship_id', $relationship->id)
                        ->whereIn('status', ['assigned', 'in_progress'])
                        ->get();
                    $lastProgress = $this->lastChallengeProgress($relationship, $activeChallenges);

                    if ($activeChallenges->isNotEmpty()
                        && (!$lastProgress || $lastProgress->lte($now->copy()->subDays($stagnationDays)))) {
                        $created += $this->createAlert(
                            $relationship,
                            'stagnation',
                            $now,
                            $lastProgress,
                            $stagnationDays,
                            ['active_challenges' => $activeChallenges->count()],
                        );
                    }
                }
            });

        return $created;
    }

    private function lastMenteeActivity(MentorshipRelationship $relationship): ?CarbonInterface
    {
        return $this->latestDate([
            EmotionalCheckIn::query()->where('relationship_id', $relationship->id)->max('checked_in_at'),
            ChallengeCheckIn::query()
                ->where('relationship_id', $relationship->id)
                ->where('source', 'mentee_self_report')
                ->max('checked_in_at'),
            ChallengeSubmission::query()->where('relationship_id', $relationship->id)->max('submitted_at'),
        ]);
    }

    /** @param Collection<int, Challenge> $challenges */
    private function lastChallengeProgress(MentorshipRelationship $relationship, Collection $challenges): ?CarbonInterface
    {
        $challengeIds = $challenges->pluck('id');
        if ($challengeIds->isEmpty()) {
            return null;
        }

        return $this->latestDate([
            $challenges->sortByDesc('created_at')->first()?->created_at,
            ChallengeCheckIn::query()->whereIn('challenge_id', $challengeIds)->max('checked_in_at'),
            ChallengeSubmission::query()->whereIn('challenge_id', $challengeIds)->max('submitted_at'),
        ]);
    }

    private function latestDate(array $values): ?CarbonInterface
    {
        return collect($values)
            ->filter()
            ->map(fn ($value) => $value instanceof CarbonInterface ? $value : Carbon::parse($value))
            ->sort()
            ->last();
    }

    private function createAlert(
        MentorshipRelationship $relationship,
        string $type,
        CarbonInterface $now,
        ?CarbonInterface $lastActivity,
        int $thresholdDays,
        array $extra = [],
    ): int {
        $window = $now->copy()->startOfWeek()->toDateString();
        $dedupeKey = "mentor_alert:{$relationship->id}:{$type}:{$window}";
        $data = array_merge([
            'relationship_id' => $relationship->id,
            'mentee_id' => $relationship->source_id,
            'type' => $type,
            'threshold_days' => $thresholdDays,
            'last_activity_at' => $lastActivity?->toIso8601String(),
        ], $extra);

        $alert = NeurocognitiveAlert::firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'relationship_id' => $relationship->id,
                'mentor_id' => $relationship->mentor_id,
                'mentee_id' => $relationship->source_id,
                'type' => $type,
                'severity' => $type === 'disengagement' ? 'high' : 'medium',
                'data' => $data,
                'detected_at' => $now,
            ],
        );

        Notification::firstOrCreate(
            ['dedupe_key' => "notification:{$dedupeKey}"],
            [
                'user_id' => $relationship->mentor_id,
                'type' => 'mentor_alert',
                'data' => [
                    'alert_id' => $alert->id,
                    'relationship_id' => $relationship->id,
                    'alert_type' => $type,
                ],
            ],
        );

        return $alert->wasRecentlyCreated ? 1 : 0;
    }
}
