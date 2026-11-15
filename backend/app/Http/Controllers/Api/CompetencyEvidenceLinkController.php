<?php

namespace App\Http\Controllers\Api;

use App\Actions\RemoveCompetencyAssessmentLinkAction;
use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Http\Requests\Progress\SetCompetencyEvidenceLinkRequest;
use App\Http\Resources\CompetencyEvidenceLinkResource;
use App\Models\CompetencyAssessmentLink;
use App\Models\User;
use App\Services\PlanEntitlementService;
use App\Services\Progress\ProgressAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Competency evidence-link management (RF-014.4, .9).
 *
 * Same access matrix as the progress panel via ProgressAccessService: the
 * owner manages own links; the assigned mentor manages the mentee's under
 * the mentor_workspace entitlement (support keeps read-only rights). OCEAN
 * competency links additionally require the OCEAN entitlement on the
 * subject's plan. Foreign subjects, foreign links and foreign sources deny
 * without disclosure.
 */
class CompetencyEvidenceLinkController
{
    public function __construct(
        private SetCompetencyAssessmentLinkAction $set,
        private RemoveCompetencyAssessmentLinkAction $remove,
        private ProgressAccessService $access,
        private PlanEntitlementService $entitlements,
    ) {}

    public function store(SetCompetencyEvidenceLinkRequest $request): CompetencyEvidenceLinkResource
    {
        $subject = $request->user();
        $this->access->assertUserPanel($subject, $subject);

        return new CompetencyEvidenceLinkResource($this->apply($request, $subject, null));
    }

    public function update(SetCompetencyEvidenceLinkRequest $request, CompetencyAssessmentLink $link): CompetencyEvidenceLinkResource
    {
        $subject = $request->user();
        $this->access->assertUserPanel($subject, $subject);

        return new CompetencyEvidenceLinkResource($this->apply($request, $subject, $link));
    }

    public function destroy(Request $request, CompetencyAssessmentLink $link): Response
    {
        $subject = $request->user();
        $this->access->assertUserPanel($subject, $subject);
        $this->remove->execute($subject, $subject, $link, now());

        return response()->noContent();
    }

    public function storeFor(SetCompetencyEvidenceLinkRequest $request, User $user): CompetencyEvidenceLinkResource
    {
        $this->access->assertUserPanel($request->user(), $user);

        return new CompetencyEvidenceLinkResource($this->apply($request, $user, null));
    }

    public function updateFor(SetCompetencyEvidenceLinkRequest $request, User $user, CompetencyAssessmentLink $link): CompetencyEvidenceLinkResource
    {
        $this->access->assertUserPanel($request->user(), $user);

        return new CompetencyEvidenceLinkResource($this->apply($request, $user, $link));
    }

    public function destroyFor(Request $request, User $user, CompetencyAssessmentLink $link): Response
    {
        $this->access->assertUserPanel($request->user(), $user);
        $this->remove->execute($user, $request->user(), $link, now());

        return response()->noContent();
    }

    private function apply(SetCompetencyEvidenceLinkRequest $request, User $subject, ?CompetencyAssessmentLink $link): CompetencyAssessmentLink
    {
        $competencyType = $request->validated('competency_type');
        $this->assertOceanEntitled($subject, $competencyType);

        return $this->set->execute(
            $subject,
            $request->user(),
            $link,
            $request->validated('source_type'),
            $request->validated('source_id'),
            $competencyType,
            $request->validated('competency_key'),
            now(),
        );
    }

    /**
     * OCEAN competency links require the subject plan's OCEAN entitlement —
     * the same entitlement that already gates the personality block.
     */
    private function assertOceanEntitled(User $subject, string $competencyType): void
    {
        if ($competencyType !== CompetencyAssessmentLink::COMPETENCY_OCEAN_FACTOR) {
            return;
        }

        $access = $this->entitlements->forModel($subject, 'personal');

        abort_unless(
            $this->entitlements->allows($access['plan'], PlanEntitlementService::OCEAN),
            403,
            __('progress.messages.entitlement_missing'),
        );
    }
}
