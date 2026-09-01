<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->text('objective')->nullable()->after('instructions');
            $table->text('success_criteria')->nullable()->after('objective');
            $table->string('presentation_format', 80)->nullable()->after('success_criteria');
            $table->string('frequency', 80)->nullable()->after('presentation_format');
            $table->unsignedSmallInteger('progress')->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn([
                'objective',
                'success_criteria',
                'presentation_format',
                'frequency',
                'progress',
            ]);
        });
    }
};
