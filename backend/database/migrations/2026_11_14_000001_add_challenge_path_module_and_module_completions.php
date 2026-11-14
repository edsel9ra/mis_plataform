<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->uuid('path_module_id')->nullable()->index();
            $table->foreign('path_module_id')->references('id')->on('path_modules')->nullOnDelete();
        });

        Schema::create('user_module_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('path_module_id');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('path_module_id')->references('id')->on('path_modules')->cascadeOnDelete();
            $table->unique(['user_id', 'path_module_id']);
            $table->index('path_module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_completions');

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropForeign(['path_module_id']);
            $table->dropIndex(['path_module_id']);
            $table->dropColumn('path_module_id');
        });
    }
};
