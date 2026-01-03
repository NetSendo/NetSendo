<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds system emails and pages for preference management and unsubscribe confirmation flow.
     */
    public function up(): void
    {
        // Add new system emails
        $emails = [
            // Email sent when user requests to change preferences
            [
                'slug' => 'preference_confirm',
                'name' => 'Preference Change Confirmation',
                'subject' => 'Confirm Your Subscription Preferences',
                'content' => '<h2>Confirm Your Changes</h2><p>We received a request to update your subscription preferences.</p><p>Click the link below to confirm your changes:</p><p><a href="[[confirm-link]]">Confirm changes</a></p><p>If you did not request this change, you can ignore this email.</p>',
            ],
        ];

        foreach ($emails as $email) {
            DB::table('system_emails')->updateOrInsert(
                ['slug' => $email['slug'], 'contact_list_id' => null],
                [
                    'name' => $email['name'],
                    'subject' => $email['subject'],
                    'content' => $email['content'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Add new system pages
        if (Schema::hasTable('system_pages')) {
            $pages = [
                // Page shown after sending unsubscribe confirmation email
                [
                    'slug' => 'unsubscribe_confirm_sent',
                    'name' => 'Unsubscribe Confirmation Sent',
                    'title' => 'Check Your Email',
                    'content' => '<h1>Check Your Email</h1><p>We have sent you a confirmation email. Please click the link in the email to unsubscribe from this list.</p><p>If you don\'t see the email, please check your spam folder.</p>',
                ],
                // Page shown when preference changes are submitted (before confirmation)
                [
                    'slug' => 'preference_confirm_sent',
                    'name' => 'Preference Confirmation Sent',
                    'title' => 'Check Your Email',
                    'content' => '<h1>Check Your Email</h1><p>We have sent you a confirmation email. Please click the link in the email to apply your subscription changes.</p>',
                ],
                // Page shown after preference changes are confirmed
                [
                    'slug' => 'preference_update_success',
                    'name' => 'Preferences Updated',
                    'title' => 'Preferences Updated',
                    'content' => '<h1>Preferences Updated</h1><p>Your subscription preferences have been successfully updated.</p>',
                ],
            ];

            foreach ($pages as $page) {
                $data = [
                    'name' => $page['name'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Add access column if it exists
                if (Schema::hasColumn('system_pages', 'access')) {
                    $data['access'] = 'public';
                }

                DB::table('system_pages')->updateOrInsert(
                    ['slug' => $page['slug'], 'contact_list_id' => null],
                    $data
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added system emails
        DB::table('system_emails')
            ->whereIn('slug', ['preference_confirm'])
            ->whereNull('contact_list_id')
            ->delete();

        // Remove added system pages
        if (Schema::hasTable('system_pages')) {
            DB::table('system_pages')
                ->whereIn('slug', ['unsubscribe_confirm_sent', 'preference_confirm_sent', 'preference_update_success'])
                ->whereNull('contact_list_id')
                ->delete();
        }
    }
};
