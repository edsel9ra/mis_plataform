<?php

use App\Actions\RemoveCompetencyAssessmentLinkAction;
use App\Actions\SetCompetencyAssessmentLinkAction;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CompetencyAssessmentLink;
use App\Models\PersonalityAssessment;
use App\Models\SkillTag;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use App\Services\Progress\History\CompetencyHistoryAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * T015.4 — competency history feed (RF-015.4, plan §1/§3). Link and
 * source-state changes are whitelisted projections of the operational
 * audit; retirements marked `reason=source_deleted` (Spec 014, CA-19.7)
 * never enter the timeline because they would reveal who deleted a source.
 */
uses(RefreshDatabase::class)->group('progress');

beforeEach(function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');

    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');

    $this->set = app(SetCompetencyAssessmentLinkAction::class);
    $this->remove = app(RemoveCompetencyAssessmentLinkAction::class);
    $this->adapter = new CompetencyHistoryAdapter;
});

function compHistIds(array $events): array
{
    return array_column($events, 'id');
}

function compHistSkillTag(string $name = 'Liderazgo'): SkillTag
{
    return SkillTag::create(['name' => $name, 'category' => 'test']);
}

function compHistCatalogResult(User $user): AssessmentResult
{
    $assessment = Assessment::create([
        'slug' => 'comp-hist-'.Str::random(8),
        'title' => ['es' => 'Catálogo'],
        'questions' => [['id' => 1, 'options' => ['a', 'b']]],
        'is_active' => true,
    ]);

    return AssessmentResult::create([
        'user_id' => $user->id,
        'assessment_id' => $assessment->id,
        'answers' => [['question_id' => 1, 'option_index' => 0]],
        'score' => 80,
        'passed' => true,
        'completed_at' => now(),
    ]);
}

function compHistPersonality(User $user, array $attributes = []): PersonalityAssessment
{
    return PersonalityAssessment::factory()->create(array_merge([
        'user_id' => $user->id,
        'completed_at' => now(),
    ], $attributes));
}

it('emits create, repoint and remove newest-first with whitelisted sides', function (): void {
    $tag = compHistSkillTag();
    $first = compHistCatalogResult($this->subject);
    $second = compHistCatalogResult($this->subject);

    $this->travelTo(CarbonImmutable::parse('2026-11-15T16:00:00Z'));
    $link = $this->set->execute(
        $this->subject, $this->subject, null,
        'assessment_result', $first->id, 'skill_tag', $tag->id,
        CarbonImmutable::parse('2026-11-15T16:00:00Z'),
    );

    $this->travelTo(CarbonImmutable::parse('2026-11-15T17:00:00Z'));
    $this->set->execute(
        $this->subject, $this->subject, $link,
        'assessment_result', $second->id, 'skill_tag', $tag->id,
        CarbonImmutable::parse('2026-11-15T17:00:00Z'),
    );

    $this->travelTo(CarbonImmutable::parse('2026-11-15T18:00:00Z'));
    $this->remove->execute(
        $this->subject, $this->subject, $link,
        CarbonImmutable::parse('2026-11-15T18:00:00Z'),
    );

    $events = $this->adapter->dated($this->subject, null, 25);

    expect($events)->toHaveCount(3);
    foreach ($events as $event) {
        expect($event['type'])->toBe('competency.evidence_link_changed')
            ->and($event['domain'])->toBe('competencies')
            ->and($event['id'])->toStartWith('competency_activity:')
            ->and($event['subject_ref'])->toBe(['link_id' => $link->id])
            ->and($event['actor'])->toBe(['id' => $this->subject->id, 'name' => $this->subject->name])
            ->and($event['incomplete'])->toBe([]);
    }

    // Removal (newest): before carries the whitelisted relation, after is a
    // legitimate explicit null — not incomplete.
    expect($events[0]['before'])->toBe([
        'source_type' => 'assessment_result',
        'competency_type' => 'skill_tag',
        'competency_key' => $tag->id,
        'competency_name' => 'Liderazgo',
    ])->and($events[0]['after'])->toBeNull();

    // Repoint: same competency side before and after.
    expect($events[1]['before']['source_type'])->toBe('assessment_result')
        ->and($events[1]['after']['source_type'])->toBe('assessment_result')
        ->and($events[1]['after']['competency_key'])->toBe($tag->id);

    // Creation: before is a legitimate null.
    expect($events[2]['before'])->toBeNull()
        ->and($events[2]['after']['competency_key'])->toBe($tag->id);

    $json = json_encode($events);
    expect($json)->not->toContain($first->id)
        ->not->toContain($second->id)
        ->not->toContain('source_id');
});

