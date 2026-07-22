<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_type', 20); // personal, familiar, grupal, empresa
            $table->jsonb('name'); // multi-lang: {"es": "...", "en": "...", "pt": "..."}
            $table->jsonb('description')->nullable();
            $table->jsonb('features')->nullable();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2);
            $table->integer('max_sessions_per_month')->default(4);
            $table->integer('max_members')->default(1);
            $table->integer('max_mentors')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_type');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::dropIfExists('plans');
    }
};
