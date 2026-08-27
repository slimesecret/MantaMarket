<?php
// ── Load .env vào getenv() ──────────────────────────────
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue; // bỏ qua comment
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}
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
