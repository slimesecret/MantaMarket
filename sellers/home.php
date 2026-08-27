<?php
$identity = new app_Libs_UserIdentity();
if (!$identity->isLogin() || $_SESSION["role"] != "seller") {
    header("Location: login.php");
    exit();
}
$router  = new app_Libs_Router();
$danhMuc = new app_Models_Categories();
$page    = $_GET['page'] ?? '';
// ── DB + Seller hiện tại ──
$db = new app_Libs_DbConnection();

$sellerRow = $db->query(
    "SELECT id FROM sellers WHERE user_id = ?",
    [$_SESSION['userId']]
)->fetch();
$sellerId = $sellerRow ? (int)$sellerRow['id'] : 0;












if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $router->getPOST('action');
    if (in_array($action, ['insert', 'update', 'delete', 'bulk_delete', 'bulk_toggle'])) {
        if ($page === 'products') include 'products/index.php';
        elseif ($page === 'orders')   include 'orders/index.php';
        elseif ($page === 'reviews')  include 'reviews/index.php';
        else    include 'brands/index.php';
        exit();
    }
}

$opendm = $danhMuc->buildQueryParams([
    "select" => "*",
    "other"  => "ORDER BY sort_order ASC"
])->select();

// ── DB + Seller hiện tại ──
$db = new app_Libs_DbConnection();

$sellerRow = $db->query(
    "SELECT id FROM sellers WHERE user_id = ?",
    [$_SESSION['userId']]
)->fetch();
$sellerId = $sellerRow ? (int)$sellerRow['id'] : 0;

// ── Dữ liệu biểu đồ: 7 ngày gần nhất ──
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartData['labels'][] = date('d/m', strtotime($date));

    $row = $db->query(
        "SELECT COALESCE(SUM(total_amount), 0) AS total
         FROM orders
         WHERE DATE(created_at) = ? AND order_status = 'delivered' AND seller_id = ?",
        [$date, $sellerId]
    )->fetch();
    $chartData['revenue'][] = (float)$row['total'];

    $row2 = $db->query(
        "SELECT COALESCE(SUM(shipping_fee), 0) AS total
         FROM orders
         WHERE DATE(created_at) = ? AND order_status != 'cancelled' AND seller_id = ?",
        [$date, $sellerId]
    )->fetch();
    $chartData['shipping'][] = (float)$row2['total'];

    $row3 = $db->query(
        "SELECT COALESCE(SUM(total_amount), 0) AS total
         FROM orders
         WHERE DATE(created_at) = ? AND payment_status = 'refunded' AND seller_id = ?",
        [$date, $sellerId]
    )->fetch();
    $chartData['refund'][] = (float)$row3['total'];
}

