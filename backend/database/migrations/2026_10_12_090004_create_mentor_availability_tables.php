<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_availability_windows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone', 50);
            $table->timestamps();

            $table->unique(
                ['user_id', 'day_of_week', 'start_time', 'end_time'],
                'mentor_availability_windows_owner_day_time_unique',
            );
            $table->index(
                ['user_id', 'day_of_week'],
                'mentor_availability_windows_owner_day_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE mentor_availability_windows
            ADD CONSTRAINT mentor_availability_windows_day_of_week_check
            CHECK (day_of_week BETWEEN 0 AND 6)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE mentor_availability_windows
            ADD CONSTRAINT mentor_availability_windows_time_order_check
            CHECK (end_time > start_time)
        SQL);

        Schema::create('mentor_availability_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 10);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('timezone', 50);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'start_date', 'end_date'],
                'mentor_availability_exceptions_owner_date_index',
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE mentor_availability_exceptions
            ADD CONSTRAINT mentor_availability_exceptions_mode_check
            CHECK (mode IN ('block', 'open'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE mentor_availability_exceptions
            ADD CONSTRAINT mentor_availability_exceptions_date_range_check
            CHECK (end_date IS NULL OR end_date >= start_date)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE mentor_availability_exceptions
            ADD CONSTRAINT mentor_availability_exceptions_time_pair_check
            CHECK (
                (start_time IS NULL AND end_time IS NULL)
                OR (start_time IS NOT NULL AND end_time IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_availability_exceptions');
        Schema::dropIfExists('mentor_availability_windows');
    }
};
