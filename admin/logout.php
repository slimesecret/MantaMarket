<?php
require_once "../app/Libs/UserIdentity.php";
require_once "../app/Libs/Router.php";

$user = new app_Libs_UserIdentity();

// Lấy role TRƯỚC khi destroy session
$role = $_SESSION["role"] ?? "";

$user->logout();

if ($role === "seller") {
    header("Location: /MantaMarket/admin/index.php?r=login");
} else {
    (new app_Libs_Router)->loginPage();
}
exit();