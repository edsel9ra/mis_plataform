<?php

use App\Services\Progress\Calculators\CompetencyEvidenceCalculator;

/**
 * T014.3 — pure evidence/completeness classifier. Operates on normalized
 * arrays only: no database, clock or inference, and it never writes levels.
 */

function calLink(string $id, string $sourceType, string $sourceId, string $competencyType, string $competencyKey): array
{
    return [
        'id' => $id,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'competency_type' => $competencyType,
        'competency_key' => $competencyKey,
    ];
}

it('classifies a completed source as current evidence with its date', function (): void {
    $state = CompetencyEvidenceCalculator::evidenceState(['completed_at' => '2026-11-01T10:00:00Z']);

    expect($state)->toBe('current');
});

it('classifies an existing but uncompleted source as incomplete, never current', function (): void {
    expect(CompetencyEvidenceCalculator::evidenceState(['completed_at' => null]))->toBe('incomplete');
});

it('classifies a missing source distinctly from incomplete', function (): void {
    expect(CompetencyEvidenceCalculator::evidenceState(null))->toBe('missing_source');
    expect(CompetencyEvidenceCalculator::evidenceState(null))->not->toBe('incomplete');
    expect(CompetencyEvidenceCalculator::evidenceState(null))->not->toBe('current');
});

it('builds per-competency evidence only from explicit links', function (): void {
    $links = [
        calLink('l1', 'assessment_result', 'r1', 'skill_tag', 'skill-1'),
        calLink('l2', 'personality_assessment', 'p1', 'ocean_factor', 'C'),
    ];
    $sources = [
        'assessment_result' => [
            'r1' => ['completed_at' => '2026-11-01T10:00:00Z', 'instrument' => 'Evaluación catálogo'],
        ],
        'personality_assessment' => [
            'p1' => ['completed_at' => '2026-11-02T10:00:00Z', 'test_version' => 'ipip-neo-120'],
        ],
    ];

    $evidence = CompetencyEvidenceCalculator::build($links, $sources, true);

    // Keyed by competency; a competency without a link has no entry at all
    // (sin relación → sin evidencia, nunca inferida).
    expect(array_keys($evidence))->toBe(['skill_tag:skill-1', 'ocean_factor:C']);
    expect($evidence['skill_tag:skill-1'][0])->toMatchArray([
        'link_id' => 'l1',
        'state' => 'current',
        'source_type' => 'assessment_result',
        'completed_at' => '2026-11-01T10:00:00Z',
    ]);
    expect($evidence['ocean_factor:C'][0]['state'])->toBe('current');
    expect($evidence['ocean_factor:C'][0]['test_version'])->toBe('ipip-neo-120');
    // A personality source never fabricates an Assessment reference.
    expect($evidence['ocean_factor:C'][0]['instrument'])->toBeNull();
});

it('marks uncompleted and missing sources without presenting them as current', function (): void {
    $links = [
        calLink('l1', 'assessment_result', 'r1', 'skill_tag', 's1'),
        calLink('l2', 'assessment_result', 'r2', 'skill_tag', 's2'),
    ];
    $sources = [
        'assessment_result' => [
            'r1' => ['completed_at' => null, 'instrument' => 'Evaluación'],
            // r2 deleted — no row in the source map.
        ],
    ];

    $evidence = CompetencyEvidenceCalculator::build($links, $sources, true);

    expect($evidence['skill_tag:s1'][0]['state'])->toBe('incomplete');
    expect($evidence['skill_tag:s1'][0]['completed_at'])->toBeNull();
    expect($evidence['skill_tag:s2'][0]['state'])->toBe('missing_source');
    expect($evidence['skill_tag:s2'][0]['completed_at'])->toBeNull();
});

it('blocks OCEAN evidence without entitlement without exposing source details', function (): void {
    $links = [
        calLink('l1', 'personality_assessment', 'p1', 'ocean_factor', 'O'),
        calLink('l2', 'assessment_result', 'r1', 'skill_tag', 's1'),
    ];
    $sources = [
        'assessment_result' => ['r1' => ['completed_at' => '2026-11-01T10:00:00Z']],
        'personality_assessment' => ['p1' => ['completed_at' => '2026-11-01T10:00:00Z', 'test_version' => 'v1']],
    ];

    $evidence = CompetencyEvidenceCalculator::build($links, $sources, false);

    expect($evidence['ocean_factor:O'][0])->toMatchArray([
        'link_id' => 'l1',
        'state' => 'blocked',
        'reason' => 'entitlement_missing',
    ]);
    // No source fields leak when blocked.
    expect($evidence['ocean_factor:O'][0])->not->toHaveKey('test_version');
    expect($evidence['ocean_factor:O'][0])->not->toHaveKey('completed_at');

    // Skill evidence is unaffected by the OCEAN entitlement.
    expect($evidence['skill_tag:s1'][0]['state'])->toBe('current');
});

it('collects several links on the same competency in input order', function (): void {
    $links = [
        calLink('l2', 'assessment_result', 'r2', 'skill_tag', 's1'),
        calLink('l1', 'assessment_result', 'r1', 'skill_tag', 's1'),
    ];
    $sources = [
        'assessment_result' => [
            'r1' => ['completed_at' => '2026-11-01T10:00:00Z'],
            'r2' => ['completed_at' => null],
        ],
    ];

    $entries = CompetencyEvidenceCalculator::build($links, $sources, true)['skill_tag:s1'];

    expect(array_column($entries, 'link_id'))->toBe(['l2', 'l1']);
    expect($entries[0]['state'])->toBe('incomplete');
    expect($entries[1]['state'])->toBe('current');
});

it('never carries answers, scores or levels in the evidence output', function (): void {
    $links = [calLink('l1', 'assessment_result', 'r1', 'skill_tag', 's1')];
    $sources = [
        'assessment_result' => [
            'r1' => [
                'completed_at' => '2026-11-01T10:00:00Z',
                'instrument' => 'Evaluación',
                'answers' => ['secret'],
                'score' => 99,
                'raw_scores' => ['x' => 1],
                'level' => 'expert',
            ],
        ],
    ];

    $flat = json_encode(CompetencyEvidenceCalculator::build($links, $sources, true));

    foreach (['"answers"', '"score"', '"raw_scores"', '"level"', 'secret', 'expert', '99'] as $forbidden) {
        expect($flat)->not->toContain($forbidden);
    }
});

it('does not mutate its inputs and writes no level anywhere', function (): void {
    $links = [calLink('l1', 'assessment_result', 'r1', 'skill_tag', 's1')];
    $sources = ['assessment_result' => ['r1' => ['completed_at' => '2026-11-01T10:00:00Z']]];
    $before = json_encode([$links, $sources]);

    $evidence = CompetencyEvidenceCalculator::build($links, $sources, true);

    expect(json_encode([$links, $sources]))->toBe($before);
    expect(json_encode($evidence))->not->toContain('level');
});
