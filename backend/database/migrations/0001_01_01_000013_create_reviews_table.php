<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->uuid('rater_id');
            $table->tinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
            $table->foreign('rater_id')->references('id')->on('users');
            $table->unique(['session_id', 'rater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