// ── Chart data AJAX ──
if (isset($_GET['chart_range'])) {
    $days = (int)($_GET['chart_range'] ?? 7);
    $days = in_array($days, [1, 3, 7, 30]) ? $days : 7;
    $result = ['labels' => [], 'revenue' => [], 'shipping' => [], 'refund' => []];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $result['labels'][] = date('d/m', strtotime($date));

        $r = $db->query(
            "SELECT COALESCE(SUM(total_amount),0) AS v FROM orders
             WHERE DATE(created_at)=? AND order_status='delivered' AND seller_id=?",
            [$date, $sellerId]
        )->fetch();
        $result['revenue'][] = (float)$r['v'];

        $r2 = $db->query(
            "SELECT COALESCE(SUM(shipping_fee),0) AS v FROM orders
             WHERE DATE(created_at)=? AND order_status!='cancelled' AND seller_id=?",
            [$date, $sellerId]
        )->fetch();
        $result['shipping'][] = (float)$r2['v'];

        $r3 = $db->query(
            "SELECT COALESCE(SUM(total_amount),0) AS v FROM orders
             WHERE DATE(created_at)=? AND payment_status='refunded' AND seller_id=?",
            [$date, $sellerId]
        )->fetch();
        $result['refund'][] = (float)$r3['v'];
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// ── AJAX: Analytics dashboard data ──
if (isset($_GET['analytics_data'])) {
    $section = $_GET['analytics_data'];
    $days    = (int)($_GET['days'] ?? 30);
    $days    = in_array($days, [1, 3, 7, 30]) ? $days : 30;
    $dateFilter = "DATE_SUB(NOW(), INTERVAL $days DAY)";

    // --- Section 2: Top sản phẩm & tỷ lệ huỷ của shop này ---
    if ($section === 'sellers') {
        // Doanh thu top 5 sản phẩm của seller
        $top5 = $db->query(
            "SELECT p.name AS shop_name,
                    COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS revenue
             FROM products p
             JOIN order_items oi ON oi.product_id = p.id
             JOIN orders o ON o.id = oi.order_id
                 AND o.order_status = 'delivered'
                 AND o.created_at >= {$dateFilter}
             WHERE p.seller_id = {$sellerId}
             GROUP BY p.id, p.name
             ORDER BY revenue DESC LIMIT 5",
            []
        )->fetchAll();

        // Tỷ lệ huỷ/hoàn theo từng sản phẩm
        $cancelRates = $db->query(
            "SELECT p.name AS shop_name,
                    ROUND(SUM(CASE WHEN o.order_status IN ('cancelled','returned') THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(o.id),0),1) AS cancel_rate,
                    ROUND(SUM(CASE WHEN o.payment_status = 'refunded' THEN 1 ELSE 0 END)*100.0/NULLIF(COUNT(o.id),0),1) AS refund_rate
             FROM products p
             JOIN order_items oi ON oi.product_id = p.id
             JOIN orders o ON o.id = oi.order_id AND o.created_at >= {$dateFilter}
             WHERE p.seller_id = {$sellerId}
             GROUP BY p.id, p.name
             HAVING COUNT(o.id) > 0
             ORDER BY cancel_rate DESC LIMIT 5",
            []
        )->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['top5' => $top5, 'cancelRates' => $cancelRates]);
        exit();
    }

    // --- Section 3: Sản phẩm & tồn kho của seller ---
    if ($section === 'products') {
        $topProducts = $db->query(
            "SELECT p.name,
                    COALESCE(SUM(oi.quantity), 0) AS sold_count
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
             LEFT JOIN orders o ON o.id = oi.order_id
                 AND o.order_status = 'delivered'
                 AND o.created_at >= {$dateFilter}
             WHERE p.status = 'active' AND p.seller_id = {$sellerId}
             GROUP BY p.id, p.name
             ORDER BY sold_count DESC LIMIT 6",
            []
        )->fetchAll();

        $lowStock = $db->query(
            "SELECT pv.sku, p.name AS product_name, pv.color, pv.size, pv.stock_quantity
             FROM product_variants pv
             JOIN products p ON p.id = pv.product_id
             WHERE pv.stock_quantity <= pv.low_stock_alert
               AND pv.is_active = 1
               AND p.seller_id = {$sellerId}
             ORDER BY pv.stock_quantity ASC LIMIT 8",
            []
        )->fetchAll();

        $catStats = $db->query(
            "SELECT c.name AS cat_name,
                    COUNT(DISTINCT pv.sku) AS sku_count,
                    COALESCE(SUM(o.total_amount),0) AS revenue
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.seller_id = {$sellerId}
             LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
             LEFT JOIN order_items oi ON oi.product_id = p.id
             LEFT JOIN orders o ON o.id = oi.order_id
                 AND o.order_status = 'delivered'
                 AND o.created_at >= {$dateFilter}
             GROUP BY c.id, c.name
             ORDER BY sku_count DESC",
            []
        )->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['topProducts' => $topProducts, 'lowStock' => $lowStock, 'catStats' => $catStats]);
        exit();
    }

    // --- Section 4: Người dùng mua hàng tại shop ---
    if ($section === 'users') {
        $months = [];
        if ($days <= 7) {
            for ($i = $days - 1; $i >= 0; $i--) {
                $date  = date('Y-m-d', strtotime("-$i days"));
                $label = date('d/m', strtotime($date));
                $row   = $db->query(
                    "SELECT
                        SUM(u.provider='local')    AS local_cnt,
                        SUM(u.provider='google')   AS google_cnt,
                        SUM(u.provider='facebook') AS facebook_cnt
                     FROM orders o
                     JOIN users u ON u.id = o.user_id
                     WHERE DATE(o.created_at) = ? AND o.seller_id = ?",
                    [$date, $sellerId]
                )->fetch();
                $months[] = [
                    'label'    => $label,
                    'local'    => (int)$row['local_cnt'],
                    'google'   => (int)$row['google_cnt'],
                    'facebook' => (int)$row['facebook_cnt'],
                ];
            }
        } else {
            for ($i = 5; $i >= 0; $i--) {
                $y     = date('Y', strtotime("-$i months"));
                $m     = date('m', strtotime("-$i months"));
                $label = 'T' . (int)$m;
                $row   = $db->query(
                    "SELECT
                        SUM(u.provider='local')    AS local_cnt,
                        SUM(u.provider='google')   AS google_cnt,
                        SUM(u.provider='facebook') AS facebook_cnt
                     FROM orders o
                     JOIN users u ON u.id = o.user_id
                     WHERE YEAR(o.created_at)=? AND MONTH(o.created_at)=? AND o.seller_id=?",
                    [$y, $m, $sellerId]
                )->fetch();
                $months[] = [
                    'label'    => $label,
                    'local'    => (int)$row['local_cnt'],
                    'google'   => (int)$row['google_cnt'],
                    'facebook' => (int)$row['facebook_cnt'],
                ];
            }
        }

        $rfm = $db->query(
            "SELECT
                SUM(CASE WHEN cnt=1 THEN 1 ELSE 0 END) AS one_order,
                SUM(CASE WHEN cnt BETWEEN 2 AND 5 THEN 1 ELSE 0 END) AS few_orders,
                SUM(CASE WHEN cnt>5 THEN 1 ELSE 0 END) AS many_orders
             FROM (
                SELECT user_id, COUNT(*) AS cnt
                FROM orders
                WHERE created_at >= {$dateFilter} AND seller_id = {$sellerId}
                GROUP BY user_id
             ) t",
            []
        )->fetch();

        header('Content-Type: application/json');
        echo json_encode(['months' => $months, 'rfm' => $rfm]);
        exit();
    }

    // --- Section 5: Vận chuyển của seller ---
    if ($section === 'shipping') {
        $pipeline = $db->query(
            "SELECT
                SUM(sh.status='waiting_pickup')                           AS waiting,
                SUM(sh.status='picked_up')                                AS picked,
                SUM(sh.status IN ('in_transit','out_for_delivery'))       AS transit,
                SUM(sh.status='delivered')                                AS delivered,
                SUM(sh.status='failed')                                   AS failed
             FROM shipping sh
             JOIN orders o ON o.id = sh.order_id
             WHERE sh.created_at >= {$dateFilter} AND o.seller_id = {$sellerId}",
            []
        )->fetch();

        $providers = $db->query(
            "SELECT sh.provider,
                    ROUND(SUM(sh.status='delivered')*100.0/NULLIF(COUNT(*),0),1) AS success_rate
             FROM shipping sh
             JOIN orders o ON o.id = sh.order_id
             WHERE sh.created_at >= {$dateFilter} AND o.seller_id = {$sellerId}
             GROUP BY sh.provider
             ORDER BY success_rate DESC LIMIT 5",
            []
        )->fetchAll();

        $cancelReq = $db->query(
            "SELECT
                SUM(cr.status='pending')  AS pending,
                SUM(cr.status='approved') AS approved,
                SUM(cr.status='rejected') AS rejected
             FROM cancel_requests cr
             JOIN orders o ON o.id = cr.order_id
             WHERE cr.created_at >= {$dateFilter} AND o.seller_id = {$sellerId}",
            []
        )->fetch();

        header('Content-Type: application/json');
        echo json_encode(['pipeline' => $pipeline, 'providers' => $providers, 'cancelReq' => $cancelReq]);
        exit();
    }

    // --- Section 6: Nền tảng — sản phẩm & đánh giá của seller ---
    if ($section === 'platform') {
        $prodStatus = $db->query(
            "SELECT status, COUNT(*) AS cnt FROM products
             WHERE seller_id = {$sellerId} AND created_at >= {$dateFilter}
             GROUP BY status",
            []
        )->fetchAll();

        $ratings = $db->query(
            "SELECT r.rating, COUNT(*) AS cnt
             FROM reviews r
             JOIN products p ON p.id = r.product_id
             WHERE r.is_approved = 1
               AND r.created_at >= {$dateFilter}
               AND p.seller_id = {$sellerId}
             GROUP BY r.rating ORDER BY r.rating",
            []
        )->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['prodStatus' => $prodStatus, 'ratings' => $ratings]);
        exit();
    }

    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// ── Đơn hàng mới nhất (chỉ của seller, có phân trang) ──
if (isset($_GET['recent_orders'])) {
    $page_num = max(1, (int)($_GET['recent_orders'] ?? 1));
    $limit    = 6;
    $offset   = ($page_num - 1) * $limit;

    $total = $db->query(
        "SELECT COUNT(*) AS cnt FROM orders WHERE seller_id = ?",
        [$sellerId]
    )->fetch()['cnt'];

    $rows = $db->query(
        "SELECT o.id, o.order_code, o.total_amount, o.payment_method,
                o.order_status, o.created_at,
                u.full_name,
                ua.province
         FROM orders o
         JOIN users u ON u.id = o.user_id
         LEFT JOIN user_addresses ua ON ua.id = o.address_id
         WHERE o.seller_id = {$sellerId}
         ORDER BY o.created_at DESC
         LIMIT {$limit} OFFSET {$offset}",
        []
    )->fetchAll();

    $paymentMap = [
        'cod'           => 'Tiền mặt',
        'bank_transfer' => 'Chuyển khoản',
        'momo'          => 'MoMo',
        'vnpay'         => 'VNPay',
        'zalopay'       => 'ZaloPay',
        'credit_card'   => 'Thẻ tín dụng',
        'bnb'           => 'BNB',
    ];
    $statusMap = [
        'pending'    => ['Chờ xác nhận', 'pending'],
        'confirmed'  => ['Đã xác nhận',  'processing'],
        'processing' => ['Đang xử lý',   'processing'],
        'shipped'    => ['Đang giao',     'processing'],
        'delivered'  => ['Hoàn thành',   'done'],
        'cancelled'  => ['Đã hủy',       'cancelled'],
        'returned'   => ['Hoàn trả',     'cancelled'],
    ];

    $result = [];
    foreach ($rows as $r) {
        [$statusLabel, $statusClass] = $statusMap[$r['order_status']] ?? [$r['order_status'], ''];
        $result[] = [
            'id'       => $r['order_code'] ?: '#' . $r['id'],
            'customer' => $r['full_name'] ?? 'N/A',
            'amount'   => number_format($r['total_amount'], 0, ',', '.') . 'đ',
            'payment'  => $paymentMap[$r['payment_method']] ?? $r['payment_method'],
            'province' => $r['province'] ?? '—',
            'status'   => $statusLabel,
            'class'    => $statusClass,
            'date'     => date('d/m/Y', strtotime($r['created_at'])),
        ];
    }
    header('Content-Type: application/json');
    echo json_encode(['rows' => $result, 'total' => (int)$total, 'limit' => $limit]);
    exit();
}

