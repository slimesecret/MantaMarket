<?php
include '../app/config.php';
include '../app/bootstrap.php';
$router = new app_Libs_Router(__DIR__);
$identity = new app_Libs_UserIdentity();
if (($router->getGET('r') !== 'login') &&
    (!$identity->isLogin() || $_SESSION["role"] != "seller")
) {
    $router->loginPage();
}
$router->router();
