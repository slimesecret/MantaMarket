<?php
/**
 * sellers/orders/index.php
 * Chỉ hiển thị đơn hàng & sản phẩm thuộc seller đang đăng nhập.
 */

$user   = new app_Libs_UserIdentity();
$router = new app_Libs_Router();
$db     = new app_Libs_DbConnection();

$action = $router->getPOST("action");
$id     = intval($router->getPOST("id") ?? $router->getGET("id") ?? 0);

// ── Lấy sellerId ──
if (!isset($sellerId) || !$sellerId) {
    $sellerRow = $db->query(
        "SELECT id FROM sellers WHERE user_id = ?",
        [$_SESSION['userId'] ?? 0]
    )->fetch();
    $sellerId = $sellerRow ? (int)$sellerRow['id'] : 0;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

// Helper: kiểm tra đơn có thuộc seller không
function sellerOwnsOrder(object $db, int $orderId, int $sellerId): bool {
    $row = $db->query(
        "SELECT id FROM orders WHERE id = ? AND seller_id = ?",
        [$orderId, $sellerId]
    )->fetch();
    return (bool)$row;
}

// ── DUYỆT YÊU CẦU HỦY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve_cancel' && $id) {
    $admin_note = trim($_POST['admin_note'] ?? '');
    $req = $db->query(
        "SELECT cr.* FROM cancel_requests cr
         JOIN orders o ON o.id = cr.order_id AND o.seller_id = ?
         WHERE cr.id = ? AND cr.status = 'pending'",
        [$sellerId, $id]
    )->fetch();
    if ($req) {
        $db->query(
            "UPDATE orders SET order_status = 'cancelled', cancelled_reason = ? WHERE id = ? AND seller_id = ?",
            [$req['reason'], $req['order_id'], $sellerId]
        );
        $db->query(
            "UPDATE cancel_requests SET status = 'approved', admin_note = ? WHERE id = ?",
            [$admin_note, $id]
        );
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$req]);
        exit;
    }
    header("Location: /MantaMarket/sellers/index.php?page=orders#orders");
    exit();
}

// ── TỪ CHỐI YÊU CẦU HỦY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reject_cancel' && $id) {
    $admin_note = trim($_POST['admin_note'] ?? '');
    $req = $db->query(
        "SELECT cr.id FROM cancel_requests cr
         JOIN orders o ON o.id = cr.order_id AND o.seller_id = ?
         WHERE cr.id = ?",
        [$sellerId, $id]
    )->fetch();
    if ($req) {
        $db->query(
            "UPDATE cancel_requests SET status = 'rejected', admin_note = ? WHERE id = ?",
            [$admin_note, $id]
        );
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => (bool)$req]);
        exit;
    }
    header("Location: /MantaMarket/sellers/index.php?page=orders#orders");
    exit();
}

// ── CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status' && $id) {
    if (sellerOwnsOrder($db, $id, $sellerId)) {
        $new_status     = $_POST['order_status'] ?? '';
        $valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
        if (in_array($new_status, $valid_statuses)) {
            $cancelled_reason = ($new_status === 'cancelled') ? trim($_POST['cancelled_reason'] ?? '') : null;
            $delivered_at     = ($new_status === 'delivered') ? date('Y-m-d H:i:s') : null;
            $sql    = "UPDATE orders SET order_status = ?";
            $params = [$new_status];
            if ($cancelled_reason !== null) { $sql .= ", cancelled_reason = ?"; $params[] = $cancelled_reason; }
            if ($delivered_at    !== null)  { $sql .= ", delivered_at = ?";     $params[] = $delivered_at; }
            $sql .= " WHERE id = ? AND seller_id = ?";
            $params[] = $id;
            $params[] = $sellerId;
            $db->query($sql, $params);
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: /MantaMarket/sellers/index.php?page=orders#orders");
    exit();
}

// ── CẬP NHẬT TRẠNG THÁI THANH TOÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_payment' && $id) {
    if (sellerOwnsOrder($db, $id, $sellerId)) {
        $pay_status = $_POST['payment_status'] ?? '';
        $valid_pay  = ['pending', 'paid', 'failed', 'refunded'];
        if (in_array($pay_status, $valid_pay)) {
            $refund_tx  = trim($_POST['refund_tx_hash'] ?? '');
            $refund_bnb = floatval($_POST['refund_bnb'] ?? 0);
            if ($refund_tx) {
                $db->query(
                    "UPDATE orders SET payment_status=?, refund_tx_hash=?, refund_bnb_amount=? WHERE id=? AND seller_id=?",
                    [$pay_status, $refund_tx, $refund_bnb, $id, $sellerId]
                );
            } else {
                $db->query(
                    "UPDATE orders SET payment_status=? WHERE id=? AND seller_id=?",
                    [$pay_status, $id, $sellerId]
                );
            }
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header("Location: /MantaMarket/sellers/index.php?page=orders#orders");
    exit();
}

// ── XÓA 1 ĐƠN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $db->query("DELETE FROM orders WHERE id = ? AND seller_id = ?", [$id, $sellerId]);
    header("Location: /MantaMarket/sellers/index.php?page=orders#orders");
    exit();
}

// ── XÓA NHIỀU ĐƠN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    foreach ($ids as $did) {
        if ($did) $db->query("DELETE FROM orders WHERE id = ? AND seller_id = ?", [$did, $sellerId]);
    }
    header("Location: /MantaMarket/sellers/index.php?page=orders#orders");
    exit();
}

// ── YÊU CẦU HỦY ĐANG PENDING (chỉ của seller này) ──
$cancel_requests = $db->query(
    "SELECT cr.*, o.order_code, o.total_amount, o.order_status,
            u.full_name AS buyer_name, u.email AS buyer_email,
            s.shop_name
     FROM cancel_requests cr
     JOIN orders o ON o.id = cr.order_id AND o.seller_id = ?
     LEFT JOIN users  u ON u.id = cr.user_id
     LEFT JOIN sellers s ON s.id = o.seller_id
     WHERE cr.status = 'pending'
     ORDER BY cr.created_at DESC",
    [$sellerId]
)->fetchAll();

// ── LẤY DỮ LIỆU DANH SÁCH ──
$search        = trim($router->getGET("q") ?? '');
$filter_status = trim($router->getGET("status") ?? '');
$filter_pay    = trim($router->getGET("pay") ?? '');
$page_num      = max(1, intval($router->getGET("p") ?? 1));
$per_page      = 10;

// Base WHERE luôn filter theo seller
$where  = "WHERE o.seller_id = :sid";
$params = [':sid' => $sellerId];

if ($filter_status) {
    $where .= " AND o.order_status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_pay) {
    $where .= " AND o.payment_status = :pay";
    $params[':pay'] = $filter_pay;
}
if ($search) {
    $where .= " AND (o.order_code LIKE :q OR u.full_name LIKE :q2 OR u.email LIKE :q3)";
    $params[':q']  = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}

$count_sql  = "SELECT COUNT(*) AS total FROM orders o LEFT JOIN users u ON u.id = o.user_id $where";
$total_row  = $db->query($count_sql, $params)->fetch();
$total_rows = (int)($total_row['total'] ?? 0);
$total_pages = max(1, ceil($total_rows / $per_page));
$page_num    = min($page_num, $total_pages);
$offset      = ($page_num - 1) * $per_page;

