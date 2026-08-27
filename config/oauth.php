

<?php
return [
    'google' => [
        'clientId'     => 'GOOGLE_CLIENT_ID',
        'clientSecret' => 'GOCSPX-xxxxxxxxxxxxx',  // ← lấy từ JSON hoặc trang Clients
        'redirectUri'  => 'http://localhost/MantaMarket/callback/google.php',
    ],
        'facebook' => [
        'clientId'     => 'GOOGLE_CLIENT_ID',
        'clientSecret' => 'FACEBOOK_CLIENT_SECRET',
        'redirectUri'  => 'http://localhost/MantaMarket/callback/facebook.php',
        'graphApiVersion' => 'v18.0',
    ],
];
