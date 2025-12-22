<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the automation_rules table to support new trigger events
        // We need to alter the ENUM to include new trigger types
        
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE automation_rules MODIFY COLUMN trigger_event ENUM(
                'subscriber_signup',
                'subscriber_activated',
                'email_opened',
                'email_clicked',
                'subscriber_unsubscribed',
                'email_bounced',
                'form_submitted',
                'tag_added',
                'tag_removed',
                'field_updated',
                'page_visited',
                'specific_link_clicked',
                'date_reached',
                'read_time_threshold',
                'subscriber_birthday',
                'subscription_anniversary'
            )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE automation_rules MODIFY COLUMN trigger_event ENUM(
                'subscriber_signup',
                'subscriber_activated',
                'email_opened',
                'email_clicked',
                'subscriber_unsubscribed',
                'email_bounced',
                'form_submitted',
                'tag_added',
                'tag_removed',
                'field_updated'
            )");
        }
    }
};
