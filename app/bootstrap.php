<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define("BASE_PATH", dirname(__DIR__));

spl_autoload_register(function ($class) {
    // Chuyển app_Libs_Router thành app/Libs/Router.php
    $path = BASE_PATH . '/' . str_replace('_', '/', $class) . '.php';

    if (file_exists($path)) {
        require_once $path;
        return true;
    }

    // Không echo lỗi ở đây — autoloader được gọi liên tục.
    // Có thể log nếu cần:
    // error_log("Autoload fail: $path");
    
    return false;
});
