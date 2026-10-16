<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => ['*'],
    // 'paths' => [
    //     'api/*',
    //     'sanctum/csrf-cookie',
    //     'login',
    //     'logout',
<<<<<<< HEAD
    //     'refresh',
=======
>>>>>>> ce3469f2e0c40ff8735b97698039fa891e41a033
    // ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
<<<<<<< HEAD
        // 'http://192.168.8.254:5173'
        'http://202.137.126.213:8089',  // ✅ ADD THIS - your public IP
        'http://192.168.100.52:5173',   // ✅ add your LAN frontend URL
        'http://192.168.30.11:5173',
        'http://192.168.30.11:8089',   // ✅ add your LAN frontend URL
        'http://192.168.8.254:5173',   // ✅ add your LAN frontend URL
        'http://localhost:5173',
        'http://127.0.0.1:5173'
=======
        'http://192.168.8.254:5173', 
        // 'http://192.168.100.52:5173', 
        // 'http://202.137.126.213:8089',  // ✅ ADD THIS - your public IP
        // 'http://192.168.100.52:5173',   // ✅ add your LAN frontend URL
        // 'http://192.168.30.11:5173',
        // 'http://192.168.30.11:8089',   // ✅ add your LAN frontend URL
        // 'http://192.168.8.254:5173',   // ✅ add your LAN frontend URL
        // 'http://localhost:5173',
        // 'http://127.0.0.1:5173'
>>>>>>> ce3469f2e0c40ff8735b97698039fa891e41a033
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
