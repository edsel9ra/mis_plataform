<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('subscriber'); // user, company, family_group, cohort
            $table->uuid('plan_id');
            $table->string('status', 20)->default('trial'); // trial, active, past_due, canceled, expired
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('payment_provider', 30)->nullable(); // stripe, mercadopago
            $table->string('payment_id')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
