<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personality_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('test_version', 20)->default('ipip-neo-120'); // ipip-neo-120, ipip-neo-300
            $table->jsonb('answers'); // respuestas crudas {id_question: id_select}
            $table->jsonb('results'); // scores normalizados con facetas
            $table->jsonb('raw_scores')->nullable(); // puntuaciones brutas
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personality_assessments');
    }
};
