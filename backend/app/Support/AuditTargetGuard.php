<?php

namespace App\Support;

/**
 * Pure refusal rules for the accessibility audit seeder (RF-019.2).
 *
 * The audit dataset may only be written to an empty, disposable database
 * whose name ends in `_audit` — never development (`mis_project`), never
 * tests (`mis_project_testing`), never production, never over existing users.
 */
final class AuditTargetGuard
{
    public static function refusal(string $environment, string $database, int $userCount): ?string
    {
        if ($environment === 'production') {
            return 'production';
        }

        if (! str_ends_with($database, '_audit')) {
            return 'not_audit_db';
        }

        if ($userCount > 0) {
            return 'not_empty';
        }

        return null;
    }
}
