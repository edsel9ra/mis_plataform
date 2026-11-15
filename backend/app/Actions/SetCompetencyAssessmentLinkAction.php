<?php

namespace App\Actions;

use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create or repoint one competency–assessment link in a transaction
 * (RF-014.3–.5).
 *
 * Server-side ownership is enforced here, never left to the UI: the link's
 * user, the result source and the subject must coincide; a source that does
 * not belong to the subject fails as ModelNotFound so its existence is not
 * disclosed. Only completed individual results can be linked; the source
 * kind is preserved (`AssessmentResult` or `PersonalityAssessment`, never a
 * fabricated `Assessment` for OCEAN). UserSkill.level is never touched.
 */
class SetCompetencyAssessmentLinkAction
{
    /**
     * @param  CompetencyAssessmentLink|null  $link  existing link to repoint, or null to create
     */
    public function execute(
        User $subject,
        User $actor,
        ?CompetencyAssessmentLink $link,
        string $sourceType,
        string $sourceId,
        string $competencyType,
        string $competencyKey,
        CarbonInterface $now,
    ): CompetencyAssessmentLink {
        return DB::transaction(function () use ($subject, $actor, $link, $sourceType, $sourceId, $competencyType, $competencyKey, $now): CompetencyAssessmentLink {
            $locked = null;
            if ($link !== null) {
                $locked = CompetencyAssessmentLink::query()
                    ->whereKey($link->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || $locked->user_id !== $subject->id) {
                    throw (new ModelNotFoundException)->setModel(CompetencyAssessmentLink::class, [$link->getKey()]);
                }
            }

            [$sourceColumn, $sourceValue] = $this->resolveSource($subject, $sourceType, $sourceId);
            [$competencyColumn, $competencyValue] = $this->resolveCompetency($competencyType, $competencyKey);

            $duplicate = CompetencyAssessmentLink::query()
                ->where('user_id', $subject->id)
                ->where($sourceColumn, $sourceValue)
                ->where($competencyColumn, $competencyValue)
                ->when($locked, fn ($q) => $q->whereKeyNot($locked->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'competency' => [__('validation.competency_evidence_link_duplicate')],
                ]);
            }

            $before = $locked !== null ? $this->relationSnapshot($locked) : null;

            $target = $locked ?? new CompetencyAssessmentLink;
            $target->fill([
                'user_id' => $subject->id,
                'assessment_result_id' => null,
                'personality_assessment_id' => null,
                'skill_tag_id' => null,
                'ocean_factor' => null,
                $sourceColumn => $sourceValue,
                $competencyColumn => $competencyValue,
            ])->save();

            activity('progress')
                ->causedBy($actor)
                ->performedOn($target)
                ->event('competency.evidence_link_changed')
                ->withProperties([
                    'link_id' => $target->id,
                    'subject_id' => $subject->id,
                    'actor_id' => $actor->id,
                    'before' => $before,
                    'after' => $this->relationSnapshot($target),
                    'occurred_at' => $now->toIso8601String(),
                ])
                ->log('competency.evidence_link_changed');

            return $target;
        });
    }

    /**
     * Resolve the discriminated source column, verifying the row exists,
     * belongs to the subject and is completed. A foreign or absent row is a
     * ModelNotFound (existence is not disclosed); an uncompleted one is a
     * validation error (RF-014.3 requires a completed individual result).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveSource(User $subject, string $sourceType, string $sourceId): array
    {
        [$column, $model] = match ($sourceType) {
            CompetencyAssessmentLink::SOURCE_ASSESSMENT_RESULT => ['assessment_result_id', AssessmentResult::class],
            CompetencyAssessmentLink::SOURCE_PERSONALITY_ASSESSMENT => ['personality_assessment_id', PersonalityAssessment::class],
            default => throw ValidationException::withMessages([
                'source' => [__('validation.competency_evidence_link_source_type')],
            ]),
        };

        $source = Str::isUuid($sourceId) ? $model::query()->whereKey($sourceId)->first() : null;

        if ($source === null || $source->user_id !== $subject->id) {
            throw (new ModelNotFoundException)->setModel($model, [$sourceId]);
        }

        if ($source->completed_at === null) {
            throw ValidationException::withMessages([
                'source' => [__('validation.competency_evidence_link_source_incomplete')],
            ]);
        }

        return [$column, $source->id];
    }

    /**
     * Resolve the discriminated competency column. A missing skill tag is a
     * ModelNotFound; an unknown type or OCEAN factor is a validation error.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveCompetency(string $competencyType, string $competencyKey): array
    {
        return match ($competencyType) {
            CompetencyAssessmentLink::COMPETENCY_SKILL_TAG => Str::isUuid($competencyKey)
                ? ['skill_tag_id', SkillTag::query()->whereKey($competencyKey)->firstOrFail()->id]
                : throw ValidationException::withMessages([
                    'competency' => [__('validation.competency_evidence_link_competency_type')],
                ]),
            CompetencyAssessmentLink::COMPETENCY_OCEAN_FACTOR => in_array($competencyKey, CompetencyAssessmentLink::OCEAN_FACTORS, true)
                ? ['ocean_factor', $competencyKey]
                : throw ValidationException::withMessages([
                    'competency' => [__('validation.competency_evidence_link_ocean_factor')],
                ]),
            default => throw ValidationException::withMessages([
                'competency' => [__('validation.competency_evidence_link_competency_type')],
            ]),
        };
    }

    /**
     * @return array{source_type: string, source_id: ?string, competency_type: string, competency_key: ?string}
     */
    private function relationSnapshot(CompetencyAssessmentLink $link): array
    {
        return [
            'source_type' => $link->sourceType(),
            'source_id' => $link->assessment_result_id ?? $link->personality_assessment_id,
            'competency_type' => $link->competencyType(),
            'competency_key' => $link->competencyKey(),
        ];
    }
}
