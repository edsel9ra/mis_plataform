<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompetencyAssessmentLinkFactory extends Factory
{
    protected $model = CompetencyAssessmentLink::class;

    private const AUTO = '__auto__';

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assessment_result_id' => null,
            'personality_assessment_id' => null,
            'skill_tag_id' => null,
            'ocean_factor' => null,
        ];
    }

    /**
     * Auto-created sources resolve in afterMaking, once the final user_id
     * (including create() overrides) is known — otherwise the source would be
     * owned by a different subject than the link.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CompetencyAssessmentLink $link): void {
            if ($link->assessment_result_id === self::AUTO) {
                $link->assessment_result_id = $this->newAssessmentResult($link->user_id)->id;
            }
            if ($link->personality_assessment_id === self::AUTO) {
                $link->personality_assessment_id = PersonalityAssessment::factory()
                    ->create(['user_id' => $link->user_id])->id;
            }
        });
    }

    /**
     * Link to a completed catalog AssessmentResult. Without an explicit model
     * one is created for the link's user so subject ownership stays consistent.
     */
    public function forAssessmentResult(?AssessmentResult $result = null): static
    {
        return $this->state(fn () => [
            'assessment_result_id' => $result?->id ?? self::AUTO,
            'personality_assessment_id' => null,
        ]);
    }

    /** Link to a PersonalityAssessment, keeping its test_version origin. */
    public function forPersonalityAssessment(?PersonalityAssessment $assessment = null): static
    {
        return $this->state(fn () => [
            'personality_assessment_id' => $assessment?->id ?? self::AUTO,
            'assessment_result_id' => null,
        ]);
    }

    /** Point the competency side at an existing skill tag. */
    public function forSkillTag(?SkillTag $tag = null): static
    {
        return $this->state(fn () => [
            'skill_tag_id' => $tag?->id ?? SkillTag::create([
                'name' => 'cal-factory-'.Str::random(12),
                'category' => 'test',
            ])->id,
            'ocean_factor' => null,
        ]);
    }

    /** Point the competency side at an OCEAN factor letter. */
    public function forOceanFactor(?string $factor = null): static
    {
        return $this->state(fn () => [
            'ocean_factor' => $factor ?? fake()->randomElement(CompetencyAssessmentLink::OCEAN_FACTORS),
            'skill_tag_id' => null,
        ]);
    }

    private function newAssessmentResult(mixed $userId): AssessmentResult
    {
        $assessment = Assessment::create([
            'slug' => 'cal-factory-'.Str::random(12),
            'title' => ['es' => 'Evaluación de fábrica'],
            'questions' => [['id' => 1, 'options' => ['a', 'b']]],
            'is_active' => true,
        ]);

        return AssessmentResult::create([
            'user_id' => $userId,
            'assessment_id' => $assessment->id,
            'answers' => [['question_id' => 1, 'option_index' => 0]],
            'score' => 80,
            'passed' => true,
            'completed_at' => now(),
        ]);
    }
}
