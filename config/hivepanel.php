<?php

return [
    'worker_url' => env('HIVE_WORKER_URL', 'http://localhost:8080'),
    'worker_token' => env('HIVE_WORKER_TOKEN', ''),
    'registry_url' => env('HIVE_REGISTRY_URL', 'https://registry.hivepanel.dev'),
];