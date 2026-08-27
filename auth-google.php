<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

$config   = require __DIR__ . '/config/oauth.php';
$provider = new League\OAuth2\Client\Provider\Google($config['google']);

$authUrl = $provider->getAuthorizationUrl([
    'scope' => ['openid', 'email', 'profile']
]);

$_SESSION['oauth2state'] = $provider->getState();

header('Location: ' . $authUrl);
exit();