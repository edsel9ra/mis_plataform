<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_type', 20)->default('personal'); // personal, familiar, grupal, empresa
            $table->string('role', 30)->default('mentee'); // super_admin, admin, company_admin, employee, mentor, mentee
            $table->uuid('company_id')->nullable();
            $table->string('name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('provider')->nullable(); // google, linkedin, github
            $table->string('provider_id')->nullable();
            $table->string('avatar')->nullable();
            $table->string('timezone', 50)->default('America/Mexico_City');
            $table->string('locale', 5)->default('es');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_type');
            $table->index('role');
            $table->index('email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
