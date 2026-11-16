<?php

use App\Models\CompetencyAssessmentLink;
use App\Models\EvidenceDeletionEvent;
use App\Models\PersonalityAssessment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * T014.14 — the retention table holds local wall clocks like every other
 * source (plan §10): occurred_at/expires_at are timestamp without time
 * zone, so the stored instant is the app-tz wall clock and reads back as
 * the true instant. Before the fix both columns were timestamptz and the
 * same write landed 6 h early under the UTC session.
 */
uses(RefreshDatabase::class)->group('progress');

const EDTZ_MIGRATION = 'database/migrations/2026_11_16_000001_convert_evidence_deletion_event_times_to_local_timestamp.php';

beforeEach(function (): void {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');

    $this->subject = User::factory()->create(['client_type' => 'personal']);
    $this->admin = User::factory()->superAdmin()->create();
    $this->admin->assignRole('super_admin');
});

function edtzLinkedSource(User $user): PersonalityAssessment
{
    $source = PersonalityAssessment::factory()->create([
        'user_id' => $user->id,
        'completed_at' => now(),
    ]);
    CompetencyAssessmentLink::factory()
        ->forPersonalityAssessment($source)
        ->forOceanFactor('O')
        ->create(['user_id' => $user->id]);

    return $source;
}

it('stores both event times as timestamp without time zone and NOT NULL', function (): void {
    $columns = DB::table('information_schema.columns')
        ->select('column_name', 'data_type', 'is_nullable')
        ->where('table_name', 'evidence_deletion_events')
        ->whereIn('column_name', ['occurred_at', 'expires_at'])
        ->get()
        ->keyBy('column_name');

    expect($columns)->toHaveCount(2);
    expect($columns['occurred_at']->data_type)->toBe('timestamp without time zone')
        ->and($columns['occurred_at']->is_nullable)->toBe('NO')
        ->and($columns['expires_at']->data_type)->toBe('timestamp without time zone')
        ->and($columns['expires_at']->is_nullable)->toBe('NO');
});

it('records the true instant through the real deletion path', function (): void {
    $source = edtzLinkedSource($this->subject);

    $instant = CarbonImmutable::parse('2026-11-15T16:00:00Z');
    $this->travelTo($instant);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    $event = EvidenceDeletionEvent::query()->sole()->fresh();

    expect($event->occurred_at->eq($instant))->toBeTrue()
        ->and($event->expires_at->eq($instant->addMonthsNoOverflow(12)))->toBeTrue();

    $epoch = DB::selectOne(
        'select extract(epoch from (occurred_at AT TIME ZONE ?)) as e from evidence_deletion_events where id = ?',
        [config('app.timezone'), $event->id],
    )->e;

    expect((int) $epoch)->toBe($instant->getTimestamp());
});

it('preserves the stored wall clock through rollback and reapply', function (): void {
    Artisan::call('migrate:rollback', ['--path' => EDTZ_MIGRATION]);

    // Old semantics: the same write lands as a UTC-mislabeled wall clock.
    $source = edtzLinkedSource($this->subject);
    $instant = CarbonImmutable::parse('2026-11-15T16:00:00Z');
    $this->travelTo($instant);
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/assessments/{$source->id}")
        ->assertOk();

    $id = EvidenceDeletionEvent::query()->sole()->id;

    Artisan::call('migrate', ['--path' => EDTZ_MIGRATION]);

    $event = EvidenceDeletionEvent::query()->findOrFail($id);
    expect($event->occurred_at->eq($instant))->toBeTrue()
        ->and($event->expires_at->eq($instant->addMonthsNoOverflow(12)))->toBeTrue();

    // Down then up again: reversible and lossless.
    Artisan::call('migrate:rollback', ['--path' => EDTZ_MIGRATION]);
    Artisan::call('migrate', ['--path' => EDTZ_MIGRATION]);

    $again = EvidenceDeletionEvent::query()->findOrFail($id);
    expect($again->occurred_at->eq($instant))->toBeTrue()
        ->and($again->expires_at->eq($instant->addMonthsNoOverflow(12)))->toBeTrue();
});
