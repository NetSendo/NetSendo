<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds subscription_welcome system email for new subscribers without double opt-in.
     */
    public function up(): void
    {
        // Add subscription_welcome email template
        DB::table('system_emails')->updateOrInsert(
            ['slug' => 'subscription_welcome', 'contact_list_id' => null],
            [
                'slug' => 'subscription_welcome',
                'contact_list_id' => null,
                'name' => 'Subscription Welcome',
                'subject' => 'Welcome to [[list-name]]!',
                'content' => '<h2>Welcome!</h2><p>Thank you for subscribing to <strong>[[list-name]]</strong>.</p><p>We\'re excited to have you on board!</p>',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_emails')
            ->where('slug', 'subscription_welcome')
            ->whereNull('contact_list_id')
            ->delete();
    }
};
