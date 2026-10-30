<?php

return [
    'enabled' => (bool) env('OMNI_ENABLED', false),
    'server_url' => env('OMNI_SERVER_URL'),
    'client_id' => env('OMNI_CLIENT_ID'),
    'client_secret' => env('OMNI_CLIENT_SECRET'),
    'redirect_uri' => env('OMNI_REDIRECT_URI'),
    'transaction_ttl' => 300,
];