it('names skill_tag competencies and leaves ocean_factor unnamed', function (): void {
    $tag = compHistSkillTag('Comunicación');
    $result = compHistCatalogResult($this->subject);
    $personality = compHistPersonality($this->subject);

    $this->set->execute($this->subject, $this->subject, null, 'assessment_result', $result->id, 'skill_tag', $tag->id, now());
    $this->set->execute($this->subject, $this->subject, null, 'personality_assessment', $personality->id, 'ocean_factor', 'O', now());

    $events = $this->adapter->dated($this->subject, null, 25);
    expect($events)->toHaveCount(2);

    expect($events[0]['after']['competency_type'])->toBe('ocean_factor')
        ->and($events[0]['after']['competency_key'])->toBe('O')
        ->and($events[0]['after']['competency_name'])->toBeNull();
    expect($events[1]['after']['competency_name'])->toBe('Comunicación');
});

it('emits source-state transitions with the actor that caused them', function (): void {
    $source = compHistPersonality($this->subject);
    $link = CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor('O')
        ->create(['user_id' => $this->subject->id]);

    $this->actingAs($this->subject);
    $source->update(['completed_at' => null]);

    // personality_assessments.user_id is unique: the anonymous transition
    // reuses the same source, moving it back to completed without an actor.
    Auth::logout();
    $source->update(['completed_at' => now()->subDay()]);

    $events = $this->adapter->dated($this->subject, null, 25);
    expect($events)->toHaveCount(2);

    foreach ($events as $event) {
        expect($event['type'])->toBe('competency.evidence_source_state_changed')
            ->and($event['subject_ref'])->toBe([
                'source_type' => 'personality_assessment',
                'source_id' => $source->id,
                'link_ids' => [$link->id],
            ]);
        expect(json_encode($event))->not->toContain('completed_at');
    }

    $authed = collect($events)->firstWhere('after.state', 'incomplete');
    expect($authed['before'])->toBe(['state' => 'current'])
        ->and($authed['actor'])->toBe(['id' => $this->subject->id, 'name' => $this->subject->name])
        ->and($authed['incomplete'])->toBe([]);

    $noActor = collect($events)->firstWhere('after.state', 'current');
    expect($noActor['before'])->toBe(['state' => 'incomplete'])
        ->and($noActor['actor'])->toBeNull()
        ->and($noActor['incomplete'])->toContain('actor');
});

it('hides source-deletion retirements while showing manual removals', function (): void {
    $tag = compHistSkillTag();
    $result = compHistCatalogResult($this->subject);
    $linked = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($result)
        ->forSkillTag($tag)
        ->create(['user_id' => $this->subject->id]);

    $other = compHistCatalogResult($this->subject);
    $manual = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($other)
        ->forSkillTag($tag)
        ->create(['user_id' => $this->subject->id]);
    $this->remove->execute($this->subject, $this->subject, $manual, now());

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();

    // The operational audit keeps the real actor plus the marker.
    $retired = Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->get()
        ->firstWhere(fn (Activity $a) => $a->properties['link_id'] === $linked->id);
    expect($retired)->not->toBeNull()
        ->and($retired->properties['reason'])->toBe('source_deleted')
        ->and($retired->properties['actor_id'])->toBe($this->admin->id);

    $manualActivity = Activity::query()
        ->where('event', 'competency.evidence_link_changed')
        ->get()
        ->firstWhere(fn (Activity $a) => $a->properties['link_id'] === $manual->id);
    expect($manualActivity->properties->has('reason'))->toBeFalse();

    // The timeline shows only the manual removal and never the admin.
    $events = $this->adapter->dated($this->subject, null, 25);
    expect($events)->toHaveCount(1);
    expect($events[0]['subject_ref']['link_id'])->toBe($manual->id)
        ->and($events[0]['actor']['id'])->toBe($this->subject->id);

    $json = json_encode($events);
    expect($json)->not->toContain($this->admin->id)
        ->not->toContain($this->admin->name)
        ->not->toContain($linked->id);
});

