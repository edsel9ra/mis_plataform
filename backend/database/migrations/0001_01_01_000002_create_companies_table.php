<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name', 200);
            $table->string('tax_id', 50)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('size', 20)->nullable(); // startup, sme, enterprise
            $table->uuid('admin_id');
            $table->uuid('plan_id')->nullable();
            $table->string('subscription_status', 20)->default('trial');
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('admin_id')->references('id')->on('users');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
