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
        // Expand the ENUM to include all trigger events
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE automation_rules MODIFY COLUMN trigger_event ENUM(
                'subscriber_signup',
                'subscriber_activated',
                'list_join',
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
                'subscription_anniversary',
                'subscriber_inactive',
                'purchase',
                'pixel_page_visited',
                'pixel_product_viewed',
                'pixel_add_to_cart',
                'pixel_checkout_started',
                'pixel_cart_abandoned',
                'pixel_return_visit',
                'crm_deal_stage_changed',
                'crm_deal_won',
                'crm_deal_lost',
                'crm_deal_idle',
                'crm_task_overdue',
                'crm_score_changed',
                'crm_contact_created',
                'crm_task_completed'
            )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversing as ENUM restrictions could cause data loss
    }
};