$main_sql = "
    SELECT o.id, o.order_code, o.order_status, o.payment_status, o.payment_method,
           o.total_amount, o.subtotal, o.shipping_fee, o.discount_amount,
           o.note, o.cancelled_reason, o.delivered_at, o.created_at,
           o.buyer_wallet, o.refund_tx_hash, o.refund_bnb_amount,
           u.full_name AS buyer_name, u.email AS buyer_email, u.avatar AS buyer_avatar,
           s.shop_name, s.shop_slug,
           ua.province, ua.district, ua.ward, ua.address_line, ua.phone AS addr_phone
    FROM orders o
    LEFT JOIN users u           ON u.id  = o.user_id
    LEFT JOIN sellers s         ON s.id  = o.seller_id
    LEFT JOIN user_addresses ua ON ua.id = o.address_id
    $where
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$orders_data = $db->query($main_sql, $params)->fetchAll();

// ── Items CHỈ của seller này trong từng đơn ──
$order_ids = array_column($orders_data, 'id');
$items_map = [];
if (!empty($order_ids)) {
    $ph = implode(',', array_fill(0, count($order_ids), '?'));
    /*
     * JOIN thêm bảng products để lọc p.seller_id = $sellerId
     * Đảm bảo seller chỉ thấy sản phẩm của mình trong đơn hàng,
     * kể cả khi order chứa hàng của nhiều seller (marketplace).
     *
     * Nếu KHÔNG dùng bảng products (product đã xóa / không liên kết),
     * fallback dùng order_items.seller_id nếu cột đó tồn tại —
     * bạn có thể đổi điều kiện WHERE tùy schema thực tế.
     */
    $rows = $db->query(
        "SELECT oi.order_id, oi.product_id, oi.product_name,
                oi.quantity, oi.unit_price, oi.discount,
                oi.color, oi.size,
                pi.image_url
         FROM order_items oi
         JOIN products p
             ON p.id = oi.product_id
             AND p.seller_id = ?
         LEFT JOIN product_images pi
             ON pi.product_id = oi.product_id
             AND pi.is_primary = 1
         WHERE oi.order_id IN ($ph)",
        array_merge([$sellerId], $order_ids)   // sellerId đứng trước vì ? đầu tiên là p.seller_id
    )->fetchAll();

    foreach ($rows as $r) {
        $items_map[$r['order_id']][] = $r;
    }
}

// ── Thống kê chỉ của seller ──
$stats = $db->query(
    "SELECT
        COUNT(*) AS total,
        SUM(order_status IN ('pending','confirmed','processing')) AS processing,
        SUM(order_status = 'delivered')  AS delivered,
        SUM(order_status = 'cancelled')  AS cancelled,
        SUM(order_status = 'shipped')    AS shipped
     FROM orders
     WHERE seller_id = ?",
    [$sellerId]
)->fetch();

// ── Doanh thu thực tế từ items của seller (đơn đã hoàn thành) ──
$revenue_stats = $db->query(
    "SELECT
        COALESCE(SUM((oi.unit_price - oi.discount) * oi.quantity), 0) AS seller_revenue,
        COUNT(DISTINCT oi.order_id) AS delivered_orders
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id AND p.seller_id = ?
     JOIN orders o   ON o.id = oi.order_id   AND o.order_status = 'delivered'",
    [$sellerId]
)->fetch();

function fmt_price_admin(float $n): string {
    return number_format($n, 0, ',', '.') . 'đ';
}

$status_labels = [
    'pending'    => ['CHỜ XÁC NHẬN', 'badge-pending'],
    'confirmed'  => ['ĐÃ XÁC NHẬN',  'badge-confirmed'],
    'processing' => ['ĐANG XỬ LÝ',   'badge-processing'],
    'shipped'    => ['ĐANG GIAO',     'badge-shipped'],
    'delivered'  => ['HOÀN THÀNH',   'badge-delivered'],
    'cancelled'  => ['ĐÃ HỦY',       'badge-cancelled'],
    'returned'   => ['TRẢ HÀNG',     'badge-returned'],
];
$pay_labels = [
    'pending'  => ['Chờ TT',    'pay-pending'],
    'paid'     => ['Đã TT',     'pay-paid'],
    'failed'   => ['Thất bại',  'pay-failed'],
    'refunded' => ['Hoàn tiền', 'pay-refunded'],
];
$pay_method_labels = [
    'cod'           => 'COD',
    'bank_transfer' => 'Chuyển khoản',
    'momo'          => 'MoMo',
    'vnpay'         => 'VNPay',
    'zalopay'       => 'ZaloPay',
    'credit_card'   => 'Thẻ TD',
    'bnb'           => 'BNB',
];
$msg = $router->getGET("msg") ?? '';
?>

