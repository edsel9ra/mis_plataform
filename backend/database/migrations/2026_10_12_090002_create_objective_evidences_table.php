<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objective_evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('objective_id');
            $table->uuid('user_id')->nullable();
            $table->text('note')->nullable();
            $table->uuid('media_asset_id')->nullable();
            $table->timestamps();

            // A retired/removed source must not invalidate past evidence (P10).
            $table->foreign('objective_id')->references('id')->on('objectives')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('media_asset_id')->references('id')->on('media_assets')->nullOnDelete();

            $table->index('objective_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objective_evidences');
    }
};