// ── Thống kê tổng quan (chỉ của seller) ──
$statProducts = $db->query(
    "SELECT COUNT(*) AS cnt FROM products WHERE seller_id = ?",
    [$sellerId]
)->fetch()['cnt'];

$statOrders = $db->query(
    "SELECT COUNT(*) AS cnt FROM orders WHERE seller_id = ?",
    [$sellerId]
)->fetch()['cnt'];

$statVariants = $db->query(
    "SELECT COUNT(*) AS cnt
     FROM product_variants pv
     JOIN products p ON p.id = pv.product_id
     WHERE p.seller_id = ?",
    [$sellerId]
)->fetch()['cnt'];

$statRevenue = $db->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders
     WHERE seller_id = ? AND order_status = 'delivered'
     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    [$sellerId]
)->fetch()['total'];

$statRevenuePrev = $db->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders
     WHERE seller_id = ? AND order_status = 'delivered'
     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    [$sellerId]
)->fetch()['total'];

$statOrdersPrev = $db->query(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE seller_id = ?
     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    [$sellerId]
)->fetch()['cnt'];

function calcChange($current, $prev) {
    if ($prev == 0) return $current > 0 ? 100 : 0;
    return round(($current - $prev) / $prev * 100, 1);
}
$changeOrders  = calcChange($statOrders,  $statOrdersPrev);
$changeRevenue = calcChange($statRevenue, $statRevenuePrev);

