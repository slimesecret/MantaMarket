<?php
// /MantaMarket/public/api/save_wallet.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$wallet   = trim($_POST['wallet']   ?? '');
$order_id = intval($_POST['order_id'] ?? 0);

if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $wallet)) {
    echo json_encode(['success' => false, 'message' => 'Địa chỉ ví không hợp lệ']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=mantamarket;charset=utf8mb4",
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($order_id) {
        // Lưu vào đơn hàng cụ thể (khi thanh toán BNB)
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET buyer_wallet = ? 
            WHERE id = ? AND user_id = ? AND payment_method = 'bnb'
        ");
        $stmt->execute([$wallet, $order_id, $user_id]);
    } else {
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET buyer_wallet = ? 
        WHERE user_id = ? AND payment_method = 'bnb'
    ");
    $stmt->execute([$wallet, $user_id]);
}

    echo json_encode(['success' => true, 'wallet' => $wallet]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi server']);
}