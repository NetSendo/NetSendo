<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks backup & restore operations so the UI can show real progress and
 * results (issue #26). Each row is one create/restore run driven by a queued
 * job (RunBackupJob / RestoreBackupJob), which updates status/message here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('create');       // create | restore
            $table->string('status')->default('running');     // running | success | failed
            $table->boolean('only_db')->default(false);
            $table->string('filename')->nullable();           // produced (create) / source (restore) archive
            $table->string('safety_backup')->nullable();      // pre-restore DB snapshot filename
            $table->text('message')->nullable();              // error / summary shown to the user
            $table->longText('output')->nullable();           // raw command output (truncated), for diagnostics
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_operations');
    }
};
