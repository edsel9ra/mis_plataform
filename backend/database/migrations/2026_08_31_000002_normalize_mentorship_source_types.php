<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'App\\Models\\User' => 'user',
            'App\\Models\\FamilyGroup' => 'family_group',
            'App\\Models\\Cohort' => 'cohort',
            'App\\Models\\Company' => 'company',
        ] as $legacyType => $canonicalType) {
            DB::table('mentorship_relationships')
                ->where('source_type', $legacyType)
                ->update(['source_type' => $canonicalType]);
        }
    }

    public function down(): void
    {
        // Canonical values are intentionally retained. Reintroducing class names
        // would make polymorphic queries and authorization less predictable.
    }
};
