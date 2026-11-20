<?php

use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\FeedbackEntry;
use App\Models\FeedbackThread;
use App\Models\MediaAsset;
use App\Models\MentorshipRelationship;
use App\Models\Objective;
use App\Models\ObjectiveEvidence;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses()->group('progress', 'objectives', 'objective-evidence-media');

beforeEach(function () {
    Storage::fake('local');
    Role::findOrCreate('mentor');
    Role::findOrCreate('mentee');
    Permission::findOrCreate('progress.view.support', 'web');

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

function evidenceMediaUser(): User
{
    return User::withoutSyncingToSearch(fn () => User::factory()->create([
        'client_type' => 'personal',
    ]));
}

function evidenceMediaAssetFor(Objective $objective, User $uploader, array $overrides = []): MediaAsset
{
    $asset = MediaAsset::factory()->create(array_merge([
        'relationship_id' => $objective->relationship_id,
        'uploaded_by' => $uploader->id,
        'purpose' => 'objective_evidence',
    ], $overrides));
    $asset->mediaable()->associate($objective);
    $asset->save();

    return $asset;
}

function uploadEvidenceFile(User $actor, Objective $objective): object
{
    return test()->actingAs($actor)->postJson(
        "/api/v1/objectives/{$objective->id}/media",
        ['file' => UploadedFile::fake()->create('evidence.pdf', 128, 'application/pdf')],
    );
}

function assertStoredEvidenceAsset(object $response, Objective $objective, User $uploader): MediaAsset
{
    $response->assertCreated()
        ->assertJsonPath('data.purpose', 'objective_evidence')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonStructure(['data' => ['id', 'original_name', 'size']]);

    $asset = MediaAsset::findOrFail($response->json('data.id'));
    expect($asset->purpose)->toBe('objective_evidence')
        ->and($asset->mediaable)->toBeInstanceOf(Objective::class)
        ->and($asset->mediaable->id)->toBe($objective->id)
        ->and($asset->uploaded_by)->toBe($uploader->id)
        ->and($asset->relationship_id)->toBe($objective->relationship_id)
        ->and(Storage::disk('local')->exists($asset->path))->toBeTrue();

    return $asset;
}

it('lets the objective titular upload an evidence file', function () {
    assertStoredEvidenceAsset(uploadEvidenceFile($this->mentee, $this->objective), $this->objective, $this->mentee);
});

it('lets the assigned mentor upload an evidence file', function () {
    assertStoredEvidenceAsset(uploadEvidenceFile($this->mentor, $this->objective), $this->objective, $this->mentor);
});

it('lets a member of a collective objective upload an evidence file', function () {
    $member = evidenceMediaUser();
    $cohort = Cohort::create([
        'created_by' => $member->id,
        'name' => 'Cohorte evidencia',
        'status' => 'active',
    ]);
    CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $member->id, 'status' => 'active']);
    $collective = Objective::factory()->collective('cohort', $cohort->id)->create([
        'created_by' => $member->id,
    ]);

    assertStoredEvidenceAsset(uploadEvidenceFile($member, $collective), $collective, $member);
});

it('lets support upload an evidence file', function () {
    $support = evidenceMediaUser();
    $support->givePermissionTo(Permission::findOrCreate('progress.view.support', 'web'));

    assertStoredEvidenceAsset(uploadEvidenceFile($support, $this->objective), $this->objective, $support);
});

it('forbids an unrelated user from uploading an evidence file', function () {
    uploadEvidenceFile(evidenceMediaUser(), $this->objective)->assertForbidden();
});

it('requires authentication to upload an evidence file', function () {
    $this->postJson("/api/v1/objectives/{$this->objective->id}/media", [
        'file' => UploadedFile::fake()->create('evidence.pdf', 128, 'application/pdf'),
    ])->assertUnauthorized();
});