function fmtMoney($val) {
    if ($val >= 1_000_000_000) return round($val / 1_000_000_000, 1) . 'B';
    if ($val >= 1_000_000)     return round($val / 1_000_000, 1) . 'tr';
    if ($val >= 1_000)         return round($val / 1_000) . 'k';
    return number_format($val) . 'đ';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MANTA</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= asset('css/ad_home.css') ?>">
    <style>
        /* ── Revenue chart range tabs ── */
        .chart-range-tabs {
            display: flex; gap: 4px; background: #f1f5f9;
            border-radius: 8px; padding: 3px; margin-right: 8px;
        }
        .range-tab {
            border: none; background: transparent; padding: 4px 10px;
            border-radius: 6px; font-size: 12px; font-weight: 500;
            color: #64748b; cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .range-tab.active {
            background: #fff; color: #6366f1;
            box-shadow: 0 1px 4px rgba(0,0,0,.1); font-weight: 600;
        }
        .range-tab:hover:not(.active) { color: #334155; background: rgba(255,255,255,.6); }

        /* ── Analytics card header with range tabs ── */
        .analytics-card-header {
            display: flex; align-items: flex-start;
            justify-content: space-between; gap: 8px;
            flex-wrap: wrap; margin-bottom: 3px;
        }
        .analytics-range-tabs {
            display: flex; gap: 4px; background: #f1f5f9;
            border-radius: 8px; padding: 3px; flex-shrink: 0;
        }
        .analytics-range-tab {
            border: none; background: transparent; padding: 3px 9px;
            border-radius: 6px; font-size: 11px; font-weight: 500;
            color: #64748b; cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .analytics-range-tab.active {
            background: #fff; color: #6366f1;
            box-shadow: 0 1px 4px rgba(0,0,0,.1); font-weight: 600;
        }
        .analytics-range-tab:hover:not(.active) { color: #334155; background: rgba(255,255,255,.6); }

        /* ── Analytics sections ── */
        .analytics-section-heading {
            display: flex; align-items: center; gap: 10px;
            padding: 22px 0 14px;
            font-size: 12px; font-weight: 700;
            color: #6b7280; text-transform: uppercase; letter-spacing: 0.7px;
            border-top: 1.5px solid rgba(124,58,237,.1); margin-top: 20px;
        }
        .analytics-section-heading i { font-size: 14px; color: #112D60; }

        .analytics-two-col {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;
        }
        .analytics-one-col { margin-bottom: 16px; }

        /* Top 5 list */
        .top5-list { list-style: none; }
        .top5-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 0; border-bottom: 1px solid rgba(124,58,237,.08);
        }
        .top5-item:last-child { border-bottom: none; }
        .top5-rank {
            width: 22px; height: 22px; border-radius: 50%;
            background: linear-gradient(135deg, #112D60, #DD83E0);
            color: #fff; font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .top5-bar-wrap { flex: 1; }
        .top5-name { font-size: 13px; font-weight: 600; color: #1e1b4b; margin-bottom: 4px; }
        .top5-bar-bg { background: #f1f5f9; border-radius: 4px; height: 5px; }
        .top5-bar-fill {
            height: 5px; border-radius: 4px;
            background: linear-gradient(90deg, #112D60, #DD83E0);
            transition: width .6s ease;
        }
        .top5-val { font-size: 13px; font-weight: 700; color: #1e1b4b; white-space: nowrap; }

        /* Low stock list */
        .low-stock-list { list-style: none; }
        .low-stock-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0; border-bottom: 1px solid rgba(124,58,237,.08); font-size: 13px;
        }
        .low-stock-item:last-child { border-bottom: none; }
        .badge-xs {
            padding: 3px 9px; border-radius: 20px;
            font-size: 11px; font-weight: 700; white-space: nowrap; flex-shrink: 0;
        }
        .badge-out { background: #fee2e2; color: #dc2626; }
        .badge-low { background: #fef3c7; color: #d97706; }
        .stock-name { flex: 1; color: #1e1b4b; }
        .stock-qty  { font-weight: 700; color: #1e1b4b; }

        /* Pipeline */
        .pipeline-row {
            display: flex; gap: 0; margin: 10px 0 4px; align-items: stretch;
        }
        .pipeline-step { flex: 1; text-align: center; position: relative; }
        .pipeline-step + .pipeline-step::before {
            content: ''; position: absolute; left: 0; top: 20px;
            width: 100%; height: 3px; background: #e5e7eb; z-index: 0;
        }
        .pip-val { font-size: 20px; font-weight: 800; line-height: 1; position: relative; z-index: 1; }
        .pip-label { font-size: 11px; color: #6b7280; margin-top: 5px; }
        .pip-bar { height: 3px; border-radius: 2px; margin: 5px auto 0; width: 55%; }

        /* Donut layout */
        .donut-wrap { display: flex; align-items: center; gap: 20px; }
        .donut-legend { flex: 1; }
        .donut-legend-item {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 9px; font-size: 13px;
        }
        .donut-dot { width: 11px; height: 11px; border-radius: 3px; flex-shrink: 0; }
        .donut-pct { font-weight: 700; color: #1e1b4b; margin-left: auto; }

        /* Legend row */
        .chart-legend-row {
            display: flex; flex-wrap: wrap; gap: 14px;
            margin-bottom: 10px; font-size: 12px; color: #6b7280;
        }
        .chart-legend-row span { display: flex; align-items: center; gap: 5px; }
        .legend-sq { width: 10px; height: 10px; border-radius: 2px; }

        /* Analytics card */
        .analytics-card {
            background: #ffffff; border-radius: 18px; padding: 20px 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
            border: 1.5px solid rgba(124,58,237,.1);
        }
        .analytics-card-title { font-size: 15px; font-weight: 700; color: #1e1b4b; margin-bottom: 3px; }
        .analytics-card-sub   { font-size: 12px; color: #6b7280; margin-bottom: 14px; }

        .analytics-loader {
            display: flex; align-items: center; justify-content: center;
            height: 120px; color: #9ca3af; font-size: 13px; gap: 8px;
        }
        .analytics-loader i { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 700px) {
            .analytics-two-col { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="logo">
            <img src="../img/footer-logo.png" alt="Manta Marketplace Logo" />
        </div>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="headerSearch" placeholder="Tìm kiếm...">
        </div>
        <div class="header-right">
            <div class="user-dropdown">
                <div class="user-trigger">
                    <img src="<?= $_SESSION["avatar"] ?>" alt="avatar" class="user-avatar" />
                    <span><?= $_SESSION["username"] ?? "Tài khoản" ?></span>
                </div>
                <div class="dropdown-menu">
                    <a href="/MantaMarket/admin/logout.php">Đăng xuất</a>
                </div>
            </div>
        </div>
    </header>

    <div class="layout">
        <aside class="sidebar">
            <div class="nav-item active" data-page="dashboard"><i class="fas fa-th-large nav-icon"></i><span class="nav-text">Trang chủ</span></div>
            <div class="nav-item" data-page="products"><i class="fas fa-box nav-icon"></i><span class="nav-text">Sản phẩm</span></div>
            <div class="nav-item" data-page="orders"><i class="fas fa-shopping-bag nav-icon"></i><span class="nav-text">Đơn hàng</span></div>
            <div class="nav-item" data-page="brands"><i class="fas fa-tag nav-icon"></i><span class="nav-text">Thương hiệu</span></div>
            <div class="nav-item" data-page="reviews"><i class="fas fa-comment-dots nav-icon"></i><span class="nav-text">Phản hồi khách hàng</span></div>
        </aside>

        <main class="main">
            <!-- PAGE: DASHBOARD -->
            <div class="page active" id="page-dashboard">
                <div class="page-header">
                    <h1 class="page-title">Dashboard</h1>
                    <div class="page-actions">
                        <button class="btn-action">SUN <i class="fas fa-chevron-down"></i></button>
                        <button class="btn-action"><i class="fab fa-youtube"></i></button>
                        <button class="btn-action"><i class="fas fa-list"></i></button>
                        <button class="btn-action"><i class="fas fa-bell"></i></button>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card purple">
                        <div class="stat-icon"><i class="fas fa-cube"></i></div>
                        <div class="stat-label">Sản phẩm</div>
                        <div class="stat-value"><?= number_format($statProducts) ?></div>
                        <div class="stat-change"><i class="fas fa-minus"></i> Tổng sản phẩm của shop</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="stat-label">Đơn hàng</div>
                        <div class="stat-value"><?= number_format($statOrders) ?></div>
                        <div class="stat-change <?= $changeOrders >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $changeOrders >= 0 ? 'up' : 'down' ?>"></i>
                            <?= ($changeOrders >= 0 ? '+' : '') . $changeOrders ?>% so tuần trước
                        </div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="stat-label">Biến thể</div>
                        <div class="stat-value"><?= number_format($statVariants) ?></div>
                        <div class="stat-change"><i class="fas fa-minus"></i> Tổng variants của shop</div>
                    </div>
                    <div class="stat-card teal">
                        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-label">Doanh thu tuần</div>
                        <div class="stat-value"><?= fmtMoney($statRevenue) ?></div>
                        <div class="stat-change <?= $changeRevenue >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $changeRevenue >= 0 ? 'up' : 'down' ?>"></i>
                            <?= ($changeRevenue >= 0 ? '+' : '') . $changeRevenue ?>% so tuần trước
                        </div>
                    </div>
                </div>

                <div class="bottom-grid">
                    <div>
                        <!-- Revenue Chart -->
                        <div class="card">
                            <div class="card-header">
                                <span class="card-title">Doanh thu theo tuần</span>
                                <div class="card-actions">
                                    <div class="chart-range-tabs">
                                        <button class="range-tab" data-range="1">1N</button>
                                        <button class="range-tab" data-range="3">3N</button>
                                        <button class="range-tab active" data-range="7">1 Tuần</button>
                                        <button class="range-tab" data-range="30">1 Tháng</button>
                                    </div>
                                    <button class="icon-btn"><i class="fas fa-list"></i></button>
                                    <button class="icon-btn"><i class="fas fa-redo"></i></button>
                                    <button class="icon-btn"><i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                            <canvas id="revenueChart" height="120"></canvas>
                            <div class="chart-legend">
                                <div class="legend-item"><span class="legend-dot" style="background:#06b6d4"></span> Doanh thu</div>
                                <div class="legend-item"><span class="legend-dot" style="background:#a855f7"></span> Phí vận chuyển</div>
                                <div class="legend-item"><span class="legend-dot" style="background:#f59e0b"></span> Hoàn tiền</div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="card" style="margin-top:16px">
                            <div class="card-header">
                                <span class="card-title">Đơn hàng mới nhất</span>
                                <div class="pagination" id="orderPagination"></div>
                            </div>
                            <table id="recentOrdersTable">
                                <thead>
                                    <tr>
                                        <th>Mã đơn</th>
                                        <th>Khách hàng</th>
                                        <th>Tỉnh / Thành</th>
                                        <th>Tổng tiền</th>
                                        <th>Thanh toán</th>
                                        <th>Ngày đặt</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody id="recentOrdersBody">
                                    <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">Đang tải...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ═══════════════════════════════════════════════ -->
                        <!-- ANALYTICS SECTIONS (2–6)                       -->
                        <!-- ═══════════════════════════════════════════════ -->

                        <!-- SECTION 2: HIỆU SUẤT SẢN PHẨM -->
                        <div class="analytics-section-heading">
                            <i class="fas fa-store"></i> 2 — Hiệu suất sản phẩm
                        </div>
                        <div class="analytics-two-col">
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Top 5 sản phẩm — doanh thu</div>
                                        <div class="analytics-card-sub" id="top5Month">Đang tải...</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="sellerTop5Tabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab active" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <ul class="top5-list" id="top5List">
                                    <li class="analytics-loader"><i class="fas fa-circle-notch"></i> Đang tải dữ liệu...</li>
                                </ul>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Tỷ lệ huỷ/hoàn đơn</div>
                                        <div class="analytics-card-sub">Sản phẩm có tỷ lệ cao nhất</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="sellerCancelTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab active" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div style="position:relative;width:100%;height:200px">
                                    <canvas id="cancelChart" role="img" aria-label="Biểu đồ tỷ lệ huỷ hoàn đơn theo sản phẩm">Đang tải...</canvas>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 3: SẢN PHẨM & TỒN KHO -->
                        <div class="analytics-section-heading">
                            <i class="fas fa-boxes"></i> 3 — Sản phẩm & tồn kho
                        </div>
                        <div class="analytics-two-col">
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Top 6 sản phẩm bán chạy</div>
                                        <div class="analytics-card-sub" id="topProdSub">Theo doanh số</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="topProdTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div style="position:relative;width:100%;height:210px">
                                    <canvas id="topProdChart" role="img" aria-label="Top 6 sản phẩm bán chạy">Đang tải...</canvas>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Cảnh báo tồn kho thấp</div>
                                        <div class="analytics-card-sub">Variants ≤ low_stock_alert</div>
                                    </div>
                                </div>
                                <ul class="low-stock-list" id="lowStockList">
                                    <li class="analytics-loader"><i class="fas fa-circle-notch"></i> Đang tải...</li>
                                </ul>
                            </div>
                        </div>

                        <div class="analytics-card analytics-one-col">
                            <div class="analytics-card-header">
                                <div>
                                    <div class="analytics-card-title">Phân bổ sản phẩm theo danh mục</div>
                                    <div class="analytics-card-sub">Số SKU và doanh thu theo category</div>
                                </div>
                                <div class="analytics-range-tabs" id="catTabs">
                                    <button class="analytics-range-tab" data-range="1">1N</button>
                                    <button class="analytics-range-tab" data-range="3">3N</button>
                                    <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                    <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                </div>
                            </div>
                            <div class="chart-legend-row">
                                <span><span class="legend-sq" style="background:#3b82f6"></span>Số SKU</span>
                                <span><span class="legend-sq" style="background:#f59e0b"></span>Doanh thu (triệu đ)</span>
                            </div>
                            <div id="catChartWrap" style="position:relative;width:100%;height:220px;overflow-y:auto">
                                <canvas id="catChart" role="img" aria-label="Phân bổ SKU và doanh thu theo danh mục">Đang tải...</canvas>
                            </div>
                        </div>

                        <!-- SECTION 4: NGƯỜI DÙNG & HÀNH VI -->
                        <div class="analytics-section-heading">
                            <i class="fas fa-users"></i> 4 — Khách hàng & hành vi
                        </div>
                        <div class="analytics-two-col">
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Khách hàng mới mua tại shop</div>
                                        <div class="analytics-card-sub">Phân loại theo provider</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="userTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div class="chart-legend-row">
                                    <span><span class="legend-sq" style="background:#3b82f6"></span>Local</span>
                                    <span><span class="legend-sq" style="background:#f97316"></span>Google</span>
                                    <span><span class="legend-sq" style="background:#22c55e"></span>Facebook</span>
                                </div>
                                <div style="position:relative;width:100%;height:180px">
                                    <canvas id="userChart" role="img" aria-label="Khách hàng mua tại shop theo provider">Đang tải...</canvas>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Phân khúc tần suất mua</div>
                                        <div class="analytics-card-sub">RFM — số đơn / khách</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="rfmTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div class="donut-wrap" style="margin-top:10px">
                                    <canvas id="rfmChart" role="img" aria-label="Phân khúc tần suất mua hàng RFM"
                                        style="width:150px!important;height:150px!important;flex-shrink:0">Đang tải...</canvas>
                                    <div class="donut-legend" id="rfmLegend">
                                        <div class="analytics-loader" style="height:60px"><i class="fas fa-circle-notch"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 5: VẬN CHUYỂN -->
                        <div class="analytics-section-heading">
                            <i class="fas fa-truck"></i> 5 — Vận chuyển
                        </div>
                        <div class="analytics-card analytics-one-col" style="margin-bottom:16px">
                            <div class="analytics-card-header">
                                <div>
                                    <div class="analytics-card-title">Pipeline trạng thái vận chuyển</div>
                                    <div class="analytics-card-sub">Số đơn đang ở từng bước</div>
                                </div>
                                <div class="analytics-range-tabs" id="pipelineTabs">
                                    <button class="analytics-range-tab" data-range="1">1N</button>
                                    <button class="analytics-range-tab" data-range="3">3N</button>
                                    <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                    <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                </div>
                            </div>
                            <div class="pipeline-row" id="pipelineRow">
                                <div class="analytics-loader"><i class="fas fa-circle-notch"></i> Đang tải...</div>
                            </div>
                        </div>
                        <div class="analytics-two-col">
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Hiệu suất đơn vị vận chuyển</div>
                                        <div class="analytics-card-sub">Tỷ lệ giao thành công (%)</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="shipTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div style="position:relative;width:100%;height:160px">
                                    <canvas id="shipChart" role="img" aria-label="Hiệu suất đơn vị vận chuyển">Đang tải...</canvas>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Yêu cầu huỷ đơn</div>
                                        <div class="analytics-card-sub">Trạng thái cancel_requests</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="cancelReqTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div class="donut-wrap" style="margin-top:10px">
                                    <canvas id="cancelReqChart" role="img" aria-label="Trạng thái yêu cầu huỷ đơn"
                                        style="width:150px!important;height:150px!important;flex-shrink:0">Đang tải...</canvas>
                                    <div class="donut-legend" id="cancelReqLegend">
                                        <div class="analytics-loader" style="height:60px"><i class="fas fa-circle-notch"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 6: SẢN PHẨM & ĐÁNH GIÁ -->
                        <div class="analytics-section-heading">
                            <i class="fas fa-shield-alt"></i> 6 — Sản phẩm & đánh giá
                        </div>
                        <div class="analytics-two-col">
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Sản phẩm theo trạng thái</div>
                                        <div class="analytics-card-sub">Của shop bạn</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="prodStatusTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div class="donut-wrap" style="margin-top:10px">
                                    <canvas id="prodStatusChart" role="img" aria-label="Sản phẩm theo trạng thái"
                                        style="width:150px!important;height:150px!important;flex-shrink:0">Đang tải...</canvas>
                                    <div class="donut-legend" id="prodStatusLegend">
                                        <div class="analytics-loader" style="height:60px"><i class="fas fa-circle-notch"></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="analytics-card">
                                <div class="analytics-card-header">
                                    <div>
                                        <div class="analytics-card-title">Đánh giá theo rating</div>
                                        <div class="analytics-card-sub">Phân phối 1–5 sao (is_approved=1)</div>
                                    </div>
                                    <div class="analytics-range-tabs" id="ratingTabs">
                                        <button class="analytics-range-tab" data-range="1">1N</button>
                                        <button class="analytics-range-tab" data-range="3">3N</button>
                                        <button class="analytics-range-tab" data-range="7">1 Tuần</button>
                                        <button class="analytics-range-tab active" data-range="30">1 Tháng</button>
                                    </div>
                                </div>
                                <div style="position:relative;width:100%;height:190px">
                                    <canvas id="ratingChart" role="img" aria-label="Phân phối đánh giá theo số sao">Đang tải...</canvas>
                                </div>
                            </div>
                        </div>

                    </div><!-- end bottom-grid inner -->
                </div><!-- end bottom-grid -->
            </div><!-- end page-dashboard -->

            <!-- PLACEHOLDER PAGES -->
            <?php include 'products/index.php'; ?>
            <?php include 'orders/index.php'; ?>
            <?php include 'reviews/index.php'; ?>
            <?php include 'brands/index.php'; ?>
        </main>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SCRIPTS
    ══════════════════════════════════════════════════════════ -->
    <script>
    /* ── Recent orders ── */
    (function() {
        let currentPage = 1;
        function loadOrders(page) {
            currentPage = page;
            fetch(`?recent_orders=${page}`).then(r => r.json()).then(data => {
                renderTable(data.rows);
                renderPagination(data.total, data.limit);
            });
        }
        function renderTable(rows) {
            const tbody = document.getElementById('recentOrdersBody');
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">Chưa có đơn hàng</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(r => `
                <tr>
                    <td><strong>${r.id}</strong></td>
                    <td>${r.customer}</td>
                    <td><span style="display:flex;align-items:center;gap:5px">
                        <i class="fas fa-map-marker-alt" style="color:#a855f7;font-size:11px"></i>${r.province}
                    </span></td>
                    <td><strong>${r.amount}</strong></td>
                    <td>${r.payment}</td>
                    <td style="color:#9ca3af;font-size:12px">${r.date}</td>
                    <td><span class="badge ${r.class}">${r.status}</span></td>
                </tr>`).join('');
        }
        function renderPagination(total, limit) {
            const pages = Math.ceil(total / limit);
            const pg = document.getElementById('orderPagination');
            if (pages <= 1) { pg.innerHTML = ''; return; }
            let html = '';
            if (currentPage > 1) html += `<div class="page-btn" onclick="loadOrderPage(${currentPage-1})"><i class="fas fa-chevron-left" style="font-size:10px"></i></div>`;
            const start = Math.max(1, currentPage-2), end = Math.min(pages, currentPage+2);
            if (start > 1) html += `<div class="page-btn" onclick="loadOrderPage(1)">1</div><div class="page-btn" style="pointer-events:none">…</div>`;
            for (let i = start; i <= end; i++) html += `<div class="page-btn ${i===currentPage?'active':''}" onclick="loadOrderPage(${i})">${i}</div>`;
            if (end < pages) html += `<div class="page-btn" style="pointer-events:none">…</div><div class="page-btn" onclick="loadOrderPage(${pages})">${pages}</div>`;
            if (currentPage < pages) html += `<div class="page-btn" onclick="loadOrderPage(${currentPage+1})"><i class="fas fa-chevron-right" style="font-size:10px"></i></div>`;
            pg.innerHTML = html;
        }
        window.loadOrderPage = loadOrders;
        loadOrders(1);
    })();
    </script>

    <script>
    /* ── Revenue chart ── */
    (function() {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        function makeGrad(r,g,b) {
            const gr = ctx.createLinearGradient(0,0,0,300);
            gr.addColorStop(0,`rgba(${r},${g},${b},.35)`);
            gr.addColorStop(1,`rgba(${r},${g},${b},.02)`);
            return gr;
        }
        let chart = null;
        function buildChart(data) {
            if (chart) chart.destroy();
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { type:'line', label:'Doanh thu',       data:data.revenue,  borderColor:'#06b6d4', backgroundColor:makeGrad(6,182,212),   tension:.45, fill:true, pointBackgroundColor:'#06b6d4', pointRadius:5, pointHoverRadius:7, borderWidth:2.5, order:0 },
                        { type:'line', label:'Phí vận chuyển',  data:data.shipping, borderColor:'#a855f7', backgroundColor:makeGrad(168,85,247),  tension:.45, fill:true, pointBackgroundColor:'#a855f7', pointRadius:5, pointHoverRadius:7, borderWidth:2.5, borderDash:[5,3], order:1 },
                        { type:'bar',  label:'Hoàn tiền',       data:data.refund,   backgroundColor:makeGrad(245,158,11), borderRadius:6, barPercentage:.45, order:2 }
                    ]
                },
                options: {
                    responsive:true, animation:{duration:400},
                    interaction:{mode:'index',intersect:false},
                    plugins:{
                        legend:{display:false},
                        tooltip:{
                            backgroundColor:'#1e1b4b', titleColor:'#fff', bodyColor:'rgba(255,255,255,.8)',
                            padding:12, cornerRadius:10,
                            callbacks:{ label: c => ` ${c.dataset.label}: ${Number(c.parsed.y).toLocaleString('vi-VN')}đ` }
                        }
                    },
                    layout:{padding:{top:10,bottom:0}},
                    scales:{
                        x:{ grid:{display:false}, ticks:{color:'#9ca3af',font:{size:12}} },
                        y:{
                            grid:{color:'rgba(0,0,0,.05)',drawBorder:false},
                            ticks:{color:'#9ca3af',font:{size:11},maxTicksLimit:5,
                                callback:v=>{ if(v===0)return'0đ'; if(v>=1_000_000)return(v/1_000_000).toFixed(1).replace('.0','')+'tr'; if(v>=1_000)return(v/1_000).toFixed(0)+'k'; return v+'đ'; }
                            },
                            min:0, grace:'20%'
                        }
                    }
                }
            });
        }
        function loadChart(days) {
            fetch(`?chart_range=${days}`)
                .then(r=>r.json())
                .then(data=>buildChart(data))
                .catch(()=>buildChart({
                    labels:  Array.from({length:days},(_,i)=>{const d=new Date();d.setDate(d.getDate()-(days-1-i));return d.toLocaleDateString('vi-VN',{day:'2-digit',month:'2-digit'});}),
                    revenue: Array(days).fill(0),
                    shipping:Array(days).fill(0),
                    refund:  Array(days).fill(0)
                }));
        }
        document.querySelectorAll('.range-tab').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.range-tab').forEach(b=>b.classList.remove('active'));
                this.classList.add('active');
                loadChart(+this.dataset.range);
            });
        });
        loadChart(7);
    })();
    </script>

    <script>
    /* ══════════════════════════════════════════════════════════
       ANALYTICS DASHBOARD
    ══════════════════════════════════════════════════════════ */

    let _analyticsCharts = {};

    function destroyChart(id) {
        if (_analyticsCharts[id]) { _analyticsCharts[id].destroy(); delete _analyticsCharts[id]; }
    }
    function mkChart(id, cfg) {
        destroyChart(id);
        const canvas = document.getElementById(id);
        if (!canvas) return;
        _analyticsCharts[id] = new Chart(canvas, cfg);
    }

    function getActiveDays(groupId) {
        const wrap = document.getElementById(groupId);
        if (!wrap) return 30;
        const active = wrap.querySelector('.analytics-range-tab.active');
        return active ? +active.dataset.range : 30;
    }

    /* ── Section 2: Top sản phẩm của shop ── */
    function loadAnalyticsSellers(days) {
        days = days || getActiveDays('sellerTop5Tabs');
        fetch(`?analytics_data=sellers&days=${days}`).then(r=>r.json()).then(data => {
            const rangeLabel = days===1?'hôm nay':days===3?'3 ngày qua':days===7?'tuần này':'tháng này';
            document.getElementById('top5Month').textContent = `Doanh thu ${rangeLabel}`;

            const ul = document.getElementById('top5List');
            if (!data.top5 || !data.top5.length) {
                ul.innerHTML = '<li style="padding:12px;color:#9ca3af;font-size:13px">Chưa có dữ liệu</li>';
            } else {
                const maxRev = Math.max(...data.top5.map(s=>+s.revenue));
                ul.innerHTML = data.top5.map((s,i)=>{
                    const pct = maxRev>0 ? Math.round(+s.revenue/maxRev*100) : 0;
                    const val = +s.revenue>=1e9?(+s.revenue/1e9).toFixed(1)+'B':+s.revenue>=1e6?(+s.revenue/1e6).toFixed(0)+'tr':+s.revenue>=1e3?(+s.revenue/1e3).toFixed(0)+'k':s.revenue+'đ';
                    return `<li class="top5-item">
                        <span class="top5-rank">${i+1}</span>
                        <div class="top5-bar-wrap">
                            <div class="top5-name">${s.shop_name}</div>
                            <div class="top5-bar-bg"><div class="top5-bar-fill" style="width:${pct}%"></div></div>
                        </div>
                        <span class="top5-val">${val}</span>
                    </li>`;
                }).join('');
            }

            const labels  = (data.cancelRates||[]).map(r=>r.shop_name);
            const cancels = (data.cancelRates||[]).map(r=>+r.cancel_rate||0);
            const refunds = (data.cancelRates||[]).map(r=>+r.refund_rate||0);
            mkChart('cancelChart', {
                type:'bar',
                data:{ labels, datasets:[
                    { label:'Tỷ lệ huỷ',  data:cancels, backgroundColor:'#ef4444', borderRadius:3 },
                    { label:'Tỷ lệ hoàn', data:refunds, backgroundColor:'#f59e0b', borderRadius:3 },
                ]},
                options:{
                    indexAxis:'y', responsive:true, maintainAspectRatio:false,
                    plugins:{legend:{display:false}},
                    scales:{
                        x:{grid:{color:'rgba(0,0,0,.05)'},ticks:{callback:v=>v+'%',color:'#9ca3af',font:{size:11}}},
                        y:{grid:{display:false},ticks:{color:'#6b7280',font:{size:11}}}
                    }
                }
            });
        }).catch(()=>{
            document.getElementById('top5List').innerHTML='<li style="padding:12px;color:#9ca3af;font-size:13px">Không thể tải dữ liệu</li>';
        });
    }

    /* ── Section 3: Sản phẩm & tồn kho ── */
    function loadAnalyticsProducts(days) {
        days = days || getActiveDays('topProdTabs');
        const rangeLabel = days===1?'hôm nay':days===3?'3 ngày qua':days===7?'7 ngày qua':'30 ngày qua';
        document.getElementById('topProdSub').textContent = `Theo doanh số — ${rangeLabel}`;

        fetch(`?analytics_data=products&days=${days}`).then(r=>r.json()).then(data=>{
            const names = (data.topProducts||[]).map(p=>p.name.length>16?p.name.slice(0,16)+'…':p.name);
            const sold  = (data.topProducts||[]).map(p=>+p.sold_count||0);
            mkChart('topProdChart',{
                type:'bar',
                data:{labels:names,datasets:[{data:sold,backgroundColor:'#3b82f6',borderRadius:4}]},
                options:{
                    indexAxis:'y',responsive:true,maintainAspectRatio:false,
                    plugins:{legend:{display:false}},
                    scales:{x:{grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9ca3af',font:{size:11}}},y:{grid:{display:false},ticks:{color:'#6b7280',font:{size:11}}}}
                }
            });

            const sl = document.getElementById('lowStockList');
            if (!data.lowStock||!data.lowStock.length) {
                sl.innerHTML='<li style="padding:12px;color:#9ca3af;font-size:13px">Không có tồn kho thấp 🎉</li>';
            } else {
                sl.innerHTML=data.lowStock.map(r=>{
                    const badge=r.stock_quantity<=2?'out':'low';
                    const label=badge==='out'?'Hết sắp':'Thấp';
                    const detail=[r.size,r.color].filter(Boolean).join(' / ');
                    const name=r.product_name+(detail?` — ${detail}`:'');
                    return `<li class="low-stock-item">
                        <span class="badge-xs badge-${badge}">${label}</span>
                        <span class="stock-name">${name}</span>
                        <span class="stock-qty">${r.stock_quantity}</span>
                    </li>`;
                }).join('');
            }

            const catFiltered=(data.catStats||[]).filter(c=>(+c.sku_count||0)>0||(+c.revenue||0)>0);
            const catLabels=catFiltered.map(c=>c.cat_name);
            const skus=catFiltered.map(c=>+c.sku_count||0);
            const revenues=catFiltered.map(c=>+(+c.revenue/1e6).toFixed(1));
            const catHeight=Math.max(220,catLabels.length*52);
            const catWrap=document.getElementById('catChartWrap');
            if(catWrap) catWrap.style.height=catHeight+'px';
            mkChart('catChart',{
                type:'bar',
                data:{labels:catLabels,datasets:[
                    {label:'Số SKU',          data:skus,     backgroundColor:'#3b82f6',borderRadius:4,barThickness:14},
                    {label:'Doanh thu (triệu)',data:revenues, backgroundColor:'#f59e0b',borderRadius:4,barThickness:14},
                ]},
                options:{
                    indexAxis:'y',responsive:true,maintainAspectRatio:false,
                    plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>ctx.datasetIndex===0?` SKU: ${ctx.parsed.x}`:` DT: ${ctx.parsed.x.toFixed(1)}tr`}}},
                    scales:{x:{grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9ca3af',font:{size:11}},beginAtZero:true},y:{grid:{display:false},ticks:{color:'#6b7280',font:{size:11},autoSkip:false}}}
                }
            });
        });
    }

    /* ── Section 4: Khách hàng ── */
    function loadAnalyticsUsers(days) {
        days = days || getActiveDays('userTabs');
        fetch(`?analytics_data=users&days=${days}`).then(r=>r.json()).then(data=>{
            const labels    = (data.months||[]).map(m=>m.label);
            const locals    = (data.months||[]).map(m=>m.local);
            const googles   = (data.months||[]).map(m=>m.google);
            const facebooks = (data.months||[]).map(m=>m.facebook);
            mkChart('userChart',{
                type:'bar',
                data:{labels,datasets:[
                    {label:'Local',    data:locals,    backgroundColor:'#3b82f6',stack:'s'},
                    {label:'Google',   data:googles,   backgroundColor:'#f97316',stack:'s'},
                    {label:'Facebook', data:facebooks, backgroundColor:'#22c55e',stack:'s'},
                ]},
                options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
                    scales:{x:{stacked:true,grid:{display:false},ticks:{color:'#6b7280',font:{size:11}}},y:{stacked:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9ca3af',font:{size:11}}}}}
            });

            const rfm=data.rfm||{};
            const one=+rfm.one_order||0, few=+rfm.few_orders||0, many=+rfm.many_orders||0;
            const total=one+few+many||1;
            const p1=Math.round(one/total*100), p2=Math.round(few/total*100), p3=Math.round(many/total*100);
            mkChart('rfmChart',{
                type:'doughnut',
                data:{datasets:[{data:[p1,p2,p3],backgroundColor:['#bfdbfe','#3b82f6','#1d4ed8'],borderWidth:2,borderColor:'#fff',hoverOffset:4}]},
                options:{responsive:false,plugins:{legend:{display:false}},cutout:'62%'}
            });
            document.getElementById('rfmLegend').innerHTML=`
                <div class="donut-legend-item"><span class="donut-dot" style="background:#bfdbfe"></span>1 đơn<span class="donut-pct">${p1}%</span></div>
                <div class="donut-legend-item"><span class="donut-dot" style="background:#3b82f6"></span>2–5 đơn<span class="donut-pct">${p2}%</span></div>
                <div class="donut-legend-item"><span class="donut-dot" style="background:#1d4ed8"></span>5+ đơn<span class="donut-pct">${p3}%</span></div>`;
        });
    }

    /* ── Section 5: Vận chuyển ── */
    function loadAnalyticsShipping(days) {
        days = days || getActiveDays('pipelineTabs');
        fetch(`?analytics_data=shipping&days=${days}`).then(r=>r.json()).then(data=>{
            const pip=data.pipeline||{};
            const steps=[
                {label:'Chờ lấy',        val:+pip.waiting||0,    color:'#9ca3af',bar:'#d1d5db'},
                {label:'Đã lấy',          val:+pip.picked||0,     color:'#3b82f6',bar:'#3b82f6'},
                {label:'Đang vận chuyển', val:+pip.transit||0,    color:'#112D60',bar:'#112D60'},
                {label:'Đã giao',         val:+pip.delivered||0,  color:'#22c55e',bar:'#22c55e'},
                {label:'Thất bại',        val:+pip.failed||0,     color:'#ef4444',bar:'#ef4444'},
            ];
            document.getElementById('pipelineRow').innerHTML=steps.map(s=>
                `<div class="pipeline-step">
                    <div class="pip-val" style="color:${s.color}">${s.val.toLocaleString('vi-VN')}</div>
                    <div class="pip-bar" style="background:${s.bar}"></div>
                    <div class="pip-label">${s.label}</div>
                </div>`
            ).join('');

            const provLabels=(data.providers||[]).map(p=>p.provider);
            const provRates=(data.providers||[]).map(p=>+p.success_rate||0);
            const provColors=['#22c55e','#3b82f6','#7c3aed','#f59e0b','#06b6d4'];
            mkChart('shipChart',{
                type:'bar',
                data:{labels:provLabels,datasets:[{data:provRates,backgroundColor:provColors.slice(0,provLabels.length),borderRadius:4}]},
                options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
                    scales:{x:{min:60,max:100,grid:{color:'rgba(0,0,0,.05)'},ticks:{callback:v=>v+'%',color:'#9ca3af',font:{size:11}}},y:{grid:{display:false},ticks:{color:'#6b7280',font:{size:11}}}}}
            });

            const cr=data.cancelReq||{};
            const pending=+cr.pending||0, approved=+cr.approved||0, rejected=+cr.rejected||0;
            const crTotal=pending+approved+rejected||1;
            const cp=Math.round(pending/crTotal*100), ca=Math.round(approved/crTotal*100), cr2=Math.round(rejected/crTotal*100);
            mkChart('cancelReqChart',{
                type:'doughnut',
                data:{datasets:[{data:[cp,ca,cr2],backgroundColor:['#f59e0b','#22c55e','#ef4444'],borderWidth:2,borderColor:'#fff',hoverOffset:4}]},
                options:{responsive:false,plugins:{legend:{display:false}},cutout:'62%'}
            });
            document.getElementById('cancelReqLegend').innerHTML=`
                <div class="donut-legend-item"><span class="donut-dot" style="background:#f59e0b"></span>Pending<span class="donut-pct">${cp}%</span></div>
                <div class="donut-legend-item"><span class="donut-dot" style="background:#22c55e"></span>Approved<span class="donut-pct">${ca}%</span></div>
                <div class="donut-legend-item"><span class="donut-dot" style="background:#ef4444"></span>Rejected<span class="donut-pct">${cr2}%</span></div>`;
        });
    }

    /* ── Section 6: Sản phẩm & đánh giá ── */
    function loadAnalyticsPlatform(days) {
        days = days || getActiveDays('prodStatusTabs');
        fetch(`?analytics_data=platform&days=${days}`).then(r=>r.json()).then(data=>{
            const statusMap={active:{color:'#22c55e',label:'Active'},draft:{color:'#93c5fd',label:'Draft'},inactive:{color:'#fb923c',label:'Inactive'},banned:{color:'#ef4444',label:'Banned'}};
            const ps=data.prodStatus||[];
            const psCounts=ps.map(r=>+r.cnt||0);
            const psColors=ps.map(r=>statusMap[r.status]?.color||'#9ca3af');
            const psTotal=psCounts.reduce((a,b)=>a+b,0)||1;
            mkChart('prodStatusChart',{
                type:'doughnut',
                data:{datasets:[{data:psCounts,backgroundColor:psColors,borderWidth:2,borderColor:'#fff',hoverOffset:4}]},
                options:{responsive:false,plugins:{legend:{display:false}},cutout:'62%'}
            });
            document.getElementById('prodStatusLegend').innerHTML=ps.map((r,i)=>{
                const pct=Math.round(+r.cnt/psTotal*100);
                const info=statusMap[r.status]||{color:'#9ca3af',label:r.status};
                return `<div class="donut-legend-item"><span class="donut-dot" style="background:${info.color}"></span>${info.label}<span class="donut-pct">${pct}%</span></div>`;
            }).join('');

            const ratingData=[0,0,0,0,0];
            (data.ratings||[]).forEach(r=>{if(r.rating>=1&&r.rating<=5)ratingData[r.rating-1]=+r.cnt||0;});
            mkChart('ratingChart',{
                type:'bar',
                data:{labels:['⭐ 1 sao','⭐⭐ 2 sao','⭐⭐⭐ 3 sao','⭐⭐⭐⭐ 4 sao','⭐⭐⭐⭐⭐ 5 sao'],datasets:[{data:ratingData,backgroundColor:'#3b82f6',borderRadius:6}]},
                options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
                    scales:{x:{grid:{display:false},ticks:{color:'#6b7280',font:{size:10}}},y:{grid:{color:'rgba(0,0,0,.05)'},ticks:{color:'#9ca3af',font:{size:11}}}}}
            });
        });
    }

    /* ── Gắn sự kiện range tabs ── */
    function syncTab(groupId, days) {
        const wrap = document.getElementById(groupId);
        if (!wrap) return;
        wrap.querySelectorAll('.analytics-range-tab').forEach(b=>{
            b.classList.toggle('active', +b.dataset.range===days);
        });
    }

    function initAnalyticsRangeTabs() {
        const configs = [
            { id:'sellerTop5Tabs',  fn: loadAnalyticsSellers },
            { id:'sellerCancelTabs',fn: function(days){ syncTab('sellerTop5Tabs',days); loadAnalyticsSellers(days); }},
            { id:'topProdTabs',     fn: function(days){ syncTab('catTabs',days);        loadAnalyticsProducts(days); }},
            { id:'catTabs',         fn: function(days){ syncTab('topProdTabs',days);    loadAnalyticsProducts(days); }},
            { id:'userTabs',        fn: function(days){ syncTab('rfmTabs',days);        loadAnalyticsUsers(days); }},
            { id:'rfmTabs',         fn: function(days){ syncTab('userTabs',days);       loadAnalyticsUsers(days); }},
            { id:'pipelineTabs',    fn: function(days){ syncTab('shipTabs',days); syncTab('cancelReqTabs',days); loadAnalyticsShipping(days); }},
            { id:'shipTabs',        fn: function(days){ syncTab('pipelineTabs',days); syncTab('cancelReqTabs',days); loadAnalyticsShipping(days); }},
            { id:'cancelReqTabs',   fn: function(days){ syncTab('pipelineTabs',days); syncTab('shipTabs',days); loadAnalyticsShipping(days); }},
            { id:'prodStatusTabs',  fn: function(days){ syncTab('ratingTabs',days);     loadAnalyticsPlatform(days); }},
            { id:'ratingTabs',      fn: function(days){ syncTab('prodStatusTabs',days); loadAnalyticsPlatform(days); }},
        ];
        configs.forEach(({id, fn})=>{
            const wrap = document.getElementById(id);
            if (!wrap) return;
            wrap.querySelectorAll('.analytics-range-tab').forEach(btn=>{
                btn.addEventListener('click', function(){
                    wrap.querySelectorAll('.analytics-range-tab').forEach(b=>b.classList.remove('active'));
                    this.classList.add('active');
                    fn(+this.dataset.range);
                });
            });
        });
    }

    function loadAllAnalytics() {
        initAnalyticsRangeTabs();
        loadAnalyticsSellers();
        loadAnalyticsProducts();
        loadAnalyticsUsers();
        loadAnalyticsShipping();
        loadAnalyticsPlatform();
    }
    </script>

    <script>
    /* ── NAVIGATION ── */
    function switchPage(pageName) {
        document.querySelectorAll('.nav-item').forEach(x=>x.classList.remove('active'));
        const navEl = document.querySelector(`.nav-item[data-page="${pageName}"]`);
        if (navEl) navEl.classList.add('active');
        document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
        const pageEl = document.getElementById('page-'+pageName);
        if (!pageEl) return;
        pageEl.classList.add('active');
        location.hash = pageName;
        if (pageName==='dashboard') loadAllAnalytics();
        if (pageName==='products'  && typeof renderProducts  === 'function') renderProducts();
        if (pageName==='donhang'   && typeof renderOrders    === 'function') renderOrders();
        if (pageName==='brands'    && typeof renderBrands    === 'function') renderBrands();
        if (pageName==='reviews'   && typeof renderFeedback  === 'function') renderFeedback();
    }
    document.querySelectorAll('.nav-item[data-page]').forEach(el=>{
        el.addEventListener('click', function(){ switchPage(this.dataset.page); });
    });
    window.addEventListener('load', function(){
        const urlParams = new URLSearchParams(location.search);
        const initPage  = urlParams.get('page') || location.hash.replace('#','') || 'dashboard';
        switchPage(initPage);
    });
    window.onerror = function(msg, src, line){
        console.error('JS Error:', msg, src, line);
        return false;
    };
    </script>
</body>
</html>