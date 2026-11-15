<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit many-to-many link between one completed individual result source
 * (catalog AssessmentResult XOR personality PersonalityAssessment) and one
 * existing competency (skill tag XOR OCEAN factor). The schema enforces
 * exactly one source and exactly one competency per row; this model only
 * exposes the discriminators and inverse lookups — it never derives
 * evidence, completeness or competency levels.
 */
class CompetencyAssessmentLink extends Model
{
    use HasFactory, HasUuids;

    public const SOURCE_ASSESSMENT_RESULT = 'assessment_result';

    public const SOURCE_PERSONALITY_ASSESSMENT = 'personality_assessment';

    public const COMPETENCY_SKILL_TAG = 'skill_tag';

    public const COMPETENCY_OCEAN_FACTOR = 'ocean_factor';

    public const OCEAN_FACTORS = ['O', 'C', 'E', 'A', 'N'];

    protected $fillable = [
        'user_id',
        'assessment_result_id',
        'personality_assessment_id',
        'skill_tag_id',
        'ocean_factor',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessmentResult(): BelongsTo
    {
        return $this->belongsTo(AssessmentResult::class);
    }

    public function personalityAssessment(): BelongsTo
    {
        return $this->belongsTo(PersonalityAssessment::class);
    }

    public function skillTag(): BelongsTo
    {
        return $this->belongsTo(SkillTag::class);
    }

    /** Which of the two discriminated sources this link points at. */
    public function sourceType(): string
    {
        return $this->assessment_result_id !== null
            ? self::SOURCE_ASSESSMENT_RESULT
            : self::SOURCE_PERSONALITY_ASSESSMENT;
    }

    /** Which competency kind this link points at. */
    public function competencyType(): string
    {
        return $this->skill_tag_id !== null
            ? self::COMPETENCY_SKILL_TAG
            : self::COMPETENCY_OCEAN_FACTOR;
    }

    /**
     * Stable identifier of the competency side: the skill tag UUID or the
     * OCEAN factor letter, exactly as stored.
     */
    public function competencyKey(): string
    {
        return $this->skill_tag_id ?? $this->ocean_factor;
    }

    /**
     * The linked result source, preserving its own kind: an AssessmentResult
     * (with its Assessment) or a PersonalityAssessment (with test_version).
     */
    public function source(): AssessmentResult|PersonalityAssessment
    {
        $source = $this->sourceType() === self::SOURCE_ASSESSMENT_RESULT
            ? $this->assessmentResult
            : $this->personalityAssessment;

        // A NULL here means the row is inconsistent with its own CHECK
        // constraint, which the schema guarantees cannot happen.
        assert($source !== null);

        return $source;
    }
}
