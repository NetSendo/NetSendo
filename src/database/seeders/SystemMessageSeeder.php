<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $messages = [
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

        foreach ($messages as $msg) {
            DB::table('system_messages')->updateOrInsert(
                ['slug' => $msg['slug']],
                [
                    'name' => $msg['name'],
                    'title' => $msg['title'],
                    'content' => $msg['content'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
