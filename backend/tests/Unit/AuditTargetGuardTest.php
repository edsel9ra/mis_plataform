<?php

use App\Support\AuditTargetGuard;

it('refuses any database in production, even an empty *_audit one', function (): void {
    expect(AuditTargetGuard::refusal('production', 'mis_project_audit', 0))->toBe('production');
});

it('refuses the development database', function (): void {
    expect(AuditTargetGuard::refusal('local', 'mis_project', 0))->toBe('not_audit_db');
});

it('refuses the testing database', function (): void {
    expect(AuditTargetGuard::refusal('testing', 'mis_project_testing', 0))->toBe('not_audit_db');
});

it('refuses a database whose name does not end in _audit', function (): void {
    expect(AuditTargetGuard::refusal('local', 'foo_audit_x', 0))->toBe('not_audit_db');
});

it('refuses an *_audit database that already has users', function (): void {
    expect(AuditTargetGuard::refusal('local', 'mis_project_audit', 1))->toBe('not_empty');
});

it('allows an empty *_audit database outside production', function (): void {
    expect(AuditTargetGuard::refusal('local', 'mis_project_audit', 0))->toBeNull();
});
