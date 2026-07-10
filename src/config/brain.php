<?php

/*
|--------------------------------------------------------------------------
| NetSendo Brain — tunable thresholds (Brain 2.0 Phase 5)
|--------------------------------------------------------------------------
| Previously hardcoded across services. Per-user overrides live in
| ai_brain_settings.preferences under the same keys.
*/

return [

    // CRM contacts with score >= this are "hot leads"
    'hot_lead_score' => 50,

    // Industry benchmark fallbacks (used until the user has own history)
    'benchmarks' => [
        'avg_open_rate' => 21.0,
        'avg_click_rate' => 2.6,
        'avg_unsubscribe_rate' => 0.26,
        'avg_bounce_rate' => 0.7,
    ],

    // Campaign performance review window (hours after send)
    'review_min_hours' => 24,
    'review_max_hours' => 168,

    // Learning engine
    'min_snapshots' => 3,
    'analysis_window_days' => 60,

    // Attention-budget allocator (Phase 5)
    'allocator' => [
        'lookback_days' => 90,
        'exploration_prior_rpm' => 50.0, // optimistic prior for untested arms
        'min_arm_samples' => 2,
    ],
];
