<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->index('relationship_id');
        });

        Schema::table('mentorship_relationships', function (Blueprint $table) {
            $table->index(['mentor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['relationship_id']);
        });

        Schema::table('mentorship_relationships', function (Blueprint $table) {
            $table->dropIndex(['mentor_id', 'status']);
        });
    }
};
