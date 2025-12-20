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
        // Globalne ustawienia CRON
        Schema::create('cron_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Ustawienia CRON per lista
        Schema::create('contact_list_cron_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained()->onDelete('cascade');
            $table->boolean('use_defaults')->default(true);
            $table->unsignedInteger('volume_per_minute')->nullable();
            $table->json('schedule')->nullable(); // Harmonogram tygodniowy
            $table->timestamps();
            
            $table->unique('contact_list_id');
        });

        // Logi uruchomień CRON
        Schema::create('cron_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name', 100);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->unsignedInteger('emails_sent')->default(0);
            $table->unsignedInteger('emails_failed')->default(0);
            $table->text('errors')->nullable();
            $table->json('metadata')->nullable(); // Dodatkowe dane (np. które listy)
            $table->timestamps();
            
            $table->index(['job_name', 'started_at']);
            $table->index('status');
        });

        // Dodaj kolumnę do email_queue dla trackowania harmonogramów
        if (Schema::hasTable('email_queue')) {
            Schema::table('email_queue', function (Blueprint $table) {
                if (!Schema::hasColumn('email_queue', 'scheduled_for')) {
                    $table->timestamp('scheduled_for')->nullable()->after('status');
                }
                if (!Schema::hasColumn('email_queue', 'attempts')) {
                    $table->unsignedTinyInteger('attempts')->default(0)->after('scheduled_for');
                }
                if (!Schema::hasColumn('email_queue', 'last_attempt_at')) {
                    $table->timestamp('last_attempt_at')->nullable()->after('attempts');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_job_logs');
        Schema::dropIfExists('contact_list_cron_settings');
        Schema::dropIfExists('cron_settings');

        if (Schema::hasTable('email_queue')) {
            Schema::table('email_queue', function (Blueprint $table) {
                $table->dropColumn(['scheduled_for', 'attempts', 'last_attempt_at']);
            });
        }
    }
};
