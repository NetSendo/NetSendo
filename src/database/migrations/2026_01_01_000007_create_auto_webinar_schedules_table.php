<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auto_webinar_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');

            // Schedule type
            $table->enum('schedule_type', ['fixed', 'recurring', 'on_demand', 'evergreen'])->default('recurring');
            // fixed: specific dates/times
            // recurring: weekly schedule
            // on_demand: starts when user registers (with delay)
            // evergreen: "starting in X minutes" illusion

            // Recurring settings
            $table->json('days_of_week')->nullable(); // [0,1,2,3,4,5,6] - Sunday = 0
            $table->json('times_of_day')->nullable(); // ["09:00", "14:00", "19:00"]

            // Fixed dates
            $table->json('fixed_dates')->nullable(); // ["2026-01-15 10:00", "2026-01-20 14:00"]

            // On-demand / Evergreen settings
            $table->unsignedInteger('start_delay_minutes')->nullable(); // Start X minutes after registration
            $table->json('available_slots')->nullable(); // For evergreen: show these times to user

            // Date range
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Limits
            $table->unsignedInteger('max_sessions_per_day')->nullable();
            $table->unsignedInteger('max_attendees_per_session')->nullable();

            // Timezone
            $table->string('timezone')->default('Europe/Warsaw');

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'is_active']);
            $table->index(['schedule_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_webinar_schedules');
    }
};
