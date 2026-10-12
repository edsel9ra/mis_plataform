<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ChallengeResource;
use App\Http\Resources\EmotionalCheckInResource;
use App\Http\Resources\NeurocognitiveAlertResource;
use App\Http\Resources\NeurocognitiveObservationResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\EmotionalCheckIn;
use App\Models\NeurocognitiveAlert;
use App\Models\NeurocognitiveObservation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MentorWorkspaceAccessService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorWorkspaceController
{
    public function __construct(
        private MentorWorkspaceAccessService $access,
        private PlanEntitlementService $entitlements,
    ) {}

    public function profile(Request $request, User $user): JsonResponse
    {
        $relationshipId = $this->relationshipId($request);
        $authorized = $this->access->mentorPair($request->user(), $user, $relationshipId);
        $access = $authorized['access'];
        $subscription = $user->subscriptions()->current()->with('plan')->latest()->first();

        return response()->json([
            'user' => (new UserSummaryResource($user))->resolve($request),
            'plan' => $access['plan'] ? [
                'id' => $access['plan']->id,
                'code' => $access['plan']->code,
                'name' => $access['plan']->name,
                'client_type' => $access['plan']->client_type,
                'is_active' => $access['plan']->is_active,
            ] : null,
            'subscription' => $subscription ? (new SubscriptionResource($subscription))->resolve($request) : null,
            'locked' => $access['locked'],
            'lock_reason' => $access['lock_reason'],
            'plan_status' => $access['status'],
            'entitlements' => $access['entitlements'],
            'assignment' => [
                'relationship_id' => $relationshipId,
                'type' => 'personal',
                'locked' => $access['locked'],
                'lock_reason' => $access['lock_reason'],
                'plan' => $access['plan'] ? [
                    'id' => $access['plan']->id,
                    'code' => $access['plan']->code,
                    'name' => $access['plan']->name,
                    'client_type' => $access['plan']->client_type,
                ] : null,
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $relationshipId = $this->relationshipId($request);
        $authorized = $this->access->mentorPair($request->user(), $user, $relationshipId);
        $access = $authorized['access'];
        $relationship = $authorized['relationship'];
        $unlocked = !$access['locked'];
        $attentionAllowed = $this->entitlements->allowsAccess($access, PlanEntitlementService::ATTENTION_TRACKING);
        $observationsAllowed = $attentionAllowed
            && $this->entitlements->allows($access['plan'], PlanEntitlementService::MENTOR_OBSERVATIONS);
        $challengesAllowed = $this->entitlements->allowsAccess($access, PlanEntitlementService::CHALLENGES);
        $challengeMediaAllowed = $this->entitlements->allowsAccess($access, PlanEntitlementService::CHALLENGE_MEDIA);

        $data = [
            'relationship_id' => $relationship->id,
            'mentee' => (new UserSummaryResource($user))->resolve($request),
            'locked' => $access['locked'],
            'lock_reason' => $access['lock_reason'],
            'plan_status' => $access['status'],
            'plan' => $access['plan'] ? [
                'id' => $access['plan']->id,
                'code' => $access['plan']->code,
                'name' => $access['plan']->name,
                'client_type' => $access['plan']->client_type,
            ] : null,
            'entitlements' => $access['entitlements'],
            'attention' => [],
            'emotional_check_ins' => [],
            'challenges' => [],
            'alerts' => [],
        ];

        if ($unlocked) {
            if ($attentionAllowed) {
                $checkIns = ChallengeCheckIn::query()
                    ->where('relationship_id', $relationship->id)
                    ->where('source', 'mentee_self_report')
                    ->latest('checked_in_at')
                    ->limit(10)
                    ->get();
                $observations = $observationsAllowed
                    ? NeurocognitiveObservation::query()
                        ->where('relationship_id', $relationship->id)
                        ->where('mentor_id', $request->user()->id)
                        ->latest('observed_at')
                        ->limit(10)
                        ->get()
                    : collect();
                $data['attention'] = $checkIns->concat($observations)
                    ->sortByDesc(fn ($item) => $item->checked_in_at ?? $item->observed_at)
                    ->take(20)
                    ->values()
                    ->map(fn ($item) => $item instanceof NeurocognitiveObservation
                        ? (new NeurocognitiveObservationResource($item))->resolve($request)
                        : ['id' => $item->id, 'source' => $item->source, 'attention_score' => $item->attention_score, 'note' => $item->note, 'checked_in_at' => $item->checked_in_at])
                    ->all();
            }

            if ($this->entitlements->allowsAccess($access, PlanEntitlementService::EMOTIONAL_CHECK_INS)) {
                $data['emotional_check_ins'] = EmotionalCheckInResource::collection(
                    EmotionalCheckIn::query()
                        ->where('relationship_id', $relationship->id)
                        ->latest('checked_in_at')
                        ->limit(10)
                        ->get(),
                )->resolve($request);
            }

            if ($challengesAllowed) {
                $challengeQuery = Challenge::query()
                    ->where('relationship_id', $relationship->id)
                    ->where('mentor_id', $request->user()->id)
                    ->where('mentee_id', $user->id)
                    ->latest();
                if ($challengeMediaAllowed) {
                    $challengeQuery->with('mediaAssets');
                }

                $data['challenges'] = ChallengeResource::collection(
                    $challengeQuery
                        ->limit(20)
                        ->get(),
                )->resolve($request);
            }

            if ($attentionAllowed) {
                $data['alerts'] = NeurocognitiveAlertResource::collection(
                    NeurocognitiveAlert::query()
                        ->where('relationship_id', $relationship->id)
                        ->whereNull('resolved_at')
                        ->latest('detected_at')
                        ->limit(20)
                        ->get(),
                )->resolve($request);
            }
        }

        return response()->json($data);
    }

    private function relationshipId(Request $request): string
    {
        return $request->validate([
            'relationship_id' => ['required', 'uuid'],
        ])['relationship_id'];
    }
}
