<?php

return [
    'chat' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'timeout' => env('GEMINI_TIMEOUT', 35),
    ],
];
