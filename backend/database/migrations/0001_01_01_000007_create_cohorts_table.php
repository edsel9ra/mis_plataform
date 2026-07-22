<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('max_members')->default(20);
            $table->uuid('plan_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending, active, completed
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('plan_id')->references('id')->on('plans');
        });

        Schema::create('cohort_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cohort_id');
            $table->uuid('user_id');
            $table->uuid('personality_assessment_id')->nullable();
            $table->string('status', 20)->default('active'); // active, dropped, completed
            $table->timestamps();

            $table->foreign('cohort_id')->references('id')->on('cohorts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['cohort_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_members');
        Schema::dropIfExists('cohorts');
    }
};