it('excludes retirement markers in SQL before applying the limit', function (): void {
    $tag = compHistSkillTag();

    // One manual event, older than the retirement batch.
    $old = compHistCatalogResult($this->subject);
    $manual = CompetencyAssessmentLink::factory()
        ->forAssessmentResult($old)
        ->forSkillTag($tag)
        ->create(['user_id' => $this->subject->id]);
    $this->remove->execute($this->subject, $this->subject, $manual, now());

    $this->travelTo(now()->addHour());

    // Three newer retirements through one admin deletion.
    $result = compHistCatalogResult($this->subject);
    foreach (range(1, 3) as $i) {
        $t = compHistSkillTag("habilidad-{$i}");
        CompetencyAssessmentLink::factory()
            ->forAssessmentResult($result)
            ->forSkillTag($t)
            ->create(['user_id' => $this->subject->id]);
    }

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/evaluation-catalog/{$result->assessment_id}")
        ->assertOk();

    // Naive post-LIMIT filtering would return an empty page; SQL filtering
    // reaches the only showable event.
    $page = $this->adapter->dated($this->subject, null, 1);
    expect($page)->toHaveCount(1)
        ->and($page[0]['subject_ref']['link_id'])->toBe($manual->id);

    $events = $this->adapter->dated($this->subject, null, 25);
    expect($events)->toHaveCount(1)
        ->and($events[0]['subject_ref']['link_id'])->toBe($manual->id);
});

it('isolates competency events strictly to the subject', function (): void {
    $other = User::factory()->create(['client_type' => 'personal']);
    $tag = compHistSkillTag();
    $result = compHistCatalogResult($other);

    $this->set->execute($other, $other, null, 'assessment_result', $result->id, 'skill_tag', $tag->id, now());

    $source = compHistPersonality($other);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor('O')
        ->create(['user_id' => $other->id]);
    $source->update(['completed_at' => null]);

    expect($this->adapter->dated($this->subject, null, 25))->toBe([]);
});

it('never infers level changes as history (plan D-1)', function (): void {
    $tag = compHistSkillTag();
    $skill = UserSkill::create([
        'user_id' => $this->subject->id,
        'skill_tag_id' => $tag->id,
        'level' => 1,
    ]);
    $skill->update(['level' => 3]);

    expect($this->adapter->dated($this->subject, null, 25))->toBe([]);
});

it('pages through ties exactly once in compare() order', function (): void {
    $tag = compHistSkillTag();
    $instant = CarbonImmutable::parse('2026-11-15T16:00:00Z');
    $this->travelTo($instant);

    // Three link events at the same instant.
    foreach (['a', 'b', 'c'] as $i => $suffix) {
        $t = compHistSkillTag("tag-{$suffix}");
        $this->set->execute(
            $this->subject, $this->subject, null,
            'assessment_result', compHistCatalogResult($this->subject)->id,
            'skill_tag', $t->id, $instant,
        );
    }

    // A source-state event at the same instant.
    $source = compHistPersonality($this->subject);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor('O')
        ->create(['user_id' => $this->subject->id]);
    $source->update(['completed_at' => null]);

    $expected = compHistIds(
        ProgressHistoryCalculator::mergePage([$this->adapter->dated($this->subject, null, 50)], null, 50)['data'],
    );
    expect($expected)->toHaveCount(4);

    foreach ([1, 2] as $limit) {
        $seen = [];
        $after = null;
        $guard = 0;
        do {
            $page = ProgressHistoryCalculator::mergePage(
                [$this->adapter->dated($this->subject, $after, $limit)],
                $after,
                $limit,
            );
            $seen = array_merge($seen, compHistIds($page['data']));
            $after = $page['next_cursor'] !== null
                ? ProgressHistoryCalculator::decodeCursor($page['next_cursor'])
                : null;
        } while ($after !== null && ++$guard < 30);

        expect($seen)->toBe($expected);
    }
});

it('uses a constant number of queries per dated() call', function (): void {
    $tag = compHistSkillTag();
    $this->set->execute(
        $this->subject, $this->subject, null,
        'assessment_result', compHistCatalogResult($this->subject)->id,
        'skill_tag', $tag->id, now(),
    );

    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->adapter->dated($this->subject, null, 25);

        return $queries;
    };

    $small = $count();

    foreach (range(1, 5) as $i) {
        $t = compHistSkillTag("tag-extra-{$i}");
        $this->set->execute(
            $this->subject, $this->subject, null,
            'assessment_result', compHistCatalogResult($this->subject)->id,
            'skill_tag', $t->id, now(),
        );
    }

    $big = $count();

    expect($big)->toBe($small)->and($big)->toBeLessThanOrEqual(4);
});

it('returns no undated events', function (): void {
    expect($this->adapter->undated($this->subject, 10))->toBe([]);
});
