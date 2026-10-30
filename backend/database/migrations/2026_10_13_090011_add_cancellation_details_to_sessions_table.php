<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->timestamp('canceled_at')->nullable();
            $table->foreignUuid('canceled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('canceled_late')->default(false);
            $table->integer('cancellation_lead_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['canceled_by']);
            $table->dropColumn([
                'canceled_at',
                'canceled_by',
                'cancellation_reason',
                'canceled_late',
                'cancellation_lead_minutes',
            ]);
        });
    }
};
