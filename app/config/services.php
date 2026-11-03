<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'relay-dev-verify'),
        'freeform_window_hours' => (int) env('WHATSAPP_FREEFORM_WINDOW_HOURS', 24),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    // AI provider for summaries, drafts and scores: auto picks Groq, then Anthropic, then the simulated demo client.
    'ai' => ['driver' => env('AI_DRIVER', 'auto')],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

];
