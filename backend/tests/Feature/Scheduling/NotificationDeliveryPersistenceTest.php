<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds delivery state without changing the sent semantics of existing notifications', function (): void {
    $user = User::factory()->create();
    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'mentor_alert',
        'data' => ['alert_id' => 'alert-1'],
    ]);

    $statusColumn = DB::selectOne(
        "SELECT is_nullable, column_default FROM information_schema.columns WHERE table_name = 'notifications' AND column_name = 'status'",
    );

    expect(Schema::hasColumn('notifications', 'sent_at'))->toBeTrue()
        ->and($statusColumn->is_nullable)->toBe('NO')
        ->and($statusColumn->column_default)->toContain('sent')
        ->and($notification->fresh()->status)->toBe('sent')
        ->and($notification->fresh()->sent_at)->toBeNull()
        ->and($notification->fresh()->data)->toBe(['alert_id' => 'alert-1']);
});

it('persists pending sent and failed reminder delivery states and sent timestamp', function (): void {
    $user = User::factory()->create();
    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'session_reminder',
        'dedupe_key' => 'session_reminder:session-1:user-1:24:2026-10-14T12:00:00Z',
        'status' => 'pending',
        'data' => ['session_id' => 'session-1'],
    ]);

    expect($notification->fresh()->status)->toBe('pending')
        ->and($notification->fresh()->sent_at)->toBeNull();

    $notification->update(['status' => 'failed']);

    expect($notification->fresh()->status)->toBe('failed')
        ->and($notification->fresh()->sent_at)->toBeNull()
        ->and($notification->fresh()->dedupe_key)
        ->toBe('session_reminder:session-1:user-1:24:2026-10-14T12:00:00Z');

    $sentAt = Carbon::parse('2026-10-13 12:00:00', config('app.timezone'));
    $notification->update(['status' => 'sent', 'sent_at' => $sentAt]);

    expect($notification->fresh()->status)->toBe('sent')
        ->and($notification->fresh()->sent_at->equalTo($sentAt))->toBeTrue();
});

it('rejects unsupported delivery states at the persistence boundary', function (): void {
    $user = User::factory()->create();

    expect(fn () => DB::transaction(fn () => Notification::create([
        'user_id' => $user->id,
        'type' => 'session_reminder',
        'status' => 'delivered',
        'data' => [],
    ])))->toThrow(QueryException::class);
});
