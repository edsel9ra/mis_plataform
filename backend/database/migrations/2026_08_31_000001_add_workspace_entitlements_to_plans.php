<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('client_type');
            $table->jsonb('entitlements')->nullable()->after('features');
        });

        $workspaceEntitlements = json_encode([
            'mentor_workspace' => true,
            'ocean' => true,
            'attention_tracking' => true,
            'emotional_check_ins' => true,
            'challenges' => true,
            'challenge_media' => true,
            'challenge_feedback' => true,
            'mentor_observations' => true,
            'systemic_maps' => true,
        ], JSON_THROW_ON_ERROR);

        DB::table('plans')
            ->where('client_type', 'personal')
            ->whereNull('entitlements')
            ->update(['entitlements' => $workspaceEntitlements]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'entitlements']);
        });
    }
};