it('rejects a file whose mime type is not allowed', function () {
    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/media", [
            'file' => UploadedFile::fake()->create('payload.exe', 64, 'application/x-msdownload'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('rejects a file larger than 25 MB', function () {
    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/media", [
            'file' => UploadedFile::fake()->create('huge.pdf', 26 * 1024, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('creates an evidence from a freshly uploaded asset and reparents it', function () {
    $asset = assertStoredEvidenceAsset(uploadEvidenceFile($this->mentee, $this->objective), $this->objective, $this->mentee);

    $response = $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'note' => 'Evidencia con fichero',
            'media_asset_id' => $asset->id,
        ]);

    $response->assertCreated();
    $asset->refresh();
    expect($asset->mediaable)->toBeInstanceOf(ObjectiveEvidence::class)
        ->and($asset->mediaable->objective_id)->toBe($this->objective->id);
});

it('rejects a media asset that belongs to a different objective', function () {
    $other = Objective::factory()->individual($this->mentee)->create(['created_by' => $this->mentor->id]);
    $asset = evidenceMediaAssetFor($other, $this->mentee);

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'media_asset_id' => $asset->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_asset_id');
});

it('rejects a media asset uploaded by someone else', function () {
    $asset = evidenceMediaAssetFor($this->objective, $this->mentor);

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'media_asset_id' => $asset->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_asset_id');
});

it('rejects a media asset already linked to an evidence', function () {
    $evidence = ObjectiveEvidence::create([
        'objective_id' => $this->objective->id,
        'user_id' => $this->mentee->id,
        'note' => 'Primera evidencia',
    ]);
    $asset = evidenceMediaAssetFor($this->objective, $this->mentee);
    $asset->mediaable()->associate($evidence);
    $asset->save();

    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'media_asset_id' => $asset->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_asset_id');
});

it('rejects a media asset with a different purpose', function () {
    $asset = MediaAsset::factory()->forRelationship($this->relationship)->create([
        'purpose' => 'mentor_feedback',
    ]);

    $this->actingAs($this->mentor)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'media_asset_id' => $asset->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_asset_id');
});

it('serves an evidence file to every authorized figure and forbids a third party', function () {
    $asset = assertStoredEvidenceAsset(uploadEvidenceFile($this->mentee, $this->objective), $this->objective, $this->mentee);
    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'media_asset_id' => $asset->id,
        ])
        ->assertCreated();
    Storage::disk('local')->put($asset->path, 'evidence content');

    $support = evidenceMediaUser();
    $support->givePermissionTo(Permission::findOrCreate('progress.view.support', 'web'));

    $member = evidenceMediaUser();
    $cohort = Cohort::create(['created_by' => $member->id, 'name' => 'Cohorte B', 'status' => 'active']);
    CohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $member->id, 'status' => 'active']);
    $collective = Objective::factory()->collective('cohort', $cohort->id)->create(['created_by' => $member->id]);
    $collectiveAsset = evidenceMediaAssetFor($collective, $member);
    $collectiveEvidence = ObjectiveEvidence::create([
        'objective_id' => $collective->id,
        'user_id' => $member->id,
        'media_asset_id' => $collectiveAsset->id,
    ]);
    $collectiveAsset->mediaable()->associate($collectiveEvidence);
    $collectiveAsset->save();
    Storage::disk('local')->put($collectiveAsset->path, 'collective evidence');

    foreach ([$this->mentee, $this->mentor, $support] as $viewer) {
        $this->actingAs($viewer)->getJson("/api/v1/media/{$asset->id}")->assertOk();
    }
    $this->actingAs($member)->getJson("/api/v1/media/{$collectiveAsset->id}")->assertOk();

    $this->actingAs(evidenceMediaUser())->getJson("/api/v1/media/{$asset->id}")->assertForbidden();
});

it('denies the evidence file once the evidence no longer exists', function () {
    $asset = assertStoredEvidenceAsset(uploadEvidenceFile($this->mentee, $this->objective), $this->objective, $this->mentee);
    $this->actingAs($this->mentee)
        ->postJson("/api/v1/objectives/{$this->objective->id}/evidences", [
            'media_asset_id' => $asset->id,
        ])
        ->assertCreated();
    Storage::disk('local')->put($asset->path, 'evidence content');

    // Simulate the cascade removing the evidence row.
    ObjectiveEvidence::query()->delete();

    $this->actingAs($this->mentee)->getJson("/api/v1/media/{$asset->id}")->assertForbidden();
});

it('keeps mentor feedback media visibility unchanged', function () {
    $plan = Plan::create([
        'code' => 'evidence_media_feedback_plan',
        'client_type' => 'personal',
        'name' => ['es' => 'evidence_media_feedback_plan'],
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_mentors' => 1,
        'entitlements' => [
            'mentor_workspace' => true,
            'challenge_feedback' => true,
        ],
        'is_active' => true,
    ]);
    Subscription::create([
        'subscriber_type' => 'user',
        'subscriber_id' => $this->mentee->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    $thread = FeedbackThread::factory()->forRelationship($this->relationship)->create();
    $entry = FeedbackEntry::factory()->create(['feedback_thread_id' => $thread->id]);
    $asset = MediaAsset::factory()->forRelationship($this->relationship)->create([
        'purpose' => 'mentor_feedback',
    ]);
    $asset->mediaable()->associate($entry);
    $asset->save();
    Storage::disk('local')->put($asset->path, 'feedback audio');

    $this->actingAs($this->mentor)->getJson("/api/v1/media/{$asset->id}")->assertOk();
    $this->actingAs(evidenceMediaUser())->getJson("/api/v1/media/{$asset->id}")->assertForbidden();
});

it('returns 422 when the asset was deleted between validation and attach', function () {
    $asset = assertStoredEvidenceAsset(uploadEvidenceFile($this->mentee, $this->objective), $this->objective, $this->mentee);

    expect($asset->isAttachableAsEvidence($this->objective, $this->mentee))->toBeTrue();

    $asset->delete();

    try {
        app(\App\Actions\AttachObjectiveEvidenceAction::class)->execute(
            $this->mentee,
            $this->objective,
            ['media_asset_id' => $asset->id, 'note' => 'tarde'],
        );
        $this->fail('Expected ValidationException');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('media_asset_id');
    }

    expect(ObjectiveEvidence::count())->toBe(0);
});

it('returns 422 when the asset was linked to another evidence before attach', function () {
    $asset = assertStoredEvidenceAsset(uploadEvidenceFile($this->mentee, $this->objective), $this->objective, $this->mentee);

    ObjectiveEvidence::create([
        'objective_id' => $this->objective->id,
        'user_id' => $this->mentee->id,
        'media_asset_id' => $asset->id,
    ]);

    try {
        app(\App\Actions\AttachObjectiveEvidenceAction::class)->execute(
            $this->mentee,
            $this->objective,
            ['media_asset_id' => $asset->id, 'note' => 'tarde'],
        );
        $this->fail('Expected ValidationException');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('media_asset_id');
    }

    expect(ObjectiveEvidence::count())->toBe(1);
});
