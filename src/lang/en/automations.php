<?php

/**
 * English translations - Automations Module
 */

return [
    'title' => 'Automations',
    'create' => 'New Automation',
    'edit' => 'Edit Automation',
    'delete' => 'Delete Automation',
    'logs' => 'Execution Logs',
    
    'no_rules' => 'No automations',
    'no_rules_hint' => 'Create your first automation to automatically respond to events.',
    'create_first' => 'Create first automation',
    
    'basic_info' => 'Basic Information',
    'name_placeholder' => 'E.g. Welcome new subscribers',
    'description_placeholder' => 'Description (optional)',
    
    'when' => 'WHEN',
    'if' => 'IF',
    'then' => 'THEN',
    
    'trigger' => 'Trigger',
    'trigger_event' => 'Trigger Event',
    'actions_count' => 'Actions',
    'executions' => 'Executions',
    
    'filter_by_list' => 'Filter by list',
    'filter_by_message' => 'Filter by message',
    'filter_by_form' => 'Filter by form',
    'filter_by_tag' => 'Filter by tag',
    
    'add_condition' => 'Add condition',
    'no_conditions_hint' => 'No conditions - automation will trigger for every event occurrence.',
    'all_conditions' => 'All conditions must be met',
    'any_condition' => 'Any condition must be met',
    'value' => 'Value',
    
    'add_action' => 'Add action',
    'no_actions_hint' => 'Add at least one action to execute.',
    'select_tag' => 'Select tag',
    'select_list' => 'Select list',
    'select_message' => 'Select message',
    'select_funnel' => 'Select funnel',
    'select_field' => 'Select field',
    'webhook_url' => 'Webhook URL',
    'admin_email' => 'Admin email',
    'email_subject' => 'Email subject',
    'notification_message' => 'Notification message',
    'new_value' => 'New value',
    
    'rate_limiting' => 'Rate Limiting',
    'limit_per_subscriber' => 'Limit executions per subscriber',
    'max' => 'Maximum',
    'times' => 'times',
    'per_hour' => 'per hour',
    'per_day' => 'per day',
    'per_week' => 'per week',
    'per_month' => 'per month',
    'ever' => 'ever',
    
    'activate_immediately' => 'Activate immediately',
    
    'confirm_duplicate' => 'Do you want to duplicate this automation?',
    'confirm_delete' => 'Are you sure you want to delete this automation? This action cannot be undone.',
    
    'triggers' => [
        'subscriber_signup' => 'Subscriber signup',
        'subscriber_activated' => 'Subscriber activated',
        'email_opened' => 'Email opened',
        'email_clicked' => 'Link clicked',
        'subscriber_unsubscribed' => 'Subscriber unsubscribed',
        'email_bounced' => 'Email bounced',
        'form_submitted' => 'Form submitted',
        'tag_added' => 'Tag added',
        'tag_removed' => 'Tag removed',
        'field_updated' => 'Field updated',
    ],
    
    'actions' => [
        'send_email' => 'Send email',
        'add_tag' => 'Add tag',
        'remove_tag' => 'Remove tag',
        'move_to_list' => 'Move to list',
        'copy_to_list' => 'Copy to list',
        'unsubscribe' => 'Unsubscribe',
        'call_webhook' => 'Call webhook',
        'start_funnel' => 'Start funnel',
        'update_field' => 'Update field',
        'notify_admin' => 'Notify admin',
    ],
    
    'log_status' => [
        'success' => 'Success',
        'partial' => 'Partial success',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
    ],
];
