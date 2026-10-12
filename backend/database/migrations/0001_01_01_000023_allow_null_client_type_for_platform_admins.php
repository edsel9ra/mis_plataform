<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN client_type DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN client_type DROP NOT NULL');

        DB::table('users')
            ->whereIn('role', ['super_admin', 'admin'])
            ->update(['client_type' => null]);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('client_type')
            ->update(['client_type' => 'personal']);

        DB::statement("ALTER TABLE users ALTER COLUMN client_type SET DEFAULT 'personal'");
        DB::statement('ALTER TABLE users ALTER COLUMN client_type SET NOT NULL');
    }
};
