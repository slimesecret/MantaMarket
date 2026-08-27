<?php
session_start();
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

$config   = require __DIR__ . '/config/oauth.php';
$provider = new League\OAuth2\Client\Provider\Facebook($config['facebook']);

$authUrl = $provider->getAuthorizationUrl([
    'scope' => ['email', 'public_profile']
]);

$_SESSION['oauth2state'] = $provider->getState();

header('Location: ' . $authUrl);
exit();