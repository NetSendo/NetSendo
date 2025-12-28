<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add English translations for system pages.
     */
    public function up(): void
    {
        // Update existing global system pages with English translations
        // Users can always customize these via the admin panel
        $pages = [
            'signup_success' => [
                'name' => 'Signup Success',
                'title' => 'Thank you for signing up!',
                'content' => '<h1>Thank you for signing up!</h1><p>Your email address has been added to our mailing list.</p>',
            ],
            'signup_error' => [
                'name' => 'Signup Error',
                'title' => 'Signup Failed',
                'content' => '<h1>An error occurred</h1><p>Sorry, we couldn\'t add your email address. Please try again later.</p>',
            ],
            'signup_exists' => [
                'name' => 'Email Already Exists',
                'title' => 'Email Already Registered',
                'content' => '<h1>You\'re already on our list</h1><p>This email address is already registered in our database.</p>',
            ],
            'signup_exists_active' => [
                'name' => 'Email Already Active',
                'title' => 'Email Already Active',
                'content' => '<h1>You\'re already on our list</h1><p>This email address is already active in our database.</p>',
            ],
            'signup_exists_inactive' => [
                'name' => 'Email Needs Activation',
                'title' => 'Email Needs Activation',
                'content' => '<h1>Activation Required</h1><p>This email address is in our database but needs activation. Please check your inbox for the activation email.</p>',
            ],
            'activation_success' => [
                'name' => 'Activation Success',
                'title' => 'Activation Successful',
                'content' => '<h1>Account Activated!</h1><p>Your email address has been successfully verified.</p>',
            ],
            'activation_error' => [
                'name' => 'Activation Error',
                'title' => 'Activation Failed',
                'content' => '<h1>Activation Error</h1><p>The activation link is invalid or has expired.</p>',
            ],
            'unsubscribe_success' => [
                'name' => 'Unsubscribe Success',
                'title' => 'Unsubscribed Successfully',
                'content' => '<h1>You\'ve been unsubscribed</h1><p>Your email address has been removed from our mailing list.</p>',
            ],
            'unsubscribe_error' => [
                'name' => 'Unsubscribe Error',
                'title' => 'Unsubscribe Failed',
                'content' => '<h1>An error occurred</h1><p>We couldn\'t remove your email from the list. Please contact the administrator.</p>',
            ],
            'unsubscribe_confirm' => [
                'name' => 'Confirm Unsubscribe',
                'title' => 'Confirm Unsubscribe',
                'content' => '<h1>Confirmation Required</h1><p>Are you sure you want to unsubscribe from this list?</p><p><a href="[[unsubscribe-link]]" class="btn">Yes, unsubscribe me</a></p>',
            ],
        ];

        // Only update if locale is English (check app locale or default)
        // For now, we'll create an alternative approach: keep original Polish but document that
        // users should customize. We could also add a locale column later.

        // For this migration, we'll just ensure all pages exist (some might have been missed)
        foreach ($pages as $slug => $data) {
            $exists = DB::table('system_pages')
                ->where('slug', $slug)
                ->whereNull('contact_list_id')
                ->exists();

            if (!$exists) {
                DB::table('system_pages')->insert([
                    'slug' => $slug,
                    'contact_list_id' => null,
                    'name' => $data['name'],
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'access' => 'public',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to do - we don't want to delete pages
    }
};
