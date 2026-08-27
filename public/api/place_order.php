<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'mantamarket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

header('Content-Type: application/json');

$user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

$input          = json_decode(file_get_contents('php://input'), true);
$cart_item_ids  = array_map('intval', $input['cart_item_ids'] ?? []);
$address_id     = (int)($input['address_id'] ?? 0);
$coupon_id      = !empty($input['coupon_id']) ? (int)$input['coupon_id'] : null;
$payment_method = $input['payment_method'] ?? 'cod';

$frontend_shipping_fee = isset($input['shipping_fee']) && is_numeric($input['shipping_fee'])
    ? (int)$input['shipping_fee']
    : null;

$buyer_wallet_input = trim($input['buyer_wallet'] ?? '');
$buyer_wallet = preg_match('/^0x[0-9a-fA-F]{40}$/', $buyer_wallet_input)
    ? $buyer_wallet_input : null;

if (!$buyer_wallet && $payment_method === 'bnb') {
    $wStmt = $pdo->prepare("
        SELECT buyer_wallet FROM orders
        WHERE user_id = ? AND payment_method = 'bnb' AND buyer_wallet IS NOT NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $wStmt->execute([$user_id]);
    $buyer_wallet = $wStmt->fetchColumn() ?: null;
}

if (empty($cart_item_ids) || !$address_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin']);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// 1. Lấy cart items — giữ $ph_items riêng, KHÔNG ghi đè sau này
// ════════════════════════════════════════════════════════════════════════════
$ph_items = implode(',', array_fill(0, count($cart_item_ids), '?'));

$stmt = $pdo->prepare("
    SELECT ci.id AS cart_item_id, ci.quantity, ci.product_id, ci.variant_id,
           p.name AS product_name, p.base_price, p.seller_id,
           pv.price AS variant_price, pv.compare_price, pv.sku, pv.color, pv.size,
           COALESCE(pv.stock_quantity, 0) AS stock
    FROM cart_items ci
    JOIN cart ct ON ct.id = ci.cart_id AND ct.user_id = ?
    JOIN products p ON p.id = ci.product_id AND p.status = 'active'
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    WHERE ci.id IN ($ph_items)
");
$stmt->execute(array_merge([$user_id], $cart_item_ids));
$items = $stmt->fetchAll();

if (empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy sản phẩm']);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// 2. Kiểm tra tồn kho TRƯỚC khi mở transaction  ← FIX LỖI 1
// ════════════════════════════════════════════════════════════════════════════
foreach ($items as $check) {
    if ((int)$check['stock'] < (int)$check['quantity']) {
        echo json_encode([
            'success' => false,
            'error'   => 'Sản phẩm "' . $check['product_name'] . '" chỉ còn ' . $check['stock'] . ' trong kho'
        ]);
        exit;
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 3. Nhóm theo seller & tính phí ship
// ════════════════════════════════════════════════════════════════════════════
$by_seller = [];
foreach ($items as $item) {
    $by_seller[$item['seller_id']][] = $item;
}

$num_sellers       = count($by_seller);
$shipping_fee_each = $frontend_shipping_fee !== null
    ? (int)round($frontend_shipping_fee / max($num_sellers, 1))
    : 28700;

$created_orders = [];

// ════════════════════════════════════════════════════════════════════════════
// 4. Transaction
// ════════════════════════════════════════════════════════════════════════════
try {
    $pdo->beginTransaction();

    foreach ($by_seller as $seller_id => $seller_items) {

        // ── Tính subtotal ────────────────────────────────────────────────
        $subtotal = 0;
        foreach ($seller_items as $item) {
            $price     = (float)($item['variant_price'] ?? $item['base_price']);
            $subtotal += $price * $item['quantity'];
        }

        // ── Áp voucher ──────────────────────────────────────────────────
        $discount       = 0;
        $used_coupon_id = null;
        if ($coupon_id) {
            $cStmt = $pdo->prepare("
                SELECT * FROM coupons
                WHERE id = ? AND is_active = 1 AND NOW() BETWEEN start_date AND end_date
                LIMIT 1
            ");
            $cStmt->execute([$coupon_id]);
            $coupon = $cStmt->fetch();
            if ($coupon && $coupon['seller_id'] == $seller_id) {
                if ($coupon['type'] === 'fixed') {
                    $discount = (float)$coupon['value'];
                } elseif ($coupon['type'] === 'free_ship') {
                    $discount = $shipping_fee_each;
                } elseif ($coupon['type'] === 'percent') {
                    $discount = $subtotal * $coupon['value'] / 100;
                    if ($coupon['max_discount']) {
                        $discount = min($discount, (float)$coupon['max_discount']);
                    }
                }
                $used_coupon_id = $coupon_id;
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")
                    ->execute([$coupon_id]);
            }
        }

        $total          = $subtotal + $shipping_fee_each - $discount;
        $order_code     = 'MTA' . strtoupper(uniqid());
        $payment_status = in_array($payment_method, ['bnb', 'wallet']) ? 'paid' : 'pending';

        // ── INSERT orders ────────────────────────────────────────────────
        $pdo->prepare("
            INSERT INTO orders
                (order_code, user_id, seller_id, coupon_id, address_id,
                 subtotal, shipping_fee, discount_amount, total_amount,
                 payment_method, payment_status, order_status, buyer_wallet)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([
            $order_code, $user_id, $seller_id, $used_coupon_id, $address_id,
            $subtotal, $shipping_fee_each, $discount, $total,
            $payment_method, $payment_status, $buyer_wallet
        ]);
        $order_id = $pdo->lastInsertId();

        // ── INSERT order_items + trừ kho + cộng sold ─────────────────────
        foreach ($seller_items as $item) {
            $unit_price  = (float)($item['variant_price'] ?? $item['base_price']);
            $total_price = $unit_price * $item['quantity'];
            $sku         = $item['sku'] ?? ('SKU-' . $item['product_id']);

            // INSERT order_items
            $pdo->prepare("
                INSERT INTO order_items
                    (order_id, product_id, variant_id, product_name, sku,
                     color, size, quantity, unit_price, discount, total_price)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ")->execute([
                $order_id, $item['product_id'], $item['variant_id'],
                $item['product_name'], $sku,
                $item['color'], $item['size'],
                $item['quantity'], $unit_price, $total_price
            ]);

            // ── Trừ stock_quantity ────────────────────────────────────────
            if ($item['variant_id']) {
                // Có variant
                $stockStmt = $pdo->prepare("
                    UPDATE product_variants
                    SET stock_quantity = stock_quantity - ?
                    WHERE id = ? AND stock_quantity >= ?
                ");
                $stockStmt->execute([$item['quantity'], $item['variant_id'], $item['quantity']]);

                if ($stockStmt->rowCount() === 0) {
                    // Race condition: vừa hết hàng giữa lúc check và update
                    throw new Exception('Sản phẩm "' . $item['product_name'] . '" vừa hết hàng, vui lòng thử lại');
                }

                // Ghi inventory_log
                $pdo->prepare("
                    INSERT INTO inventory_logs
                        (variant_id, action_type, quantity_change,
                         quantity_before, quantity_after, reference_id, note)
                    SELECT
                        id,
                        'export',
                        -?,
                        stock_quantity + ?,
                        stock_quantity,
                        ?,
                        CONCAT('Đơn hàng #', ?)
                    FROM product_variants WHERE id = ?
                ")->execute([
                    $item['quantity'],
                    $item['quantity'],
                    $order_id,
                    $order_code,
                    $item['variant_id']
                ]);

            } else {
                // ← FIX LỖI 3: Không có variant → trừ thẳng base_price product
                // (trường hợp sản phẩm đơn giản, không có biến thể)
                // Không có bảng stock riêng cho products nên chỉ log sold_count
                // Nếu dự án sau này thêm stock vào products thì update ở đây
            }

            // ── Cộng sold_count ───────────────────────────────────────────
            // Trigger chỉ cộng khi status = 'delivered'.
            // Cộng ngay để trang sản phẩm hiển thị "Đã bán" chính xác.
            $pdo->prepare("
                UPDATE products SET sold_count = sold_count + ? WHERE id = ?
            ")->execute([$item['quantity'], $item['product_id']]);
        }

        $created_orders[] = $order_code;
    }

    // ── Xóa cart items — dùng $ph_items đã tạo từ đầu  ← FIX LỖI 2 ────
    $pdo->prepare("DELETE FROM cart_items WHERE id IN ($ph_items)")
        ->execute($cart_item_ids);

    $pdo->commit();
    echo json_encode(['success' => true, 'orders' => $created_orders]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}