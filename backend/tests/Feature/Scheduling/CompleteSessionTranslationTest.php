<?php

use App\Actions\CompleteSessionAction;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('scheduling', 'lifecycle', 'i18n');

/**
 * @return array{0: Session, 1: User, 2: User}
 */
function completeTranslationSession(string $status, Carbon $scheduledAt): array
{
    $mentor = User::factory()->mentor()->create();
    $client = User::factory()->create();
    $relationship = MentorshipRelationship::factory()->create([
        'type' => 'personal',
        'source_type' => 'user',
        'source_id' => $client->id,
        'mentor_id' => $mentor->id,
    ]);
    $session = Session::factory()->create([
        'relationship_id' => $relationship->id,
        'scheduled_at' => $scheduledAt,
        'duration_minutes' => 60,
        'status' => $status,
    ]);

    return [$session, $mentor, $client];
}

it('returns the translated domain error when completing before the end', function (string $locale, string $expected): void {
    Carbon::setTestNow(dbInstant('2026-10-14 09:00:00'));
    [$session, $mentor] = completeTranslationSession('scheduled', Carbon::now()->addHour());

    $response = $this->actingAs($mentor, 'sanctum')
        ->withHeader('X-MIS-Locale', $locale)
        ->putJson("/api/v1/sessions/{$session->id}/status", ['status' => 'completed']);

    $response->assertStatus(422);
    expect($response->json('errors.session.0'))->toBe($expected);
})->with([
    'es' => ['es', 'La sesión solo puede completarse después de su hora de fin.'],
    'en' => ['en', 'The session can only be completed after its end time.'],
    'pt' => ['pt', 'A sessão só pode ser concluída após o horário de término.'],
]);

it('returns the translated domain error on a terminal session', function (string $locale, string $expected): void {
    Carbon::setTestNow(dbInstant('2026-10-14 09:00:00'));
    [$session, $mentor] = completeTranslationSession('canceled', Carbon::now()->subHours(3));

    $response = $this->actingAs($mentor, 'sanctum')
        ->withHeader('X-MIS-Locale', $locale)
        ->putJson("/api/v1/sessions/{$session->id}/status", ['status' => 'completed']);

    $response->assertStatus(422);
    expect($response->json('errors.session.0'))->toBe($expected);
})->with([
    'es' => ['es', 'La sesión está en un estado terminal y no puede completarse.'],
    'en' => ['en', 'The session is in a terminal state and cannot be completed.'],
    'pt' => ['pt', 'A sessão está em um estado terminal e não pode ser concluída.'],
]);

it('returns the translated domain error after the completion window closed', function (string $locale, string $expected): void {
    Carbon::setTestNow(dbInstant('2026-10-14 09:00:00'));
    [$session, $mentor] = completeTranslationSession('in_progress', Carbon::now()->subHours(60));

    $response = $this->actingAs($mentor, 'sanctum')
        ->withHeader('X-MIS-Locale', $locale)
        ->putJson("/api/v1/sessions/{$session->id}/status", ['status' => 'completed']);

    $response->assertStatus(422);
    expect($response->json('errors.session.0'))->toBe($expected);
})->with([
    'es' => ['es', 'La ventana para completar la sesión ya se cerró.'],
    'en' => ['en', 'The window to complete the session has closed.'],
    'pt' => ['pt', 'O prazo para concluir a sessão já foi encerrado.'],
]);

it('returns the translated domain error before a scheduled session starts', function (string $locale, string $expected): void {
    // Reachable only with a manipulated schedule: now >= end but before start.
    Carbon::setTestNow(dbInstant('2026-10-14 09:00:00'));
    [$session, $mentor] = completeTranslationSession('scheduled', Carbon::now()->addHour());
    DB::table('sessions')->where('id', $session->id)->update(['end_at' => Carbon::now()->subMinutes(30)]);

    $response = $this->actingAs($mentor, 'sanctum')
        ->withHeader('X-MIS-Locale', $locale)
        ->putJson("/api/v1/sessions/{$session->id}/status", ['status' => 'completed']);

    $response->assertStatus(422);
    expect($response->json('errors.session.0'))->toBe($expected);
})->with([
    'es' => ['es', 'La sesión aún no ha comenzado y no puede completarse.'],
    'en' => ['en', 'The session has not started yet and cannot be completed.'],
    'pt' => ['pt', 'A sessão ainda não começou e não pode ser concluída.'],
]);

it('translates the invalid schedule domain error', function (string $locale, string $expected): void {
    // `sessions.end_at` is NOT NULL, so LifecycleRules::INVALID_SCHEDULE is
    // unreachable through the real endpoints; assert the message itself.
    app()->setLocale($locale);

    expect(__('sessions.complete_invalid_schedule'))->toBe($expected);
})->with([
    'es' => ['es', 'La sesión no tiene un horario de finalización válido.'],
    'en' => ['en', 'The session does not have a valid end time.'],
    'pt' => ['pt', 'A sessão não tem um horário de término válido.'],
]);

it('returns the translated forbidden error for a non-mentor participant', function (string $locale, string $expected): void {
    Carbon::setTestNow(dbInstant('2026-10-14 09:00:00'));
    [$session, , $client] = completeTranslationSession('scheduled', Carbon::now()->subHours(3));

    try {
        app()->setLocale($locale);
        app(CompleteSessionAction::class)->execute($session->id, $client, now());
        $this->fail('Expected AuthorizationException');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe($expected);
    }
})->with([
    'es' => ['es', 'Solo el mentor de la relación puede completar la sesión.'],
    'en' => ['en', 'Only the relationship mentor can complete the session.'],
    'pt' => ['pt', 'Apenas o mentor da relação pode concluir a sessão.'],
]);

it('keeps every complete_* key translated in es, en and pt', function (): void {
    $keys = [
        'complete_forbidden',
        'complete_terminal',
        'complete_invalid_schedule',
        'complete_not_ended',
        'complete_window_closed',
        'complete_not_started',
    ];

    foreach (['es', 'en', 'pt'] as $locale) {
        $messages = require base_path("lang/{$locale}/sessions.php");
        foreach ($keys as $key) {
            expect($messages[$key] ?? null, "{$locale}::{$key}")
                ->toBeString()
                ->not->toBe("sessions.{$key}");
        }
    }
});
