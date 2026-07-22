<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('manager_name', 100)->nullable();
            $table->string('status', 20)->default('invited'); // invited, active, inactive
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
