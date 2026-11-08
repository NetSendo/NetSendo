<?php

return [
    'reminders' => [
        'email_subject' => 'Reminder: :title',
        'title' => '⏰ Task Reminder',
        'subtitle' => 'You have a scheduled task to complete',
        'type' => 'Type',
        'due_date' => 'Due Date',
        'priority' => 'Priority',
        'contact' => 'Contact',
        'view_in_crm' => 'View in CRM',
        'footer_auto' => 'This message was sent automatically by :appName.',
        'footer_settings' => 'You can change notification settings in your profile.',
        'deal' => 'Deal: :name',
        'types' => [
            'call' => '📞 Call',
            'email' => '✉️ Email',
            'meeting' => '📅 Meeting',
            'follow_up' => '🔄 Follow-up',
            'other' => '📋 Other',
        ],
        'priorities' => [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ]
    ],
    'calendar' => [
        'sync_label' => 'Sync to Google Calendar',
        'select_calendar' => 'Select Calendar',
        'google_calendar' => 'Google Calendar',
        'sync_all' => 'Sync All Tasks',
        'syncing' => 'Syncing...',
        'bulk_sync_description' => 'Sync all pending CRM tasks to Google Calendar with one click.',
        'sync_success' => 'Synced :count tasks',
        'synced' => 'Synced',
    ],
    'recurrence' => [
        'is_recurring' => 'Recurring Task',
        'helper_text' => 'Repeats automatically',
        'frequency' => [
            'label' => 'Repeat every',
            'daily' => '{1} day|{n} days',
            'weekly' => '{1} week|{n} weeks',
            'monthly' => '{1} month|{n} months',
            'yearly' => '{1} year|{n} years',
        ],
        'days_of_week' => 'Days of Week',
        'days' => [
            'mon' => 'Mon',
            'tue' => 'Tue',
            'wed' => 'Wed',
            'thu' => 'Thu',
            'fri' => 'Fri',
            'sat' => 'Sat',
            'sun' => 'Sun',
        ],
        'end_condition' => [
            'label' => 'Ends',
            'never' => 'Never',
            'date' => 'On Date:',
            'count' => 'After',
            'occurrences' => '{1} occurrence|{n} occurrences',
        ],
    ],
    'conflicts' => [
        'title' => 'Sync Conflict Detected',
        'description' => 'This task has been modified both locally and in Google Calendar',
        'detected_at' => 'Conflict detected: :date',
        'local_version' => 'Local Version (NetSendo)',
        'remote_version' => 'Remote Version (Google Calendar)',
        'use_local' => 'Use Local Version',
        'use_remote' => 'Use Remote Version',
        'cancel' => 'Cancel',
        'no_conflict' => 'Task has no conflict to resolve.',
        'resolved_local' => 'Conflict resolved - used local version.',
        'resolved_remote' => 'Conflict resolved - used remote version.',
    ],
    'sequences' => [
        'title' => 'Sequences',
        'banner' => [
            'title' => 'Automated Follow-up Sequences',
            'description' => 'Create task sequences that automatically remind you of the next steps in contacting a client. Set up "call back in 3 days", "follow-up after email", or entire nurturing campaigns.',
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ],
        'triggers' => [
            'manual' => 'Manual',
            'on_contact_created' => 'On Contact Created',
            'on_deal_created' => 'On Deal Created',
            'on_task_completed' => 'On Task Completed',
            'on_deal_stage_changed' => 'On Deal Stage Changed',
        ],
        'actions' => [
            'edit' => 'Edit',
            'report' => 'Report',
            'duplicate' => 'Duplicate',
            'delete' => 'Delete',
            'delete_confirm' => 'Are you sure you want to delete this sequence? All steps will be deleted.',
        ],
        'empty' => [
            'title' => 'No sequences',
            'description' => 'Create your first follow-up sequence to automate contact with clients.',
            'button' => 'Create First Sequence',
        ],
        'steps_count' => ':count steps',
        'active_enrollments' => ':count active',
    ],
    'task' => [
        'title' => [
            'new' => 'New Task',
            'edit' => 'Edit Task',
        ],
        'fields' => [
            'title' => 'Task Title *',
            'title_placeholder' => 'e.g. Call client about offer',
            'type' => 'Task Type',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'contact' => 'Assign to Contact',
            'contact_placeholder' => '-- No Contact --',
            'owner' => 'Assign to Owner',
            'owner_auto' => '-- Automatically (Me) --',
            'description' => 'Description (optional)',
            'description_placeholder' => 'Additional details about the task...',
        ],
        'calendar' => [
            'sync' => 'Sync to Google Calendar',
            'select_calendar' => 'Select Calendar',
            'default' => 'Default',
            'primary' => 'Primary',
            'synced' => 'Synced with Calendar',
        ],
        'meet' => [
            'include_meet' => 'Add Google Meet link',
            'include_meet_desc' => 'Automatically create video conference link',
            'attendees' => 'Meeting Attendees',
            'add_email' => 'Add attendee email and press Enter',
            'add_guest' => 'Add',
            'attendees_hint' => 'Press Enter to add email. Contact email is added automatically.',
            'meeting_ready' => 'Meeting link ready',
            'join' => 'Join Meeting',
            'not_connected' => 'Connect your Google account in settings to activate',
            'status' => [
                'accepted' => 'Accepted',
                'declined' => 'Declined',
                'tentative' => 'Maybe',
                'needsAction' => 'Awaiting response',
            ],
        ],
        'zoom' => [
            'include_zoom' => 'Add Zoom Meeting link',
            'include_zoom_desc' => 'Automatically create Zoom video conference',
            'meeting_ready' => 'Zoom meeting ready',
            'join' => 'Join Zoom',
            'attendees' => 'Invited Guests',
            'add_email' => 'Enter guest email...',
            'add_guest' => 'Add',
            'attendees_hint' => 'Guests will receive an invitation with meeting link',
            'not_connected' => 'Connect your Zoom account in settings to activate',
        ],
        'actions' => [
            'cancel' => 'Cancel',
            'save' => 'Save Changes',
            'create' => 'Create Task',
        ],
        'empty' => [
            'title' => 'No tasks',
            'description' => 'You don\'t have any tasks in this category yet.',
            'button' => 'Add first task',
        ],
    ],
    'tasks' => [
        'list_view' => 'List',
        'calendar_view' => 'Calendar',
        'month_view' => 'Month',
        'week_view' => 'Week',
        'today' => 'Today',
        'prev_month' => 'Previous month',
        'next_month' => 'Next month',
        'prev_week' => 'Previous week',
        'next_week' => 'Next week',
        'google_event' => 'Google Event',
        'untitled_event' => 'Untitled',
        'more_events' => '+:count more',
        'filter_overdue' => 'Overdue',
        'filter_today' => 'Today',
        'filter_upcoming' => 'Upcoming',
        'filter_completed' => 'Completed',
    ],
    'contacts' => [
        'search_or_email' => 'Search or enter email',
        'search_placeholder' => 'Type email or name...',
        'search_hint' => 'Type at least 2 characters to search for an existing subscriber or enter a new email',
        'existing_subscriber' => 'Existing subscriber',
        'found_subscribers' => 'Found subscribers',
        'title' => [
            'edit' => 'Edit contact',
        ],
        'edit_success' => 'Contact has been updated',
        'actions' => [
            'edit' => 'Edit',
        ],
    ],
    'defaults' => [
        'badge' => 'Default',
        'badge_modified' => 'Custom',
        'restore_button' => 'Restore Defaults',
        'restore_modal' => [
            'title' => 'Restore Default Sequences',
            'warning' => 'This will delete all current sequences and create new default ones. This action cannot be undone.',
            'confirm_checkbox' => 'I understand that all my current sequences will be deleted',
            'cancel' => 'Cancel',
            'confirm' => 'Restore Defaults',
        ],
        'restored_success' => 'Default sequences have been restored.',
        'no_sequences' => 'You don\'t have any sequences yet.',
    ],
    'default_sequences' => [
        'new_lead_nurture' => [
            'name' => 'New Lead Nurturing',
            'description' => 'Automatic welcome sequence for new contacts. Builds relationships and leads to the first sale.',
            'steps' => [
                0 => [
                    'title' => 'Welcome Call',
                    'description' => 'Call the new contact, introduce yourself and ask about their needs. Establish next steps.',
                ],
                1 => [
                    'title' => 'Follow-up Email with Offer',
                    'description' => 'Send an email summarizing the call with a value proposition and link to materials.',
                ],
                2 => [
                    'title' => 'Check Interest',
                    'description' => 'Call and ask about reading the materials. Answer questions and schedule a presentation.',
                ],
                3 => [
                    'title' => 'Final Offer',
                    'description' => 'Send the final offer with an expiration date. This is the last chance to close the sale.',
                ],
            ],
        ],
        'contact_recovery' => [
            'name' => 'Contact Recovery',
            'description' => 'Reactivation of inactive contacts. Ideal for clients who stopped responding.',
            'steps' => [
                0 => [
                    'title' => 'First Contact Attempt',
                    'description' => 'Call and ask if everything is okay. Remind about the offer and value of cooperation.',
                ],
                1 => [
                    'title' => 'Reminder Email',
                    'description' => 'Send an email with a new value proposition or special reactivation offer.',
                ],
                2 => [
                    'title' => 'Final Contact Attempt',
                    'description' => 'Final attempt to establish contact. Suggest a meeting or call at a convenient time.',
                ],
            ],
        ],
        'after_meeting' => [
            'name' => 'After Meeting Follow-up',
            'description' => 'Post-meeting sequence. Maintains momentum and leads to a decision.',
            'steps' => [
                0 => [
                    'title' => 'Meeting Summary',
                    'description' => 'Send an email with meeting summary, agreed points and next steps.',
                ],
                1 => [
                    'title' => 'Check-in Call',
                    'description' => 'Call to check if materials arrived. Answer any questions.',
                ],
                2 => [
                    'title' => 'Ask for Decision',
                    'description' => 'Call and ask about the decision. If they need more time - set a specific date.',
                ],
            ],
        ],
        'sales_closing' => [
            'name' => 'Sales Closing',
            'description' => 'Sales closing sequence. For contacts ready to make a decision.',
            'steps' => [
                0 => [
                    'title' => 'Send Proposal',
                    'description' => 'Prepare and send a formal proposal with expiration date and terms.',
                ],
                1 => [
                    'title' => 'Confirmation Call',
                    'description' => 'Call to confirm receipt of the proposal. Answer questions about terms.',
                ],
                2 => [
                    'title' => 'Decision Follow-up',
                    'description' => 'Call and gently ask about the decision status. Offer to clarify any doubts.',
                ],
                3 => [
                    'title' => 'Last Chance',
                    'description' => 'Final reminder about the expiring offer. Suggest a bonus for quick decision.',
                ],
            ],
        ],
    ],

    // Default lead scoring rules (LeadScoringRule::getDefaultRules())
    'scoring' => [
        'defaults' => [
            'email_opened' => [
                'name' => 'Email Opened',
                'description' => 'Points for opening an email',
            ],
            'email_clicked' => [
                'name' => 'Link Clicked',
                'description' => 'Points for clicking a link in an email',
            ],
            'email_replied' => [
                'name' => 'Email Replied',
                'description' => 'Bonus for replying to a message',
            ],
            'contact_created' => [
                'name' => 'CRM Contact Created',
                'description' => 'Starting points when a CRM contact is created',
            ],
            'form_submitted' => [
                'name' => 'Form Submitted',
                'description' => 'Points for submitting any form',
            ],
            'page_visited' => [
                'name' => 'Page Visited',
                'description' => 'Points for visiting a page',
            ],
            'page_visited_pricing' => [
                'name' => 'Pricing Page Visit',
                'description' => 'Bonus for visiting the pricing page',
            ],
            'product_viewed' => [
                'name' => 'Product Viewed',
                'description' => 'Points for viewing a product page',
            ],
            'add_to_cart' => [
                'name' => 'Added to Cart',
                'description' => 'Points for adding a product to the cart',
            ],
            'checkout_started' => [
                'name' => 'Checkout Started',
                'description' => 'Bonus for starting the checkout process',
            ],
            'tag_added_hot' => [
                'name' => 'Tag "hot"',
                'description' => 'Bonus for receiving the \'hot\' tag',
            ],
            'tag_added_vip' => [
                'name' => 'Tag "vip"',
                'description' => 'Bonus for receiving the \'vip\' tag',
            ],
            'decay_7_days' => [
                'name' => 'Inactivity 7 Days',
                'description' => 'Automatic points decay after 7 days of inactivity',
            ],
            'decay_30_days' => [
                'name' => 'Inactivity 30 Days',
                'description' => 'Large points decay after 30 days of inactivity',
            ],
        ],
    ],

    'cardintel' => [
        'settings' => [
            'saved' => 'Saved',
        ],
    ],
    'companies' => [
        'lookup' => [
            'invalid_nip' => 'Invalid NIP format',
            'invalid_regon' => 'Invalid REGON format',
            'missing_identifier' => 'Enter NIP or REGON',
            'not_found' => 'Company with this NIP/REGON not found',
        ],
    ],
    'activities' => [
        'log' => [
            'email_sent' => 'Email sent: :subject',
            'task_completed' => 'Task completed: :title',
        ],
    ],
    'notifications' => [
        'contact_replied' => [
            'subject' => 'New reply from :name',
            'greeting' => 'Hello!',
            'intro' => ':name replied to your message (channel: :channel).',
            'action' => 'View contact',
            'outro' => 'Reply while the conversation is fresh.',
        ],
        'deal_won' => [
            'subject' => 'Deal won: :name',
            'intro' => 'Congratulations! The deal ":name" worth :value was won.',
        ],
        'deal_lost' => [
            'subject' => 'Deal lost: :name',
            'intro' => 'The deal ":name" was marked as lost.',
        ],
        'deal_stage_changed' => [
            'subject' => 'Deal stage changed: :name',
            'intro' => 'The deal ":name" moved to another stage.',
            'greeting' => 'Hello!',
            'action' => 'View deal',
        ],
        'task_overdue' => [
            'subject' => 'Overdue task: :title',
            'greeting' => 'Hello!',
            'intro' => 'The task ":title" is past its due date.',
            'due_date' => 'Due: :date',
            'action' => 'View task',
            'outro' => 'Complete it or set a new due date.',
        ],
    ],
];
