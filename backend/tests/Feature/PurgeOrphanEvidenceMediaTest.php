<?php

use App\Models\Challenge;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses()->group('progress', 'objectives', 'objective-evidence-media');

beforeEach(function () {
    Storage::fake('local');
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');

    $this->mentor = User::withoutSyncingToSearch(fn () => User::factory()->mentor()->create([
        'client_type' => 'personal',
    ]));
    $this->mentor->assignRole('mentor');

    $this->mentee = User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
    $this->mentee->assignRole('mentee');

    $this->relationship = MentorshipRelationship::create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $this->mentee->id,
        'mentor_id' => $this->mentor->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $this->objective = Objective::factory()->individual($this->mentee)->create([
        'relationship_id' => $this->relationship->id,
        'created_by' => $this->mentor->id,
    ]);
});

function purgeEvidenceAsset(Objective $objective, User $uploader, int $ageHours = 25): MediaAsset
{
    $asset = MediaAsset::factory()->create([
        'relationship_id' => $objective->relationship_id,
        'uploaded_by' => $uploader->id,
        'purpose' => 'objective_evidence',
    ]);
    $asset->mediaable()->associate($objective);
    $asset->save();
    Storage::disk($asset->disk)->put($asset->path, 'evidence content');
    MediaAsset::where('id', $asset->id)->update(['created_at' => now()->subHours($ageHours)]);

    return $asset->refresh();
}

function purgeLinkedEvidence(Objective $objective, User $uploader, int $ageHours = 25): array
{
    $asset = purgeEvidenceAsset($objective, $uploader, $ageHours);
    $evidence = ObjectiveEvidence::create([
        'objective_id' => $objective->id,
        'user_id' => $uploader->id,
        'media_asset_id' => $asset->id,
    ]);
    $asset->mediaable()->associate($evidence);
    $asset->save();

    return [$evidence, $asset->refresh()];
}

it('purges an unlinked evidence asset older than 24 hours', function () {
    $asset = purgeEvidenceAsset($this->objective, $this->mentee, 25);

    $this->artisan('media:purge-orphan-evidence')->assertSuccessful();

    expect(MediaAsset::find($asset->id))->toBeNull()
        ->and(Storage::disk('local')->exists($asset->path))->toBeFalse();
});

it('keeps an unlinked evidence asset younger than 24 hours', function () {
    $asset = purgeEvidenceAsset($this->objective, $this->mentee, 2);

    $this->artisan('media:purge-orphan-evidence')->assertSuccessful();

    expect(MediaAsset::find($asset->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($asset->path))->toBeTrue();
});

it('keeps an old asset that is linked to an evidence', function () {
    [, $asset] = purgeLinkedEvidence($this->objective, $this->mentee, 48);

    $this->artisan('media:purge-orphan-evidence')->assertSuccessful();

    expect(MediaAsset::find($asset->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($asset->path))->toBeTrue();
});

it('denies the file after the evidence is deleted and then purges the orphan', function () {
    [$evidence, $asset] = purgeLinkedEvidence($this->objective, $this->mentee, 48);

    $eventsBefore = DB::table('evidence_deletion_events')->count();
    $activityBefore = DB::table('activity_log')->count();

    // Simulate the cascade removing the evidence row.
    ObjectiveEvidence::where('id', $evidence->id)->delete();

    $this->actingAs($this->mentee)->getJson("/api/v1/media/{$asset->id}")->assertForbidden();

    $this->artisan('media:purge-orphan-evidence')->assertSuccessful();

    expect(MediaAsset::find($asset->id))->toBeNull()
        ->and(Storage::disk('local')->exists($asset->path))->toBeFalse()
        ->and(DB::table('evidence_deletion_events')->count())->toBe($eventsBefore)
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('leaves media assets of other purposes untouched', function () {
    $feedback = MediaAsset::factory()->forRelationship($this->relationship)->create([
        'purpose' => 'mentor_feedback',
    ]);
    $challenge = MediaAsset::factory()->forRelationship($this->relationship)->create([
        'purpose' => 'challenge_content',
    ]);
    foreach ([$feedback, $challenge] as $asset) {
        Storage::disk($asset->disk)->put($asset->path, 'content');
        MediaAsset::where('id', $asset->id)->update(['created_at' => now()->subHours(72)]);
    }

    $this->artisan('media:purge-orphan-evidence')->assertSuccessful();

    foreach ([$feedback, $challenge] as $asset) {
        expect(MediaAsset::find($asset->id))->not->toBeNull()
            ->and(Storage::disk('local')->exists($asset->path))->toBeTrue();
    }
});

it('keeps the row and fails when the file cannot be deleted', function () {
    $asset = purgeEvidenceAsset($this->objective, $this->mentee, 25);
    $other = Objective::factory()->individual($this->mentee)->create(['created_by' => $this->mentor->id]);
    $second = purgeEvidenceAsset($other, $this->mentee, 25);

    $stub = Mockery::mock(Illuminate\Contracts\Filesystem\Filesystem::class);
    $stub->shouldReceive('delete')->with($asset->path)->andReturn(false);
    $stub->shouldReceive('exists')->with($asset->path)->andReturn(true);
    $stub->shouldReceive('delete')->with($second->path)->andReturn(true);
    $stub->shouldReceive('exists')->with($second->path)->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($stub);

    $this->artisan('media:purge-orphan-evidence')->assertFailed();

    // The failed deletion keeps its row and file; the other orphan still purges.
    expect(MediaAsset::find($asset->id))->not->toBeNull()
        ->and(MediaAsset::find($second->id))->toBeNull();
});

it('purges the row when the file is already gone from disk', function () {
    $asset = MediaAsset::factory()->create([
        'relationship_id' => $this->objective->relationship_id,
        'uploaded_by' => $this->mentee->id,
        'purpose' => 'objective_evidence',
    ]);
    $asset->mediaable()->associate($this->objective);
    $asset->save();
    MediaAsset::where('id', $asset->id)->update(['created_at' => now()->subHours(25)]);
    // Deliberately no Storage::put: the file is already missing.

    $this->artisan('media:purge-orphan-evidence')->assertSuccessful();

    expect(MediaAsset::find($asset->id))->toBeNull();
});

it('only reports the count with --dry-run', function () {
    purgeEvidenceAsset($this->objective, $this->mentee, 25);
    $other = Objective::factory()->individual($this->mentee)->create(['created_by' => $this->mentor->id]);
    $second = purgeEvidenceAsset($other, $this->mentee, 25);

    $this->artisan('media:purge-orphan-evidence', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('2');

    expect(MediaAsset::whereIn('id', [$second->id])->count())->toBe(1)
        ->and(MediaAsset::where('purpose', 'objective_evidence')->count())->toBe(2);
});
