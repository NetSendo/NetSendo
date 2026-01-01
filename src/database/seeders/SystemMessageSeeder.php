<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds system pages (displayed after form actions) and system emails.
     *
     * Note: After migration 2025_12_21_210000, system_messages was renamed to system_pages
     * and a new system_emails table was created.
     */
    public function run(): void
    {
        // Seed system_pages (HTML pages shown to users)
        $this->seedSystemPages();

        // Seed system_emails (email templates)
        $this->seedSystemEmails();
    }

    private function seedSystemPages(): void
    {
        // Check if the table exists (after migration it's system_pages, before it's system_messages)
        $tableName = Schema::hasTable('system_pages') ? 'system_pages' : 'system_messages';

        $pages = [
            [
                'slug' => 'signup_success',
                'name' => 'Signup Confirmation',
                'title' => 'Subscription Successful',
                'content' => '<h1>Thank you for subscribing!</h1><p>Your email address has been successfully added to our mailing list.</p>'
            ],
            [
                'slug' => 'signup_error',
                'name' => 'Signup Error',
                'title' => 'Subscription Failed',
                'content' => '<h1>An error occurred</h1><p>Sorry, we could not add your email address. Please try again later.</p>'
            ],
            [
                'slug' => 'activation_success',
                'name' => 'Activation Confirmation',
                'title' => 'Activation Successful',
                'content' => '<h1>Account Activated!</h1><p>Your email address has been successfully verified.</p>'
            ],
            [
                'slug' => 'activation_error',
                'name' => 'Activation Error',
                'title' => 'Activation Failed',
                'content' => '<h1>Activation Error</h1><p>The activation link is invalid or has expired.</p>'
            ],
            [
                'slug' => 'unsubscribe_success',
                'name' => 'Unsubscribe Confirmation',
                'title' => 'Unsubscribed Successfully',
                'content' => '<h1>You have been unsubscribed</h1><p>Your email address has been removed from our mailing list.</p>'
            ],
            [
                'slug' => 'unsubscribe_error',
                'name' => 'Unsubscribe Error',
                'title' => 'Unsubscribe Failed',
                'content' => '<h1>An error occurred</h1><p>We could not remove your email from the list. Please contact the administrator.</p>'
            ],
            [
                'slug' => 'signup_exists',
                'name' => 'Email Already Exists',
                'title' => 'Email Already Subscribed',
                'content' => '<h1>Already Subscribed</h1><p>This email address is already in our database.</p>'
            ],
            [
                'slug' => 'unsubscribe_confirm',
                'name' => 'Unsubscribe Confirmation Request',
                'title' => 'Confirm Unsubscription',
                'content' => '<h1>Confirmation Required</h1><p>Are you sure you want to unsubscribe from this list?</p><p><a href="{unwrap_link}">Yes, unsubscribe me</a></p>'
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

            // Add access column if using system_pages
            if ($tableName === 'system_pages' && Schema::hasColumn('system_pages', 'access')) {
                $data['access'] = 'public';
            }

            DB::table($tableName)->updateOrInsert(
                ['slug' => $page['slug']],
                $data
            );
        }
    }

    private function seedSystemEmails(): void
    {
        // Only seed if system_emails table exists (after migration)
        if (!Schema::hasTable('system_emails')) {
            return;
        }

        $emails = [
            [
                'slug' => 'new_subscriber_notification',
                'name' => 'New Subscriber Notification',
                'subject' => 'New subscriber on list [[list-name]]',
                'content' => '<h2>New subscriber!</h2><p>A new subscriber joined <strong>[[list-name]]</strong>:</p><p><strong>Email:</strong> [[email]]</p><p><strong>Date:</strong> [[date]]</p>',
            ],
            [
                'slug' => 'activation_email',
                'name' => 'Activation Email',
                'subject' => 'Confirm your email address',
                'content' => '<h2>Confirm your subscription</h2><p>Click the link below to confirm your email address:</p><p><a href="[[activation-link]]">Confirm email address</a></p>',
            ],
            [
                'slug' => 'welcome_email',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to [[list-name]]!',
                'content' => '<h2>Welcome!</h2><p>Thank you for joining <strong>[[list-name]]</strong>.</p>',
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
    }
}
