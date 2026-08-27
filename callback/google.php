<?php
session_start();
require_once __DIR__ . '/../app/bootstrap.php';  // ← THÊM DÒNG NÀY
require_once __DIR__ . '/../vendor/autoload.php';

$config   = require __DIR__ . '/../config/oauth.php';
$db       = new app_Libs_DbConnection();


$identity = new app_Libs_UserIdentity();
$router   = new app_Libs_Router();

if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    die('Invalid state.');
}

$provider = new League\OAuth2\Client\Provider\Google($config['google']);

try {
    $token      = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    $googleUser = $provider->getResourceOwner($token);
    $googleId   = $googleUser->getId();
    $email      = $googleUser->getEmail();
    $name       = $googleUser->getName();
    $avatar     = $googleUser->getAvatar();

    // Tìm theo provider_id hoặc email
    $user = $db->query(
        "SELECT * FROM users 
         WHERE (provider = 'google' AND provider_id = :pid) 
            OR email = :email 
         LIMIT 1",
        ['pid' => $googleId, 'email' => $email]
    )->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Cập nhật provider info nếu chưa có
        if ($user['provider'] !== 'google') {
            $db->query(
                "UPDATE users SET provider = 'google', provider_id = :pid, avatar = :avatar WHERE id = :id",
                ['pid' => $googleId, 'avatar' => $avatar, 'id' => $user['id']]
            );
        }
    } else {
        // Tạo user mới
        $db->query(
            "INSERT INTO users (full_name, email, provider, provider_id, avatar, role, is_active)
             VALUES (:name, :email, 'google', :pid, :avatar, 'user', 1)",
            ['name' => $name, 'email' => $email, 'pid' => $googleId, 'avatar' => $avatar]
        );
        $user = $db->query(
            "SELECT * FROM users WHERE provider_id = :pid LIMIT 1",
            ['pid' => $googleId]
        )->fetch(PDO::FETCH_ASSOC);
    }

    $identity->login([
        'id'       => $user['id'],
        'username' => $user['full_name'],
        'role'     => $user['role'],
        'avatar'   => $user['avatar'],
    ]);

    if ($user['role'] === 'admin') {
        $router->adminPage();
    } else {
        $router->userPage();
    }

} catch (Exception $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    header('Location: ?r=login&error=oauth_failed');
}
exit();