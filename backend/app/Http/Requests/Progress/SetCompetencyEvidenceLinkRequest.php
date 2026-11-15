<?php

namespace App\Http\Requests\Progress;

use App\Models\CompetencyAssessmentLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload for creating or repointing a competency evidence link
 * (RF-014.4). The subject never comes from the client — it is resolved by
 * the route — and the persisted discriminators are never client-writable.
 */
class SetCompetencyEvidenceLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSkill = $this->input('competency_type') === CompetencyAssessmentLink::COMPETENCY_SKILL_TAG;
        $isOcean = $this->input('competency_type') === CompetencyAssessmentLink::COMPETENCY_OCEAN_FACTOR;

        return [
            'source_type' => [
                'required',
                'string',
                Rule::in([
                    CompetencyAssessmentLink::SOURCE_ASSESSMENT_RESULT,
                    CompetencyAssessmentLink::SOURCE_PERSONALITY_ASSESSMENT,
                ]),
            ],
            'source_id' => ['required', 'uuid'],
            'competency_type' => [
                'required',
                'string',
                Rule::in([
                    CompetencyAssessmentLink::COMPETENCY_SKILL_TAG,
                    CompetencyAssessmentLink::COMPETENCY_OCEAN_FACTOR,
                ]),
            ],
            'competency_key' => [
                'required',
                'string',
                'max:64',
                Rule::when($isSkill, ['uuid']),
                Rule::when($isOcean, [Rule::in(CompetencyAssessmentLink::OCEAN_FACTORS)]),
            ],

            // Subject and stored discriminator columns are never supplied.
            'user_id' => ['prohibited'],
            'assessment_result_id' => ['prohibited'],
            'personality_assessment_id' => ['prohibited'],
            'skill_tag_id' => ['prohibited'],
            'ocean_factor' => ['prohibited'],
        ];
    }
}
