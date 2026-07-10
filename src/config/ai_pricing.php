<?php

/*
|--------------------------------------------------------------------------
| AI Model Pricing (USD per 1M tokens: [input, output])
|--------------------------------------------------------------------------
| Used to estimate Brain token costs. Keys are matched as substrings
| against the model name (first match wins), so keep MORE SPECIFIC
| entries BEFORE more generic ones. Update prices here as they change —
| do not hardcode them in controllers/services.
*/

return [

    'models' => [
        // --- OpenAI (2026) ---
        'gpt-5.2' => [1.75, 14.00],
        'gpt-5-mini' => [0.25, 2.00],
        'gpt-5' => [1.25, 10.00],
        'o3-mini' => [1.10, 4.40],
        'o3' => [2.00, 8.00],
        'o1-pro' => [15.00, 60.00],
        'o1-mini' => [3.00, 12.00],
        'o1' => [15.00, 60.00],
        'gpt-4.5' => [75.00, 150.00],
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-4-turbo' => [10.00, 30.00],
        'gpt-4' => [30.00, 60.00],
        'gpt-3.5-turbo' => [0.50, 1.50],

        // --- Anthropic (2026) ---
        'claude-opus-4-8' => [15.00, 75.00],
        'claude-sonnet-5' => [3.00, 15.00],
        'claude-haiku-4-5' => [0.80, 4.00],
        'claude-fable-5' => [1.00, 5.00],
        'claude-opus-4' => [15.00, 75.00],
        'claude-sonnet-4' => [3.00, 15.00],
        'claude-4-5-opus' => [15.00, 75.00],
        'claude-4-5-sonnet' => [3.00, 15.00],
        'claude-4-opus' => [15.00, 75.00],
        'claude-4-sonnet' => [3.00, 15.00],
        'claude-4-haiku' => [0.80, 4.00],
        'claude-3-5-sonnet' => [3.00, 15.00],
        'claude-3-5-haiku' => [0.80, 4.00],
        'claude-3-opus' => [15.00, 75.00],
        'claude-3-sonnet' => [3.00, 15.00],
        'claude-3-haiku' => [0.25, 1.25],
        // generic fallbacks by tier
        'opus' => [15.00, 75.00],
        'sonnet' => [3.00, 15.00],
        'haiku' => [0.80, 4.00],

        // --- xAI ---
        'grok-4' => [3.00, 15.00],
        'grok-3' => [3.00, 15.00],
        'grok-2' => [2.00, 10.00],

        // --- Google ---
        'gemini-2.5-pro' => [1.25, 10.00],
        'gemini-2.5-flash-lite' => [0.10, 0.40],
        'gemini-2.5-flash' => [0.30, 2.50],
        'gemini-2.0-pro' => [1.25, 5.00],
        'gemini-2.0-flash' => [0.10, 0.40],
        'gemini-1.5-pro' => [1.25, 5.00],
        'gemini-1.5-flash' => [0.075, 0.30],

        // --- Local / open models (no API cost) ---
        'llama' => [0.0, 0.0],
        'qwen' => [0.0, 0.0],
        'mistral4' => [0.0, 0.0],
        'phi5' => [0.0, 0.0],
        'deepseek' => [0.0, 0.0],
        'kimi' => [0.0, 0.0],
    ],

    // Applied when no model entry matches
    'fallback' => [1.00, 4.00],
];
