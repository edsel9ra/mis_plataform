<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('relationship_id');
            $table->uuid('sender_id');
            $table->text('content');
            $table->string('type', 20)->default('text'); // text, file, system
            $table->string('attachment_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('relationship_id')->references('id')->on('mentorship_relationships')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users');
            $table->index('relationship_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
