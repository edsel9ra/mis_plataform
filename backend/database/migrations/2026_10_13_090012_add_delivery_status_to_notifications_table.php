<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('status', 12)->default('sent');
            $table->timestamp('sent_at')->nullable();
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_delivery_status_check CHECK (status IN ('pending', 'sent', 'failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_delivery_status_check');

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn(['status', 'sent_at']);
        });
    }
};
