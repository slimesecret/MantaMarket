<?php
session_start();
header('Content-Type: application/json');

$user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=mantamarket;charset=utf8mb4",
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi DB']);
    exit;
}

function getOrCreateCart($pdo, $user_id)
{
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();
    if ($cart) return $cart['id'];
    $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)")->execute([$user_id]);
    return (int)$pdo->lastInsertId();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── THÊM VÀO GIỎ ──
if ($action === 'add') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $variant_id = $_POST['variant_id'] ? (int)$_POST['variant_id'] : null;
    $quantity   = max(1, (int)($_POST['quantity'] ?? 1));

    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Thiếu product_id']);
        exit;
    }

    if ($variant_id) {
        $st = $pdo->prepare("SELECT stock_quantity FROM product_variants WHERE id = ? AND is_active = 1");
        $st->execute([$variant_id]);
        $v = $st->fetch();
        if (!$v || $v['stock_quantity'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Không đủ hàng']);
            exit;
        }
    }

    $cart_id = getOrCreateCart($pdo, $user_id);

    $stmt = $pdo->prepare("
        SELECT id, quantity FROM cart_items
        WHERE cart_id = ? AND product_id = ? AND (variant_id = ? OR (variant_id IS NULL AND ? IS NULL))
    ");
    $stmt->execute([$cart_id, $product_id, $variant_id, $variant_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE id = ?")
            ->execute([$quantity, $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (?,?,?,?)")
            ->execute([$cart_id, $product_id, $variant_id, $quantity]);
    }

    $total = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
    $total->execute([$cart_id]);
    $count = (int)$total->fetchColumn();

    echo json_encode(['success' => true, 'message' => 'Đã thêm vào giỏ!', 'cart_count' => $count]);
    exit;
}

// ── XÓA SAU KHI THANH TOÁN ──
if ($action === 'clear_after_payment') {
    $item_ids = array_map('intval', $_POST['item_ids'] ?? []);
    if (empty($item_ids)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();

    if ($cart) {
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND id IN ($placeholders)")
            ->execute(array_merge([$cart['id']], $item_ids));
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── LẤY GIỎ HÀNG (mini) ──
if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();
    if (!$cart) {
        echo json_encode(['success' => true, 'items' => [], 'total_count' => 0, 'total_price' => 0]);
        exit;
    }

    // Lấy ảnh theo variant_id trước, fallback về is_primary
    $stmt = $pdo->prepare("
    SELECT ci.id, ci.quantity,
           p.id AS product_id, p.name AS product_name,
           COALESCE(pv.price, p.base_price) AS price,
           pv.color, pv.size, ci.variant_id,
           s.shop_name, s.id AS seller_id, s.shop_slug,
           (
               SELECT pi.image_url 
               FROM product_images pi
               LEFT JOIN product_variants pv2 ON pv2.id = pi.variant_id
               WHERE pi.product_id = p.id
               ORDER BY 
                   (pi.variant_id = ci.variant_id) DESC,
                   (pv2.color = pv.color) DESC,
                   pi.is_primary DESC,
                   pi.sort_order ASC
               LIMIT 1
           ) AS image_url
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    LEFT JOIN sellers s ON s.id = p.seller_id
    WHERE ci.cart_id = ?
    ORDER BY ci.updated_at DESC
    LIMIT 50
");
    $stmt->execute([$cart['id']]);
    $items = $stmt->fetchAll();

    $count_stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
    $count_stmt->execute([$cart['id']]);
    $total_count = (int)$count_stmt->fetchColumn();

    $price_stmt = $pdo->prepare("
        SELECT SUM(ci.quantity * COALESCE(pv.price, p.base_price))
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN product_variants pv ON pv.id = ci.variant_id
        WHERE ci.cart_id = ?
    ");
    $price_stmt->execute([$cart['id']]);
    $total_price = (float)$price_stmt->fetchColumn();

    echo json_encode([
        'success'     => true,
        'items'       => $items,
        'total_count' => $total_count,
        'total_price' => $total_price
    ]);
    exit;
}

// ── XÓA KHỎI GIỎ ──
if ($action === 'remove') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();
    if ($cart) {
        $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?")
            ->execute([$item_id, $cart['id']]);
        $total = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
        $total->execute([$cart['id']]);
        $count = (int)$total->fetchColumn();
        echo json_encode(['success' => true, 'cart_count' => $count]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
