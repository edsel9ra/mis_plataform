<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('module_id');
            $table->jsonb('short_name')->nullable()->after('title');
            $table->string('category', 80)->nullable()->after('description');
            $table->jsonb('client_types')->nullable()->after('category');
            $table->boolean('is_active')->default(true)->after('time_limit_minutes');
            $table->jsonb('metadata')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'short_name',
                'category',
                'client_types',
                'is_active',
                'metadata',
            ]);
        });
    }
};
