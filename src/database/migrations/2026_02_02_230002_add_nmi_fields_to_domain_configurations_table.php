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
        Schema::table('domain_configurations', function (Blueprint $table) {
            // NMI integration
            $table->foreignId('dedicated_ip_id')->nullable()->after('mailbox_id')
                ->constrained('dedicated_ip_addresses')->onDelete('set null');

            // NMI enablement
            $table->boolean('nmi_enabled')->default(false)->after('dedicated_ip_id');
            $table->timestamp('nmi_enabled_at')->nullable()->after('nmi_enabled');

            // SMTP authentication for sending via NMI
            $table->string('nmi_smtp_username', 128)->nullable()->after('nmi_enabled_at');
            $table->text('nmi_smtp_password')->nullable()->after('nmi_smtp_username'); // Encrypted
            $table->timestamp('nmi_credentials_rotated_at')->nullable()->after('nmi_smtp_password');
        });

        // Add NMI provider to mailboxes
        Schema::table('mailboxes', function (Blueprint $table) {
            // Extend provider enum - we'll handle this via raw SQL
        });

        // Add 'nmi' to the provider enum
        \DB::statement("ALTER TABLE mailboxes MODIFY COLUMN provider ENUM('smtp', 'sendgrid', 'gmail', 'nmi') DEFAULT 'smtp'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domain_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dedicated_ip_id');
            $table->dropColumn([
                'nmi_enabled',
                'nmi_enabled_at',
                'nmi_smtp_username',
                'nmi_smtp_password',
                'nmi_credentials_rotated_at',
            ]);
        });

        \DB::statement("ALTER TABLE mailboxes MODIFY COLUMN provider ENUM('smtp', 'sendgrid', 'gmail') DEFAULT 'smtp'");
    }
};
