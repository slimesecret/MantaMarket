<?php

include '../app/bootstrap.php';

// Trỏ đúng vào thư mục views
$router = new app_Libs_Router(__DIR__ . '/views');

if ($router->getGET('r') === 'logout') {
    $identity = new app_Libs_UserIdentity();
    $identity->logout();
    header("Location: index.php");
    exit;
}

$router->router();

?>