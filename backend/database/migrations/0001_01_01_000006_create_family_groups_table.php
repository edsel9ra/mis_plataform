<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('head_user_id');
            $table->string('family_name', 100);
            $table->text('description')->nullable();
            $table->uuid('plan_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('head_user_id')->references('id')->on('users');
            $table->foreign('plan_id')->references('id')->on('plans');
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_group_id');
            $table->uuid('user_id')->nullable();
            $table->string('full_name', 100);
            $table->integer('age')->nullable();
            $table->string('relationship', 50)->nullable(); // spouse, child, parent, etc
            $table->uuid('personality_assessment_id')->nullable();
            $table->timestamps();

            $table->foreign('family_group_id')->references('id')->on('family_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('family_groups');
    }
};
