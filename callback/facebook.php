<?php
session_start();
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

$config   = require __DIR__ . '/../config/oauth.php';
$db       = new app_Libs_DbConnection();
$identity = new app_Libs_UserIdentity();
$router   = new app_Libs_Router();

if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    die('Invalid state.');
}

$provider = new League\OAuth2\Client\Provider\Facebook($config['facebook']);

try {
    $token  = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    $fbUser = $provider->getResourceOwner($token);
    $data   = $fbUser->toArray();

    $fbId   = $fbUser->getId();
    $email  = $data['email'] ?? null;
    $name   = $data['name'] ?? null;
    $avatar = isset($data['picture']['data']['url']) ? $data['picture']['data']['url'] : null;

    // Tìm theo provider_id hoặc email
    $user = $db->query(
        "SELECT * FROM users 
         WHERE (provider = 'facebook' AND provider_id = :pid)" .
        ($email ? " OR email = :email" : "") .
        " LIMIT 1",
        $email ? ['pid' => $fbId, 'email' => $email] : ['pid' => $fbId]
    )->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['provider'] !== 'facebook') {
            $db->query(
                "UPDATE users SET provider = 'facebook', provider_id = :pid, avatar = :avatar WHERE id = :id",
                ['pid' => $fbId, 'avatar' => $avatar, 'id' => $user['id']]
            );
        }
    } else {
        $db->query(
            "INSERT INTO users (full_name, email, provider, provider_id, avatar, role, is_active)
             VALUES (:name, :email, 'facebook', :pid, :avatar, 'user', 1)",
            ['name' => $name, 'email' => $email, 'pid' => $fbId, 'avatar' => $avatar]
        );
        $user = $db->query(
            "SELECT * FROM users WHERE provider_id = :pid LIMIT 1",
            ['pid' => $fbId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    $identity->login([
        'id'       => $user['id'],
        'username' => $user['full_name'],
        'role'     => $user['role'],
        'avatar'   => $user['avatar'],
    ]);

    if ($user['role'] === 'admin') {
        header('Location: /MantaMarket/admin/index.php#dashboard');
    } else {
        $router->userPage();
    }

} catch (Exception $e) {
    error_log('Facebook OAuth error: ' . $e->getMessage());
    header('Location: /MantaMarket/admin/index.php?r=login&error=oauth_failed');
}
exit();