<style>
    .o-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:22px; }
    .o-stat { background:#fff; border-radius:14px; padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:0 1px 4px rgba(0,0,0,.06); border:1px solid var(--border); }
    .o-stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
    .o-stat-icon.purple { background:#ede9fe; color:var(--purple1); }
    .o-stat-icon.orange { background:#fff7ed; color:var(--orange); }
    .o-stat-icon.green  { background:#d1fae5; color:var(--green); }
    .o-stat-icon.red    { background:#fee2e2; color:var(--red); }
    .o-stat-icon.blue   { background:#dbeafe; color:var(--blue); }
    .o-stat-icon.teal   { background:#ccfbf1; color:#0d9488; }
    .o-stat-val { font-size:22px; font-weight:800; color:var(--text); line-height:1; }
    .o-stat-lbl { font-size:12px; color:var(--muted); margin-top:3px; }

    .o-filter { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
    .o-filter input, .o-filter select { padding:8px 12px; border:1.5px solid var(--border); border-radius:9px; font-size:13px; color:var(--text); background:#fff; outline:none; transition:border-color .2s; }
    .o-filter input:focus, .o-filter select:focus { border-color:var(--purple1); }
    .o-filter input { width:260px; }

    .o-table-wrap { background:#fff; border-radius:16px; box-shadow:0 1px 6px rgba(0,0,0,.07); border:1px solid var(--border); overflow:hidden; }
    .o-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .o-table thead th { background:#faf9ff; padding:12px 14px; text-align:left; font-weight:600; color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.5px; border-bottom:1.5px solid var(--border); white-space:nowrap; }
    .o-table tbody tr { border-bottom:1px solid #f3f4f6; transition:background .15s; }
    .o-table tbody tr:last-child { border-bottom:none; }
    .o-table tbody tr:hover { background:#faf9ff; }
    .o-table td { padding:12px 14px; vertical-align:middle; }

    .badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; letter-spacing:.3px; white-space:nowrap; }
    .badge-pending    { background:#fef3c7; color:#92400e; }
    .badge-confirmed  { background:#dbeafe; color:#1d4ed8; }
    .badge-processing { background:#e0e7ff; color:#4338ca; }
    .badge-shipped    { background:#cffafe; color:#0e7490; }
    .badge-delivered  { background:#d1fae5; color:#065f46; }
    .badge-cancelled  { background:#fee2e2; color:#991b1b; }
    .badge-returned   { background:#fce7f3; color:#9d174d; }
    .pay-pending  { background:#fef9c3; color:#a16207; }
    .pay-paid     { background:#dcfce7; color:#166534; }
    .pay-failed   { background:#fee2e2; color:#991b1b; }
    .pay-refunded { background:#ede9fe; color:#5b21b6; }

    .cust-cell { display:flex; align-items:center; gap:9px; }
    .cust-av { width:32px; height:32px; border-radius:50%; object-fit:cover; background:#ede9fe; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:var(--purple1); flex-shrink:0; overflow:hidden; }
    .cust-av img { width:100%; height:100%; object-fit:cover; }
    .cust-name  { font-weight:600; color:var(--text); font-size:13px; }
    .cust-email { font-size:11.5px; color:var(--muted); }

    /* Items preview in table row */
    .items-preview { display:flex; gap:4px; flex-wrap:wrap; margin-top:5px; }
    .item-thumb { width:28px; height:28px; border-radius:5px; object-fit:cover; border:1.5px solid var(--border); background:#f3f4f6; }
    .item-count-badge { width:28px; height:28px; border-radius:5px; background:#ede9fe; color:var(--purple1); font-size:10px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; border:1.5px solid #c4b5fd; }

    .act-btns { display:flex; gap:5px; flex-wrap:wrap; }
    .btn-view-o { padding:5px 10px; border-radius:7px; border:1.5px solid #e0e7ff; background:#f5f3ff; color:var(--purple1); font-size:12px; cursor:pointer; font-weight:600; transition:all .15s; }
    .btn-view-o:hover { background:var(--purple1); color:#fff; border-color:var(--purple1); }
    .btn-del-o { padding:5px 10px; border-radius:7px; border:1.5px solid #fee2e2; background:#fff5f5; color:var(--red); font-size:12px; cursor:pointer; font-weight:600; transition:all .15s; }
    .btn-del-o:hover { background:var(--red); color:#fff; border-color:var(--red); }

    .o-pagination { display:flex; align-items:center; gap:6px; padding:14px 18px; border-top:1px solid var(--border); justify-content:space-between; }
    .o-page-info { font-size:13px; color:var(--muted); }
    .o-page-btns { display:flex; gap:4px; }
    .o-pg-btn { width:32px; height:32px; border-radius:8px; border:1.5px solid var(--border); background:#fff; color:var(--text); font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
    .o-pg-btn:hover, .o-pg-btn.active { background:var(--purple1); color:#fff; border-color:var(--purple1); }

    .o-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; padding:20px; }
    .o-modal-bg.open { display:flex; }
    .o-modal { background:#fff; border-radius:20px; width:100%; max-width:720px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .o-modal-header { padding:22px 26px 0; display:flex; align-items:flex-start; justify-content:space-between; position:sticky; top:0; background:#fff; z-index:1; border-bottom:1px solid var(--border); padding-bottom:16px; }
    .o-modal-title { font-size:18px; font-weight:800; color:var(--text); }
    .o-modal-sub { font-size:13px; color:var(--muted); margin-top:4px; }
    .o-modal-close { background:none; border:none; font-size:22px; color:var(--muted); cursor:pointer; padding:0; line-height:1; }
    .o-modal-body { padding:20px 26px 26px; }

    .o-section { margin-bottom:20px; }
    .o-section-title { font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--border); }
    .o-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .o-info-item { background:#faf9ff; border-radius:10px; padding:10px 14px; }
    .o-info-key { font-size:11px; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-bottom:3px; }
    .o-info-val { font-size:14px; color:var(--text); font-weight:600; }

    /* Items in modal */
    .o-item-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #f3f4f6; }
    .o-item-row:last-child { border-bottom:none; }
    .o-item-img { width:52px; height:52px; border-radius:10px; object-fit:cover; background:#f3f4f6; flex-shrink:0; border:1.5px solid var(--border); }
    .o-item-name { font-weight:600; font-size:13px; color:var(--text); }
    .o-item-meta { font-size:12px; color:var(--muted); margin-top:3px; }
    .o-item-price { margin-left:auto; text-align:right; font-weight:700; color:var(--purple1); font-size:13px; white-space:nowrap; }
    .o-item-subtotal { font-size:11px; color:var(--muted); margin-top:2px; }

    /* Seller revenue summary box */
    .seller-revenue-box { background:linear-gradient(135deg,#f5f3ff,#ede9fe); border:1.5px solid #c4b5fd; border-radius:12px; padding:14px 18px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .seller-revenue-box .rev-label { font-size:11px; color:#5b21b6; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
    .seller-revenue-box .rev-val { font-size:20px; font-weight:800; color:var(--purple1); }
    .seller-revenue-box .rev-note { font-size:11px; color:#7c3aed; margin-top:2px; }

    /* No items state */
    .no-items-box { padding:24px; text-align:center; background:#faf9ff; border-radius:12px; border:1.5px dashed var(--border); color:var(--muted); font-size:13px; }
    .no-items-box i { font-size:28px; display:block; margin-bottom:8px; opacity:.3; }

    .o-status-form { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:14px; }
    .o-status-form select { padding:8px 12px; border:1.5px solid var(--border); border-radius:9px; font-size:13px; color:var(--text); background:#fff; flex:1; min-width:160px; }
    .o-status-form textarea { width:100%; padding:9px 12px; border:1.5px solid var(--border); border-radius:9px; font-size:13px; resize:vertical; min-height:60px; }
    .btn-update { padding:8px 18px; border-radius:9px; border:none; background:var(--purple1); color:#fff; font-weight:700; font-size:13px; cursor:pointer; transition:opacity .15s; }
    .btn-update:hover { opacity:.85; }

    .o-toast { position:fixed; bottom:24px; right:24px; padding:12px 20px; border-radius:12px; font-size:14px; font-weight:600; z-index:99999; color:#fff; box-shadow:0 4px 20px rgba(0,0,0,.15); animation:slideUp .3s ease; }
    .o-toast.success { background:var(--green); }
    .o-toast.info    { background:var(--purple1); }
    .o-toast.error   { background:var(--red); }
    @keyframes slideUp { from { transform:translateY(20px); opacity:0 } to { transform:translateY(0); opacity:1 } }

    .cb-custom { width:16px; height:16px; border:2px solid var(--border); border-radius:4px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .15s; flex-shrink:0; }
    .cb-custom.checked { background:var(--purple1); border-color:var(--purple1); color:#fff; }

    .o-empty { padding:60px; text-align:center; color:var(--muted); font-size:14px; }
    .o-empty i { font-size:40px; margin-bottom:12px; display:block; opacity:.3; }

    .method-badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#f3f4f6; color:var(--muted); }
    .method-bnb   { background:#fef9c3; color:#92400e; }
    .method-cod   { background:#f3f4f6; color:#374151; }
    .method-momo  { background:#fce7f3; color:#9d174d; }
    .method-vnpay { background:#dbeafe; color:#1d4ed8; }
</style>

<?php if ($msg === 'updated'): ?>
    <div class="o-toast success" id="oToast"><i class="fas fa-check"></i> Cập nhật thành công!</div>
    <script>setTimeout(() => document.getElementById('oToast')?.remove(), 3000)</script>
<?php elseif ($msg === 'deleted'): ?>
    <div class="o-toast info" id="oToast"><i class="fas fa-trash"></i> Đã xóa đơn hàng!</div>
    <script>setTimeout(() => document.getElementById('oToast')?.remove(), 3000)</script>
<?php endif; ?>

<div class="page" id="page-orders">
    <div class="page-header">
        <h1 class="page-title">Quản lý Đơn hàng</h1>
        <div class="page-actions">
            <button class="btn-action" onclick="bulkDelete()"><i class="fas fa-trash"></i> Xóa đã chọn</button>
        </div>
    </div>

    <!-- STATS -->
    <div class="o-stats">
        <div class="o-stat">
            <div class="o-stat-icon purple"><i class="fas fa-box"></i></div>
            <div><div class="o-stat-val"><?= number_format($stats['total']) ?></div><div class="o-stat-lbl">Tổng đơn hàng</div></div>
        </div>
        <div class="o-stat">
            <div class="o-stat-icon orange"><i class="fas fa-spinner"></i></div>
            <div><div class="o-stat-val"><?= number_format($stats['processing']) ?></div><div class="o-stat-lbl">Đang xử lý</div></div>
        </div>
        <div class="o-stat">
            <div class="o-stat-icon blue"><i class="fas fa-truck"></i></div>
            <div><div class="o-stat-val"><?= number_format($stats['shipped']) ?></div><div class="o-stat-lbl">Đang giao</div></div>
        </div>
        <div class="o-stat">
            <div class="o-stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div><div class="o-stat-val"><?= number_format($stats['delivered']) ?></div><div class="o-stat-lbl">Hoàn thành</div></div>
        </div>
        <div class="o-stat">
            <div class="o-stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div><div class="o-stat-val"><?= number_format($stats['cancelled']) ?></div><div class="o-stat-lbl">Đã hủy</div></div>
        </div>
    </div>

    <!-- Doanh thu seller -->
    <?php if (($revenue_stats['seller_revenue'] ?? 0) > 0): ?>
    <div class="seller-revenue-box" style="margin-bottom:22px;">
        <div>
            <div class="rev-label"><i class="fas fa-coins" style="margin-right:5px"></i>Doanh thu của bạn (đơn hoàn thành)</div>
            <div class="rev-val"><?= fmt_price_admin((float)$revenue_stats['seller_revenue']) ?></div>
            <div class="rev-note">Từ <?= number_format($revenue_stats['delivered_orders']) ?> đơn đã giao thành công · Chỉ tính sản phẩm của shop bạn</div>
        </div>
        <i class="fas fa-chart-line" style="font-size:32px;color:#c4b5fd;opacity:.6"></i>
    </div>
    <?php endif; ?>

    <!-- FILTER -->
    <form method="GET" action="/MantaMarket/sellers/index.php" class="o-filter">
        <input type="hidden" name="page" value="orders">
        <i class="fas fa-search" style="color:var(--muted);font-size:14px"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm mã đơn, tên khách, email...">
        <select name="status" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <?php foreach ($status_labels as $k => [$lbl, $cls]): ?>
                <option value="<?= $k ?>" <?= $filter_status === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <select name="pay" onchange="this.form.submit()">
            <option value="">Tất cả thanh toán</option>
            <option value="pending"  <?= $filter_pay === 'pending'  ? 'selected' : '' ?>>Chờ TT</option>
            <option value="paid"     <?= $filter_pay === 'paid'     ? 'selected' : '' ?>>Đã TT</option>
            <option value="failed"   <?= $filter_pay === 'failed'   ? 'selected' : '' ?>>Thất bại</option>
            <option value="refunded" <?= $filter_pay === 'refunded' ? 'selected' : '' ?>>Hoàn tiền</option>
        </select>
        <button type="submit" class="btn-update"><i class="fas fa-search"></i> Tìm</button>
        <?php if ($search || $filter_status || $filter_pay): ?>
            <a href="/MantaMarket/sellers/index.php?page=orders#orders" class="btn-action">
                <i class="fas fa-times"></i> Xóa lọc
            </a>
        <?php endif; ?>
    </form>

    <!-- YÊU CẦU HỦY ĐƠN -->
    <?php if (!empty($cancel_requests)): ?>
        <div style="background:#fff;border-radius:16px;border:2px solid #fee2e2;padding:20px;margin-bottom:22px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                <div style="background:#fee2e2;border-radius:10px;padding:8px 12px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div style="font-weight:800;color:#dc2626;font-size:15px;">Yêu cầu hủy đơn đang chờ duyệt (<?= count($cancel_requests) ?>)</div>
                    <div style="font-size:12px;color:#6b7280;">Cần xử lý sớm</div>
                </div>
            </div>
            <?php foreach ($cancel_requests as $cr): ?>
                <div style="border:1.5px solid #fecaca;border-radius:12px;padding:16px;margin-bottom:12px;background:#fff5f5;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                        <div>
                            <div style="font-weight:700;color:#1e1b4b;font-size:14px;">
                                #<?= htmlspecialchars($cr['order_code']) ?>
                                <span style="font-size:12px;color:#6b7280;margin-left:6px;">· <?= htmlspecialchars($cr['shop_name'] ?? '—') ?></span>
                            </div>
                            <div style="font-size:13px;color:#374151;margin-top:4px;">
                                Khách: <strong><?= htmlspecialchars($cr['buyer_name']) ?></strong> · <?= htmlspecialchars($cr['buyer_email']) ?>
                            </div>
                            <div style="font-size:13px;color:#374151;margin-top:4px;">
                                Tổng tiền: <strong style="color:#7c3aed;"><?= fmt_price_admin((float)$cr['total_amount']) ?></strong>
                            </div>
                            <div style="margin-top:8px;padding:8px 12px;background:#fef2f2;border-radius:8px;border-left:3px solid #dc2626;">
                                <div style="font-size:11px;color:#dc2626;font-weight:700;margin-bottom:2px;">LÝ DO HỦY:</div>
                                <div style="font-size:13px;color:#374151;"><?= htmlspecialchars($cr['reason']) ?></div>
                            </div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:6px;">Gửi lúc: <?= date('H:i d/m/Y', strtotime($cr['created_at'])) ?></div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px;min-width:200px;">
                            <form method="POST" action="/MantaMarket/sellers/index.php?page=orders"
                                  onsubmit="return ajaxCancelAction(event, this, 'Duyệt hủy đơn #<?= htmlspecialchars($cr['order_code']) ?>?')">
                                <input type="hidden" name="action" value="approve_cancel">
                                <input type="hidden" name="id" value="<?= $cr['id'] ?>">
                                <input type="text" name="admin_note" placeholder="Ghi chú (tuỳ chọn)"
                                    style="width:100%;padding:6px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12px;margin-bottom:6px;">
                                <button type="submit"
                                    style="width:100%;padding:8px;border-radius:8px;border:none;background:#dc2626;color:#fff;font-weight:700;font-size:13px;cursor:pointer;">
                                    ✓ Duyệt hủy đơn
                                </button>
                            </form>
                            <form method="POST" action="/MantaMarket/sellers/index.php?page=orders"
                                  onsubmit="return ajaxCancelAction(event, this, 'Từ chối yêu cầu hủy này?')">
                                <input type="hidden" name="action" value="reject_cancel">
                                <input type="hidden" name="id" value="<?= $cr['id'] ?>">
                                <input type="text" name="admin_note" placeholder="Lý do từ chối"
                                    style="width:100%;padding:6px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12px;margin-bottom:6px;">
                                <button type="submit"
                                    style="width:100%;padding:8px;border-radius:8px;border:1.5px solid #6b7280;background:#fff;color:#374151;font-weight:700;font-size:13px;cursor:pointer;">
                                    ✗ Từ chối
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- TABLE -->
    <div class="o-table-wrap">
        <?php if (empty($orders_data)): ?>
            <div class="o-empty"><i class="fas fa-box-open"></i>Không có đơn hàng nào phù hợp</div>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="o-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><div class="cb-custom" id="cbAll" onclick="toggleAll(this)"></div></th>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Sản phẩm của bạn</th>
                            <th>Doanh thu</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders_data as $o):
                            [$slbl, $scls] = $status_labels[$o['order_status']] ?? [strtoupper($o['order_status']), 'badge-pending'];
                            [$plbl, $pcls] = $pay_labels[$o['payment_status']] ?? ['?', 'pay-pending'];
                            $method_lbl  = $pay_method_labels[$o['payment_method']] ?? $o['payment_method'];
                            $method_cls  = 'method-' . $o['payment_method'];
                            $initials    = mb_strtoupper(mb_substr($o['buyer_name'] ?? 'U', 0, 1));
                            $my_items    = $items_map[$o['id']] ?? [];

                            // Tính doanh thu của seller trong đơn này
                            $seller_subtotal = array_sum(array_map(
                                fn($it) => ($it['unit_price'] - ($it['discount'] ?? 0)) * $it['quantity'],
                                $my_items
                            ));
                        ?>
                            <tr data-id="<?= $o['id'] ?>">
                                <td><div class="cb-custom row-cb" onclick="toggleRow(this)"></div></td>
                                <td>
                                    <div style="font-weight:700;color:var(--text);font-size:12.5px"><?= htmlspecialchars($o['order_code']) ?></div>
                                    <div style="font-size:11px;color:var(--muted)">#<?= $o['id'] ?></div>
                                </td>
                                <td>
                                    <div class="cust-cell">
                                        <div class="cust-av">
                                            <?php if ($o['buyer_avatar']): ?>
                                                <img src="<?= htmlspecialchars($o['buyer_avatar']) ?>" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <?= $initials ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="cust-name"><?= htmlspecialchars($o['buyer_name'] ?? 'N/A') ?></div>
                                            <div class="cust-email"><?= htmlspecialchars($o['buyer_email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (empty($my_items)): ?>
                                        <span style="font-size:12px;color:var(--muted);font-style:italic;">Không có SP</span>
                                    <?php else: ?>
                                        <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:4px;">
                                            <?= count($my_items) ?> sản phẩm
                                        </div>
                                        <div class="items-preview">
                                            <?php
                                            $preview_limit = 3;
                                            $shown = 0;
                                            foreach ($my_items as $it):
                                                if ($shown >= $preview_limit) break;
                                                $shown++;
                                            ?>
                                                <img class="item-thumb"
                                                     src="<?= htmlspecialchars($it['image_url'] ?? '') ?>"
                                                     title="<?= htmlspecialchars($it['product_name']) ?>"
                                                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2228%22 height=%2228%22><rect fill=%22%23eee%22 width=%2228%22 height=%2228%22/></svg>'">
                                            <?php endforeach; ?>
                                            <?php if (count($my_items) > $preview_limit): ?>
                                                <span class="item-count-badge">+<?= count($my_items) - $preview_limit ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:800;color:var(--purple1);font-size:14px;white-space:nowrap">
                                    <?= fmt_price_admin($seller_subtotal) ?>
                                    <?php if ($seller_subtotal != (float)$o['total_amount']): ?>
                                        <div style="font-size:10px;color:var(--muted);font-weight:400;margin-top:2px;">
                                            Đơn: <?= fmt_price_admin((float)$o['total_amount']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $pcls ?>"><?= $plbl ?></span><br>
                                    <span class="method-badge <?= $method_cls ?>" style="margin-top:4px"><?= $method_lbl ?></span>
                                </td>
                                <td><span class="badge <?= $scls ?>"><?= $slbl ?></span></td>
                                <td style="font-size:12px;color:var(--muted);white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($o['created_at'])) ?><br>
                                    <span style="font-size:11px"><?= date('H:i', strtotime($o['created_at'])) ?></span>
                                </td>
                                <td>
                                    <div class="act-btns">
                                        <button class="btn-view-o" onclick="openDetail(<?= $o['id'] ?>)"><i class="fas fa-eye"></i> Xem</button>
                                        <button class="btn-del-o" onclick="delOrder(<?= $o['id'] ?>, '<?= htmlspecialchars($o['order_code']) ?>')"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="o-pagination">
                <div class="o-page-info">
                    Hiển thị <?= ($page_num - 1) * $per_page + 1 ?>–<?= min($page_num * $per_page, $total_rows) ?> / <?= $total_rows ?> đơn hàng
                </div>
                <div class="o-page-btns">
                    <?php
                    $base_url = "/MantaMarket/sellers/index.php?page=orders"
                        . ($search        ? "&q="      . urlencode($search)        : '')
                        . ($filter_status ? "&status=" . urlencode($filter_status) : '')
                        . ($filter_pay    ? "&pay="    . urlencode($filter_pay)    : '');
                    for ($p = max(1, $page_num - 2); $p <= min($total_pages, $page_num + 2); $p++):
                    ?>
                        <a href="<?= $base_url ?>&p=<?= $p ?>#orders">
                            <div class="o-pg-btn <?= $p === $page_num ? 'active' : '' ?>"><?= $p ?></div>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page_num < $total_pages): ?>
                        <a href="<?= $base_url ?>&p=<?= $page_num + 1 ?>#orders">
                            <div class="o-pg-btn"><i class="fas fa-chevron-right" style="font-size:10px"></i></div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- DETAIL MODAL -->
<div class="o-modal-bg" id="oDetailModal">
    <div class="o-modal">
        <div class="o-modal-header">
            <div>
                <div class="o-modal-title" id="oModalTitle">Chi tiết đơn hàng</div>
                <div class="o-modal-sub" id="oModalSub"></div>
            </div>
            <button class="o-modal-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="o-modal-body" id="oModalBody"></div>
    </div>
</div>

<!-- HIDDEN FORMS -->
<form id="fDelete" method="POST" action="/MantaMarket/sellers/index.php?page=orders" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="fDeleteId">
</form>
<form id="fBulk" method="POST" action="/MantaMarket/sellers/index.php?page=orders" style="display:none">
    <input type="hidden" name="action" value="bulk_delete">
    <div id="fBulkIds"></div>
</form>

<script>
// ── DATA (items đã được lọc theo seller ở PHP) ──
const ORDER_DATA = <?= json_encode(array_map(function($o) use ($items_map, $status_labels, $pay_labels, $pay_method_labels) {
    $my_items = $items_map[$o['id']] ?? [];
    // Tính seller_subtotal từ items đã lọc
    $seller_subtotal = array_sum(array_map(
        fn($it) => ($it['unit_price'] - ($it['discount'] ?? 0)) * $it['quantity'],
        $my_items
    ));
    return [
        'id'               => $o['id'],
        'order_code'       => $o['order_code'],
        'order_status'     => $o['order_status'],
        'payment_method'   => $o['payment_method'],
        'total_amount'     => $o['total_amount'],      // tổng cả đơn
        'seller_subtotal'  => $seller_subtotal,         // chỉ hàng của seller này
        'subtotal'         => $o['subtotal'],
        'shipping_fee'     => $o['shipping_fee'],
        'discount_amount'  => $o['discount_amount'],
        'buyer_wallet'     => $o['buyer_wallet'],
        'refund_tx_hash'   => $o['refund_tx_hash'],
        'payment_status'   => $o['payment_status'],
        'buyer_name'       => $o['buyer_name'],
        'buyer_email'      => $o['buyer_email'],
        'buyer_avatar'     => $o['buyer_avatar'],
        'shop_name'        => $o['shop_name'],
        'shop_slug'        => $o['shop_slug'],
        'addr_line'        => $o['address_line'],
        'addr_ward'        => $o['ward'],
        'addr_district'    => $o['district'],
        'addr_province'    => $o['province'],
        'addr_phone'       => $o['addr_phone'],
        'note'             => $o['note'],
        'cancelled_reason' => $o['cancelled_reason'],
        'delivered_at'     => $o['delivered_at'],
        'created_at'       => $o['created_at'],
        'items'            => $my_items,                // CHỈ items của seller
    ];
}, $orders_data), JSON_UNESCAPED_UNICODE) ?>;

const STATUS_LABELS = <?= json_encode(array_map(fn($v) => $v[0], $status_labels)) ?>;
const PAY_LABELS    = <?= json_encode(array_map(fn($v) => $v[0], $pay_labels)) ?>;
const PAY_METHODS   = <?= json_encode($pay_method_labels) ?>;

// ── CHECKBOX ──
function toggleAll(el) {
    el.classList.toggle('checked');
    el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
    document.querySelectorAll('.row-cb').forEach(cb => {
        cb.className = 'cb-custom row-cb' + (el.classList.contains('checked') ? ' checked' : '');
        cb.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
    });
}
function toggleRow(el) {
    el.classList.toggle('checked');
    el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
}

// ── DELETE ──
function delOrder(id, code) {
    if (!confirm(`Xóa đơn hàng ${code}?\nThao tác này không thể hoàn tác!`)) return;
    document.getElementById('fDeleteId').value = id;
    document.getElementById('fDelete').submit();
}
function bulkDelete() {
    const checked = [...document.querySelectorAll('.row-cb.checked')];
    if (!checked.length) { alert('Vui lòng chọn đơn hàng cần xóa!'); return; }
    if (!confirm(`Xóa ${checked.length} đơn hàng đã chọn?\nThao tác này không thể hoàn tác!`)) return;
    const container = document.getElementById('fBulkIds');
    container.innerHTML = '';
    checked.forEach(cb => {
        const id = cb.closest('tr').dataset.id;
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        container.appendChild(inp);
    });
    document.getElementById('fBulk').submit();
}

// ── BNB REFUND ──
function buildRefundSection(o) {
    if (o.payment_status === 'refunded' && o.refund_tx_hash) {
        return `<div style="display:flex;align-items:center;gap:10px;padding:14px 16px;
                    background:#d1fae5;border-radius:10px;border:1.5px solid #6ee7b7;margin-bottom:14px;">
            <div><div style="color:#065f46;font-weight:700;font-size:13px;">Đã hoàn tiền BNB thành công</div>
            <a href="https://testnet.bscscan.com/tx/${o.refund_tx_hash}" target="_blank"
               style="font-size:11px;color:#047857;font-family:monospace;word-break:break-all;">
                🔗 ${o.refund_tx_hash}</a></div></div>`;
    }
    if (!(o.payment_status === 'paid' && o.order_status === 'cancelled')) {
        let msg = o.payment_status === 'refunded'
            ? '✅ Đơn hàng này đã được hoàn tiền.'
            : o.order_status !== 'cancelled'
                ? '⚠️ Chỉ hoàn tiền khi đơn hàng <strong>ĐÃ HỦY</strong>.'
                : '⚠️ Chỉ hoàn tiền khi trạng thái thanh toán là <strong>Đã TT</strong>.';
        return `<div style="padding:12px 16px;background:#f5f3ff;border-radius:10px;border:1.5px solid #e0e7ff;
                    display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <div style="font-size:13px;color:#5b21b6;">${msg}</div></div>`;
    }
    const walletDisplay = o.buyer_wallet
        ? `<code style="font-size:12px;font-family:monospace;color:#374151;word-break:break-all;">${o.buyer_wallet}</code>`
        : `<span style="color:#ef4444;font-size:12px;">⚠ Không có địa chỉ ví</span>
           <input type="text" id="fallback-wallet-${o.id}" placeholder="Nhập tay địa chỉ ví 0x..."
               style="display:block;margin-top:8px;width:100%;padding:8px 12px;
                      border:1.5px solid #fcd34d;border-radius:8px;font-size:12px;font-family:monospace;">`;
    return `<div id="bnb-refund-wrap-${o.id}">
        <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:16px;margin-bottom:14px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
                <div><div style="color:#92400e;font-size:11px;font-weight:700;margin-bottom:4px;">SỐ TIỀN HOÀN</div>
                    <div style="font-size:18px;font-weight:800;color:#7c3aed;">${Number(o.total_amount).toLocaleString('vi-VN')}đ</div></div>
                <div><div style="color:#92400e;font-size:11px;font-weight:700;margin-bottom:4px;">VÍ NGƯỜI MUA</div>
                    ${walletDisplay}</div>
            </div>
        </div>
        <div id="mm-status-${o.id}" style="display:none;"></div>
        <button id="mm-btn-${o.id}" onclick="startRefund(${o.id}, '${o.buyer_wallet || ''}', ${o.total_amount})"
            style="display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:10px;
                   border:none;cursor:pointer;font-weight:700;font-size:13px;
                   background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
            💎 Kết nối MetaMask & Hoàn tiền
        </button>
    </div>`;
}

// ── DETAIL MODAL ──
function openDetail(id) {
    const o = ORDER_DATA.find(x => x.id == id);
    if (!o) return;

    document.getElementById('oModalTitle').textContent = `Đơn hàng #${o.order_code}`;
    document.getElementById('oModalSub').textContent   = `Đặt lúc ${new Date(o.created_at).toLocaleString('vi-VN')}`;

    const fmt  = n => Number(n).toLocaleString('vi-VN') + 'đ';
    const addr = [o.addr_line, o.addr_ward, o.addr_district, o.addr_province].filter(Boolean).join(', ');

    // ── Tính doanh thu seller từ items đã lọc ──
    const sellerItems    = o.items || [];
    const sellerSubtotal = o.seller_subtotal || sellerItems.reduce((sum, it) =>
        sum + (it.unit_price - (it.discount || 0)) * it.quantity, 0);

    // ── Render items chỉ của seller ──
    let itemsHtml = '';
    if (sellerItems.length === 0) {
        itemsHtml = `<div class="no-items-box">
            <i class="fas fa-box-open"></i>
            Đơn hàng này không chứa sản phẩm nào của shop bạn.<br>
            <small style="font-size:11px;margin-top:4px;display:block;">Có thể sản phẩm đã bị xóa khỏi hệ thống.</small>
        </div>`;
    } else {
        itemsHtml = sellerItems.map(it => {
            const finalPrice  = it.unit_price - (it.discount || 0);
            const lineTotal   = finalPrice * it.quantity;
            const variants    = [it.color, it.size].filter(Boolean).join(' / ');
            const fallbackImg = `data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='52' height='52'><rect fill='%23eee' width='52' height='52'/></svg>`;
            return `
            <div class="o-item-row">
                <img class="o-item-img"
                     src="${it.image_url || ''}"
                     onerror="this.src='${fallbackImg}'"
                     alt="${it.product_name}">
                <div style="flex:1;min-width:0">
                    <div class="o-item-name">${it.product_name}</div>
                    <div class="o-item-meta">
                        ${variants ? `<span style="background:#f3f4f6;padding:1px 6px;border-radius:5px;">${variants}</span>` : ''}
                        <span style="margin-left:4px;">x${it.quantity}</span>
                    </div>
                </div>
                <div class="o-item-price">
                    ${it.discount > 0
                        ? `<span style="text-decoration:line-through;color:#9ca3af;font-size:11px">${fmt(it.unit_price)}</span><br>${fmt(finalPrice)}`
                        : fmt(it.unit_price)}
                    <div class="o-item-subtotal">= ${fmt(lineTotal)}</div>
                </div>
            </div>`;
        }).join('');
    }

    // ── Revenue summary box (chỉ hiện nếu có items) ──
    const revBoxHtml = sellerItems.length > 0 ? `
        <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1.5px solid #c4b5fd;
                    border-radius:10px;padding:12px 16px;margin-top:12px;
                    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-size:11px;color:#5b21b6;font-weight:700;margin-bottom:3px;">
                    <i class="fas fa-store" style="margin-right:4px"></i>DOANH THU CỦA BẠN (${sellerItems.length} sản phẩm)
                </div>
                <div style="font-size:20px;font-weight:800;color:var(--purple1);">${fmt(sellerSubtotal)}</div>
                ${sellerSubtotal != o.total_amount
                    ? `<div style="font-size:11px;color:#7c3aed;margin-top:2px;">Tổng cả đơn: ${fmt(o.total_amount)}</div>`
                    : ''}
            </div>
            <i class="fas fa-coins" style="font-size:24px;color:#c4b5fd;opacity:.7"></i>
        </div>` : '';

    document.getElementById('oModalBody').innerHTML = `
        <div class="o-section">
            <div class="o-section-title">Thông tin khách hàng</div>
            <div class="o-info-grid">
                <div class="o-info-item"><div class="o-info-key">Tên</div><div class="o-info-val">${o.buyer_name||'—'}</div></div>
                <div class="o-info-item"><div class="o-info-key">Email</div><div class="o-info-val">${o.buyer_email||'—'}</div></div>
                <div class="o-info-item"><div class="o-info-key">SĐT giao hàng</div><div class="o-info-val">${o.addr_phone||'—'}</div></div>
                <div class="o-info-item"><div class="o-info-key">Shop</div><div class="o-info-val">${o.shop_name||'—'}</div></div>
            </div>
            ${addr ? `<div class="o-info-item" style="margin-top:10px"><div class="o-info-key">Địa chỉ giao hàng</div><div class="o-info-val">${addr}</div></div>` : ''}
        </div>

        <div class="o-section">
            <div class="o-section-title">
                <i class="fas fa-store" style="margin-right:5px;color:var(--purple1)"></i>
                Sản phẩm của shop bạn (${sellerItems.length} loại)
            </div>
            ${itemsHtml}
            ${revBoxHtml}
        </div>

        <div class="o-section">
            <div class="o-section-title">Thông tin thanh toán đơn hàng</div>
            <div class="o-info-grid">
                <div class="o-info-item"><div class="o-info-key">Tiền hàng (cả đơn)</div><div class="o-info-val">${fmt(o.subtotal)}</div></div>
                <div class="o-info-item"><div class="o-info-key">Phí ship</div><div class="o-info-val">${fmt(o.shipping_fee)}</div></div>
                <div class="o-info-item"><div class="o-info-key">Giảm giá</div><div class="o-info-val" style="color:var(--green)">-${fmt(o.discount_amount)}</div></div>
                <div class="o-info-item"><div class="o-info-key">Tổng cộng (cả đơn)</div><div class="o-info-val" style="color:var(--purple1);font-size:16px">${fmt(o.total_amount)}</div></div>
                <div class="o-info-item"><div class="o-info-key">Phương thức</div><div class="o-info-val">${PAY_METHODS[o.payment_method]||o.payment_method}</div></div>
                <div class="o-info-item"><div class="o-info-key">TT Thanh toán</div><div class="o-info-val">${PAY_LABELS[o.payment_status]||o.payment_status}</div></div>
            </div>
            ${o.note ? `<div class="o-info-item" style="margin-top:10px"><div class="o-info-key">Ghi chú</div><div class="o-info-val">${o.note}</div></div>` : ''}
            ${o.cancelled_reason ? `<div class="o-info-item" style="margin-top:6px;border:1.5px solid #fee2e2"><div class="o-info-key" style="color:var(--red)">Lý do hủy</div><div class="o-info-val" style="color:var(--red)">${o.cancelled_reason}</div></div>` : ''}
            ${o.delivered_at ? `<div class="o-info-item" style="margin-top:6px"><div class="o-info-key">Giao hàng lúc</div><div class="o-info-val">${new Date(o.delivered_at).toLocaleString('vi-VN')}</div></div>` : ''}
        </div>

        <div class="o-section">
            <div class="o-section-title">Cập nhật trạng thái đơn hàng</div>
            <form method="POST" action="/MantaMarket/sellers/index.php?page=orders">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="${o.id}">
                <div class="o-status-form">
                    <select name="order_status" id="selStatus_${o.id}" onchange="toggleCancelReason(${o.id})">
                        ${Object.entries(STATUS_LABELS).map(([k,v]) =>
                            `<option value="${k}" ${o.order_status===k?'selected':''}>${v}</option>`
                        ).join('')}
                    </select>
                    <button type="submit" class="btn-update"><i class="fas fa-save"></i> Lưu trạng thái</button>
                </div>
                <div id="cancelReasonWrap_${o.id}" style="margin-top:8px;display:${o.order_status==='cancelled'?'block':'none'}">
                    <textarea name="cancelled_reason" placeholder="Nhập lý do hủy đơn...">${o.cancelled_reason||''}</textarea>
                </div>
            </form>

            <div class="o-section-title" style="margin-top:16px">Cập nhật trạng thái thanh toán</div>
            ${o.payment_method === 'bnb' ? buildRefundSection(o) : ''}
            <form method="POST" action="/MantaMarket/sellers/index.php?page=orders">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" name="id" value="${o.id}">
                <div class="o-status-form">
                    <select name="payment_status">
                        ${Object.entries(PAY_LABELS).map(([k,v]) =>
                            `<option value="${k}" ${o.payment_status===k?'selected':''}>${v}</option>`
                        ).join('')}
                    </select>
                    <button type="submit" class="btn-update"><i class="fas fa-save"></i> Lưu thanh toán</button>
                </div>
            </form>
        </div>`;

    document.getElementById('oDetailModal').classList.add('open');
}

function toggleCancelReason(id) {
    const sel  = document.getElementById(`selStatus_${id}`);
    const wrap = document.getElementById(`cancelReasonWrap_${id}`);
    if (wrap) wrap.style.display = sel.value === 'cancelled' ? 'block' : 'none';
}
function closeDetail() { document.getElementById('oDetailModal').classList.remove('open'); }

// ── AJAX SUBMIT FORM TRONG MODAL ──
document.getElementById('oDetailModal').addEventListener('submit', async function(e) {
    const form = e.target;
    const actionVal = form.querySelector('input[name="action"]')?.value;
    if (!['update_status', 'update_payment'].includes(actionVal)) return;
    e.preventDefault();
    const btn = form.querySelector('.btn-update');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        await fetch('/MantaMarket/sellers/index.php?page=orders', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        });
        const id  = parseInt(form.querySelector('input[name="id"]').value);
        const ord = ORDER_DATA.find(x => x.id == id);
        if (ord) {
            if (actionVal === 'update_status')  ord.order_status   = form.querySelector('select[name="order_status"]').value;
            if (actionVal === 'update_payment') ord.payment_status = form.querySelector('select[name="payment_status"]').value;
            updateRowBadge(id, ord);
        }
        showOrderToast('✅ Cập nhật thành công!', 'success');
    } catch(err) {
        showOrderToast('❌ Lỗi: ' + err.message, 'error');
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
});

function showOrderToast(msg, type) {
    let t = document.getElementById('oToast');
    if (!t) { t = document.createElement('div'); t.id = 'oToast'; document.body.appendChild(t); }
    t.className = `o-toast ${type}`;
    t.innerHTML = msg;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.remove(), 3000);
}

function updateRowBadge(id, ord) {
    const SC = {pending:'badge-pending',confirmed:'badge-confirmed',processing:'badge-processing',
                shipped:'badge-shipped',delivered:'badge-delivered',cancelled:'badge-cancelled',returned:'badge-returned'};
    const PC = {pending:'pay-pending',paid:'pay-paid',failed:'pay-failed',refunded:'pay-refunded'};
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    const tds         = row.querySelectorAll('td');
    const payBadge    = tds[5]?.querySelector('.badge');
    const statusBadge = tds[6]?.querySelector('.badge');
    if (payBadge)    { payBadge.className = `badge ${PC[ord.payment_status]||'pay-pending'}`; payBadge.textContent = PAY_LABELS[ord.payment_status]||''; }
    if (statusBadge) { statusBadge.className = `badge ${SC[ord.order_status]||'badge-pending'}`; statusBadge.textContent = STATUS_LABELS[ord.order_status]||''; }
}

// ── AJAX DUYỆT / TỪ CHỐI HỦY ──
async function ajaxCancelAction(e, form, confirmMsg) {
    e.preventDefault();
    if (!confirm(confirmMsg)) return false;
    const btn = form.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const res  = await fetch('/MantaMarket/sellers/index.php?page=orders', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        });
        const text = await res.text();
        let data = { success: true };
        try { data = JSON.parse(text); } catch {}
        if (data.success) {
            form.closest('[style*="border:1.5px solid #fecaca"]')?.remove();
            if (!document.querySelectorAll('[style*="border:1.5px solid #fecaca"]').length) {
                document.querySelector('[style*="border:2px solid #fee2e2"]')?.remove();
            }
            showOrderToast('✅ Thao tác thành công!', 'success');
        }
    } catch(err) {
        alert('Lỗi kết nối: ' + err.message);
        btn.disabled = false; btn.innerHTML = orig;
    }
    return false;
}

// ── METAMASK REFUND ──
async function fetchBNBRate() {
    try {
        const r1 = await fetch('https://api.binance.com/api/v3/ticker/price?symbol=BNBUSDT');
        const d1 = await r1.json();
        let usdVnd = 25400;
        try { const r2 = await fetch('https://api.exchangerate-api.com/v4/latest/USD'); const d2 = await r2.json(); usdVnd = d2.rates?.VND || 25400; } catch {}
        return parseFloat(d1.price) * usdVnd;
    } catch { return 14000000; }
}
function mmStatus(id, html, type = 'info') {
    const el = document.getElementById(`mm-status-${id}`);
    if (!el) return;
    const styles = {
        info:    'background:#eff6ff;border:1.5px solid #bfdbfe;color:#1d4ed8;',
        success: 'background:#d1fae5;border:1.5px solid #6ee7b7;color:#065f46;',
        error:   'background:#fee2e2;border:1.5px solid #fca5a5;color:#991b1b;',
        loading: 'background:#f5f3ff;border:1.5px solid #c4b5fd;color:#5b21b6;',
    };
    el.style.cssText = `display:block;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px;line-height:1.6;${styles[type]||styles.info}`;
    el.innerHTML = html;
}
async function startRefund(orderId, buyerWallet, totalVnd) {
    let toWallet = buyerWallet;
    if (!toWallet) { const inp = document.getElementById(`fallback-wallet-${orderId}`); toWallet = inp ? inp.value.trim() : ''; }
    if (!toWallet || !/^0x[0-9a-fA-F]{40}$/.test(toWallet)) { mmStatus(orderId, '❌ Địa chỉ ví không hợp lệ!', 'error'); return; }
    if (!window.ethereum) { mmStatus(orderId, '❌ MetaMask chưa cài đặt!', 'error'); return; }
    const btn = document.getElementById(`mm-btn-${orderId}`);
    if (btn) btn.disabled = true;
    try {
        mmStatus(orderId, '⏳ Đang kết nối MetaMask...', 'loading');
        const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
        const adminWallet = accounts[0];
        try { await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: '0x61' }] }); }
        catch (sw) {
            if (sw.code === 4902) await window.ethereum.request({ method: 'wallet_addEthereumChain', params: [{ chainId:'0x61', chainName:'BNB Smart Chain Testnet', nativeCurrency:{ name:'tBNB', symbol:'tBNB', decimals:18 }, rpcUrls:['https://data-seed-prebsc-1-s1.binance.org:8545/'], blockExplorerUrls:['https://testnet.bscscan.com/'] }] });
            else throw sw;
        }
        mmStatus(orderId, '⏳ Đang lấy tỷ giá BNB...', 'loading');
        const bnbPerVnd = await fetchBNBRate();
        const bnbAmount = totalVnd / bnbPerVnd;
        const bnbDisplay = bnbAmount.toFixed(6);
        const weiHex = '0x' + BigInt(Math.round(bnbAmount * 1e18)).toString(16);
        if (!confirm(`XÁC NHẬN HOÀN TIỀN\nSố tiền: ${Number(totalVnd).toLocaleString('vi-VN')}đ\nTương đương: ${bnbDisplay} tBNB\nGửi đến: ${toWallet}`)) {
            mmStatus(orderId, '⚠️ Đã hủy.', 'info'); if (btn) btn.disabled = false; return;
        }
        mmStatus(orderId, '⏳ Đang chờ ký trong MetaMask...', 'loading');
        const txHash = await window.ethereum.request({ method: 'eth_sendTransaction', params: [{ from: adminWallet, to: toWallet, value: weiHex, gas: '0x5208' }] });
        mmStatus(orderId, `✅ Giao dịch thành công! <a href="https://testnet.bscscan.com/tx/${txHash}" target="_blank" style="color:inherit;font-size:11px;font-family:monospace;">🔗 ${txHash.slice(0,20)}...</a>`, 'success');
        const fd = new FormData();
        fd.append('action','update_payment'); fd.append('id', orderId);
        fd.append('payment_status','refunded'); fd.append('refund_tx_hash', txHash); fd.append('refund_bnb', bnbDisplay);
        await fetch('/MantaMarket/sellers/index.php?page=orders', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
    } catch (err) {
        mmStatus(orderId, err.code === 4001 ? '⚠️ Bạn đã từ chối ký giao dịch.' : `❌ Lỗi: ${err.message}`, 'error');
        if (btn) btn.disabled = false;
    }
}

document.getElementById('oDetailModal').addEventListener('click', function(e) {
    if (e.target === this) closeDetail();
});
</script>