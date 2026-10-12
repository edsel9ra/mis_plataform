<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->uuid('template_id')->nullable()->after('id');
            $table->foreign('template_id')->references('id')->on('challenges')->nullOnDelete();
            $table->uuid('relationship_id')->nullable()->change();
            $table->uuid('mentee_id')->nullable()->change();
        });

        Schema::table('challenge_check_ins', function (Blueprint $table) {
            $table->unsignedSmallInteger('attention_score')->nullable()->change();
            $table->string('status', 30)->nullable()->after('source');
            $table->uuid('author_id')->nullable()->after('mentee_id');
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('neurocognitive_observations', function (Blueprint $table) {
            $table->jsonb('dimensions')->nullable()->after('engagement_score');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->uuid('relationship_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('neurocognitive_observations', function (Blueprint $table) {
            $table->dropColumn('dimensions');
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->uuid('relationship_id')->nullable(false)->change();
        });

        Schema::table('challenge_check_ins', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn(['author_id', 'status']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
            $table->uuid('relationship_id')->nullable(false)->change();
            $table->uuid('mentee_id')->nullable(false)->change();
        });
    }
};
