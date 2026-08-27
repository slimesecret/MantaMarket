<?php
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$current_user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu order_id']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=mantamarket;charset=utf8mb4",
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Lấy thông tin đơn hàng + địa chỉ người nhận + địa chỉ shop
    $stmt = $pdo->prepare("
        SELECT
            o.id, o.order_status, o.created_at,
            ua.province AS recv_province,
            ua.district AS recv_district,
            ua.ward     AS recv_ward,
            s.address   AS shop_address,
            sh.estimated_date,
            sh.status   AS shipping_status
        FROM orders o
        LEFT JOIN user_addresses ua ON ua.id = o.address_id
        LEFT JOIN sellers s         ON s.id  = o.seller_id
        LEFT JOIN shipping sh       ON sh.order_id = o.id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id, $current_user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng']);
        exit;
    }

    // Nếu đã có estimated_date từ shipping table → dùng luôn
    if (!empty($order['estimated_date'])) {
        echo json_encode([
            'success'        => true,
            'estimated_date' => date('d/m/Y', strtotime($order['estimated_date'])),
            'source'         => 'shipping_table'
        ]);
        exit;
    }

    // Geocode địa chỉ người nhận
    $recv_query = urlencode($order['recv_ward'] . ', ' . $order['recv_district'] . ', ' . $order['recv_province'] . ', Việt Nam');
    $recv_coord = geocode($recv_query);

    // Geocode địa chỉ shop
    $shop_query = urlencode(($order['shop_address'] ?? 'Hà Nội') . ', Việt Nam');
    $shop_coord = geocode($shop_query);

    if (!$recv_coord || !$shop_coord) {
        // Fallback: ước tính 3-5 ngày nếu không geocode được
        $estimated = date('d/m/Y', strtotime('+4 days'));
        echo json_encode(['success' => true, 'estimated_date' => $estimated, 'source' => 'fallback']);
        exit;
    }

    // Tính khoảng cách Haversine (km)
    $dist_km = haversine(
        $shop_coord['lat'], $shop_coord['lon'],
        $recv_coord['lat'], $recv_coord['lon']
    );

    // Ước tính ngày giao hàng theo khoảng cách
    $days = estimate_days($dist_km);
    $estimated = date('d/m/Y', strtotime("+{$days} days"));

    echo json_encode([
        'success'        => true,
        'estimated_date' => $estimated,
        'distance_km'    => round($dist_km, 1),
        'days'           => $days,
        'source'         => 'calculated'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi server']);
}

// ── Hàm geocode qua Nominatim ──
function geocode(string $query): ?array {
    $url = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1";
    $ctx = stream_context_create(['http' => [
        'timeout' => 5,
        'header'  => "User-Agent: MantaMarket/1.0\r\n"
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;
    $data = json_decode($res, true);
    if (empty($data[0])) return null;
    return ['lat' => (float)$data[0]['lat'], 'lon' => (float)$data[0]['lon']];
}

// ── Haversine formula ──
function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// ── Ước tính số ngày theo khoảng cách ──
function estimate_days(float $km): int {
    if ($km <= 30)  return 1;  // Nội thành
    if ($km <= 100) return 2;  // Cùng tỉnh
    if ($km <= 500) return 3;  // Vùng lân cận
    if ($km <= 1000) return 4; // Liên vùng
    return 5;                  // Toàn quốc (Bắc-Nam)
}