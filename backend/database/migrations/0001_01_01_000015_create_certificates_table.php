<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('relationship_id')->nullable();
            $table->string('type', 30)->default('completion'); // completion, skill, mentorship_hours
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ipfs_cid')->nullable();
            $table->string('ipfs_uri')->nullable();
            $table->string('blockchain_tx_hash')->nullable();
            $table->string('blockchain_contract_address')->nullable();
            $table->string('blockchain_token_id')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->nullOnDelete();
            $table->index('user_id');
            $table->index('blockchain_tx_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
