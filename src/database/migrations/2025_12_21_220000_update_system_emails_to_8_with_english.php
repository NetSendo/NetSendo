<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates system emails to have 8 entries with English default content.
     * Updates system pages to have English default content.
     */
    public function up(): void
    {
        // Clear existing system emails and re-seed with all 8 + English content
        DB::table('system_emails')->whereNull('contact_list_id')->delete();

        // 8 System Emails (as shown in old NetSendo screenshot)
        $emails = [
            // 1. Activation confirmation (sent after user activates/confirms their email)
            [
                'slug' => 'activation_confirmation',
                'name' => 'Activation Confirmed',
                'subject' => 'Subscription Confirmed - [[list-name]]',
                'content' => '<h2>Subscription Confirmed!</h2><p>Thank you for confirming your email address.</p><p>You are now subscribed to <strong>[[list-name]]</strong>.</p>',
            ],
            // 2. Data edit access request (sent when user wants to edit their profile)
            [
                'slug' => 'data_edit_access',
                'name' => 'Data Edit Access',
                'subject' => 'Confirm Access to Edit Your Data',
                'content' => '<h2>Edit Your Subscription Data</h2><p>Click the link below to access your subscription profile and update your information:</p><p><a href="[[edit-link]]">Edit my data</a></p><p>If you did not request this, please ignore this email.</p>',
            ],
            // 3. New subscriber notification (sent to admin when new subscriber signs up)
            [
                'slug' => 'new_subscriber_notification',
                'name' => 'New Subscriber Notification',
                'subject' => 'New Subscriber on [[list-name]]',
                'content' => '<h2>New Subscriber!</h2><p>A new subscriber has joined <strong>[[list-name]]</strong>:</p><p><strong>Email:</strong> [[email]]</p><p><strong>Date:</strong> [[date]]</p>',
            ],
            // 4. Already active re-subscribe (sent when active user tries to subscribe again)
            [
                'slug' => 'already_active_resubscribe',
                'name' => 'Already Active Subscriber',
                'subject' => 'You are already subscribed to [[list-name]]',
                'content' => '<h2>Already Subscribed</h2><p>Good news! You are already an active subscriber on <strong>[[list-name]]</strong>.</p><p>There is no action required.</p>',
            ],
            // 5. Inactive re-subscribe (sent when inactive user re-subscribes)
            [
                'slug' => 'inactive_resubscribe',
                'name' => 'Re-subscribed Notification',
                'subject' => 'Welcome back to [[list-name]]!',
                'content' => '<h2>Welcome Back!</h2><p>You have successfully re-subscribed to <strong>[[list-name]]</strong>.</p><p>We\'re happy to have you back!</p>',
            ],
            // 6. Unsubscribe confirmation request (sent when user requests to unsubscribe)
            [
                'slug' => 'unsubscribe_request',
                'name' => 'Unsubscribe Confirmation Request',
                'subject' => 'Confirm Your Unsubscribe Request',
                'content' => '<h2>Confirm Unsubscribe</h2><p>We received a request to unsubscribe from <strong>[[list-name]]</strong>.</p><p>Click the link below to confirm:</p><p><a href="[[unsubscribe-link]]">Yes, unsubscribe me</a></p><p>If you did not request this, you can ignore this email.</p>',
            ],
            // 7. Unsubscribed confirmation (sent after successful unsubscribe)
            [
                'slug' => 'unsubscribed_confirmation',
                'name' => 'Unsubscribe Confirmed',
                'subject' => 'You have been unsubscribed from [[list-name]]',
                'content' => '<h2>Unsubscribed</h2><p>You have been successfully unsubscribed from <strong>[[list-name]]</strong>.</p><p>We\'re sorry to see you go. If this was a mistake, you can always re-subscribe.</p>',
            ],
            // 8. Signup confirmation (double opt-in - sent to confirm email address)
            [
                'slug' => 'signup_confirmation',
                'name' => 'Signup Confirmation',
                'subject' => 'Please Confirm Your Subscription',
                'content' => '<h2>Confirm Your Subscription</h2><p>Thank you for subscribing to <strong>[[list-name]]</strong>!</p><p>Please click the link below to confirm your email address:</p><p><a href="[[activation-link]]">Confirm my subscription</a></p><p>If you did not sign up, you can ignore this email.</p>',
            ],
        ];

        foreach ($emails as $email) {
            DB::table('system_emails')->insert([
                'slug' => $email['slug'],
                'contact_list_id' => null,
                'name' => $email['name'],
                'subject' => $email['subject'],
                'content' => $email['content'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update system pages with English content
        $pages = [
            [
                'slug' => 'signup_success',
                'name' => 'Signup Success',
                'title' => 'Thank you for signing up!',
                'content' => '<h1>Thank you for signing up!</h1><p>Your email address has been added to our mailing list.</p>',
            ],
            [
                'slug' => 'signup_error',
                'name' => 'Signup Error',
                'title' => 'Signup Failed',
                'content' => '<h1>An error occurred</h1><p>Sorry, we could not add your email address. Please try again later.</p>',
            ],
            [
                'slug' => 'signup_exists',
                'name' => 'Email Already Exists',
                'title' => 'Email Already Registered',
                'content' => '<h1>Already Subscribed</h1><p>This email address is already registered in our database.</p>',
            ],
            [
                'slug' => 'signup_exists_active',
                'name' => 'Email Already Active',
                'title' => 'Email Already Active',
                'content' => '<h1>Already Subscribed</h1><p>This email address is already active in our database.</p>',
            ],
            [
                'slug' => 'signup_exists_inactive',
                'name' => 'Email Inactive',
                'title' => 'Email Needs Activation',
                'content' => '<h1>Activation Required</h1><p>This email address is in our database but requires activation. Please check your inbox for the confirmation email.</p>',
            ],
            [
                'slug' => 'activation_success',
                'name' => 'Activation Success',
                'title' => 'Activation Successful',
                'content' => '<h1>Account Activated!</h1><p>Your email address has been successfully verified.</p>',
            ],
            [
                'slug' => 'activation_error',
                'name' => 'Activation Error',
                'title' => 'Activation Failed',
                'content' => '<h1>Activation Error</h1><p>The activation link is invalid or has expired.</p>',
            ],
            [
                'slug' => 'unsubscribe_success',
                'name' => 'Unsubscribe Success',
                'title' => 'Unsubscribed Successfully',
                'content' => '<h1>Unsubscribed</h1><p>Your email address has been removed from our mailing list.</p>',
            ],
            [
                'slug' => 'unsubscribe_error',
                'name' => 'Unsubscribe Error',
                'title' => 'Unsubscribe Failed',
                'content' => '<h1>An error occurred</h1><p>We could not remove your email address from the list. Please contact the administrator.</p>',
            ],
            [
                'slug' => 'unsubscribe_confirm',
                'name' => 'Confirm Unsubscribe',
                'title' => 'Confirm Unsubscribe',
                'content' => '<h1>Confirmation Required</h1><p>Are you sure you want to unsubscribe from this list?</p><p><a href="[[unsubscribe-link]]">Yes, unsubscribe me</a></p>',
            ],
        ];

        foreach ($pages as $page) {
            DB::table('system_pages')
                ->where('slug', $page['slug'])
                ->whereNull('contact_list_id')
                ->update([
                    'name' => $page['name'],
                    'title' => $page['title'],
                    'content' => $page['content'],
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only updates data, reversal not supported
    }
};
