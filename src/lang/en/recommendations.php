<?php

return [
    // Quick Win recommendations
    'missing_preheader' => [
        'title' => 'Add preheaders to your emails',
        'description' => 'Emails without preheaders miss valuable inbox preview space. Adding compelling preheaders can increase open rates by 5-10%.',
        'action_steps' => [
            'Open the email editor for each draft/scheduled email',
            'Add a preheader that complements your subject line',
            'Keep it under 100 characters for best display',
            'Use personalization tokens for higher engagement',
        ],
    ],
    'long_subject' => [
        'title' => 'Shorten your subject lines',
        'description' => 'Long subject lines get truncated on mobile devices. Keeping them under 50 characters ensures your message is fully visible.',
        'action_steps' => [
            'Review subject lines over 50 characters',
            'Focus on the most compelling part of your message',
            'Use power words that trigger emotion',
            'Test with emoji (sparingly) for visual appeal',
        ],
    ],
    'no_personalization' => [
        'title' => 'Personalize your email content',
        'description' => 'Personalized emails achieve 26% higher open rates. Using subscriber names and relevant data creates stronger connections.',
        'action_steps' => [
            'Add [[first_name]] to subject lines and greetings',
            'Use [[company]] or [[city]] for B2B communications',
            'Create dynamic content blocks based on subscriber tags',
            'Set up fallback values for missing data',
        ],
    ],
    'spam_content' => [
        'title' => 'Reduce spam trigger words',
        'description' => 'Your content contains words that may trigger spam filters. Cleaning up the language improves deliverability.',
        'action_steps' => [
            'Avoid ALL CAPS and excessive exclamation marks',
            'Replace words like "FREE", "URGENT", "ACT NOW" with softer alternatives',
            'Balance promotional and value-driven content',
            'Use HTML email checkers before sending',
        ],
    ],
    'stale_list' => [
        'title' => 'Clean your subscriber lists',
        'description' => 'Lists with inactive subscribers hurt deliverability. Regular cleaning improves open rates and sender reputation.',
        'action_steps' => [
            'Identify subscribers with no opens in 90 days',
            'Run a re-engagement campaign before removing',
            'Remove hard bounces immediately',
            'Consider a sunset policy for long-inactive users',
        ],
    ],
    'poor_timing' => [
        'title' => 'Optimize your send times',
        'description' => 'Sending at optimal times significantly impacts open rates. Your best window is typically 9-11 AM or 2-4 PM local time.',
        'action_steps' => [
            'Schedule emails between 9-11 AM for business audiences',
            'Try 2-4 PM for consumer audiences',
            'Tuesday through Thursday typically perform best',
            'Avoid weekends unless you have data showing otherwise',
        ],
    ],
    'over_mailing' => [
        'title' => 'Reduce sending frequency',
        'description' => 'You are sending too frequently to some lists. This increases unsubscribes and spam complaints.',
        'action_steps' => [
            'Limit to 2-3 emails per week per list',
            'Create a preference center for frequency options',
            'Segment high-engagement users for more content',
            'Use automations instead of manual broadcasts where possible',
        ],
    ],
    'no_automation' => [
        'title' => 'Set up welcome automations',
        'description' => 'Automated emails generate 320% more revenue than non-automated. Start with a welcome sequence.',
        'action_steps' => [
            'Create a 3-5 email welcome sequence',
            'Set up automation triggered on new subscriber',
            'Include value content before promotional offers',
            'Track engagement to identify hot leads',
        ],
    ],
    'sms_missing' => [
        'title' => 'Launch SMS campaigns',
        'description' => 'You have phone numbers but are not using SMS. Multi-channel campaigns improve conversion by 12-15%.',
        'action_steps' => [
            'Create an SMS follow-up for key email campaigns',
            'Use SMS for time-sensitive offers',
            'Keep messages under 160 characters',
            'Include a clear call-to-action with link',
        ],
    ],

    // Strategic recommendations
    'declining_open_rate' => [
        'title' => 'Reverse declining open rates',
        'description' => 'Your open rates have dropped by :change% over the last 30 days. Focus on subject line optimization and list hygiene.',
        'action_steps' => [
            'A/B test subject lines on your next 5 campaigns',
            'Remove subscribers inactive for 90+ days',
            'Check your sender reputation on mail-tester.com',
            'Verify SPF/DKIM/DMARC records',
        ],
    ],
    'low_click_rate' => [
        'title' => 'Improve email click-through rates',
        'description' => 'Your click rate is below 2%, which is under industry average. Better CTAs and content structure can help.',
        'action_steps' => [
            'Use button-style CTAs instead of text links',
            'Place your main CTA above the fold',
            'Use action-oriented language ("Get Started" vs "Click Here")',
            'Limit to 1-2 primary CTAs per email',
        ],
    ],
    'low_segmentation' => [
        'title' => 'Implement subscriber segmentation',
        'description' => 'Only :percent% of your subscribers are tagged. Better segmentation leads to 14% higher click rates.',
        'action_steps' => [
            'Create interest-based tags from click behavior',
            'Set up tag automations for key actions',
            'Segment by engagement level (active/passive/cold)',
            'Use dynamic content blocks for different segments',
        ],
    ],
];
