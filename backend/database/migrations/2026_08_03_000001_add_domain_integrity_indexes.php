<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX subscriptions_one_current_per_subscriber
            ON subscriptions (subscriber_type, subscriber_id)
            WHERE status IN ('trial', 'active')
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX subscriptions_one_lifetime_trial_per_subscriber
            ON subscriptions (subscriber_type, subscriber_id)
            WHERE trial_ends_at IS NOT NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX mentorship_relationships_one_live_source_mentor
            ON mentorship_relationships (source_type, source_id, mentor_id)
            WHERE deleted_at IS NULL AND status IN ('pending', 'active')
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX companies_one_live_per_admin
            ON companies (admin_id)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS subscriptions_one_current_per_subscriber');
        DB::statement('DROP INDEX IF EXISTS subscriptions_one_lifetime_trial_per_subscriber');
        DB::statement('DROP INDEX IF EXISTS mentorship_relationships_one_live_source_mentor');
        DB::statement('DROP INDEX IF EXISTS companies_one_live_per_admin');
    }
};
