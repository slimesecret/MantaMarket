<?php
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$current_user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$current_user_id) { echo json_encode(['success'=>false,'message'=>'Chưa đăng nhập']); exit; }

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) { echo json_encode(['success'=>false,'message'=>'Thiếu order_id']); exit; }

try {
    $pdo = new PDO("mysql:host=localhost;port=3306;dbname=mantamarket;charset=utf8mb4",
        'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

    // Lấy đơn hàng
// Sửa SELECT trong query lấy đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*,
           s.shop_name, s.shop_slug, s.address AS shop_address,
           s.avatar_url AS shop_avatar,
           c.code AS coupon_code,
           sh.estimated_date, sh.status AS shipping_status,
           ua.full_name AS addr_name, ua.phone AS addr_phone,
           ua.province, ua.district, ua.ward, ua.address_line
    FROM orders o
    LEFT JOIN sellers        s  ON s.id = o.seller_id
    LEFT JOIN coupons        c  ON c.id = o.coupon_id
    LEFT JOIN shipping       sh ON sh.order_id = o.id
    LEFT JOIN user_addresses ua ON ua.id = o.address_id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");


    $stmt->execute([$order_id, $current_user_id]);
    $order = $stmt->fetch();
    if (!$order) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy']); exit; }

    // Lấy items
    $stmt = $pdo->prepare("
        SELECT oi.*, pi.image_url AS product_image
        FROM order_items oi
        LEFT JOIN product_images pi ON pi.product_id = oi.product_id AND pi.is_primary = 1
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    // Tính estimated_date nếu chưa có
    $estimated_date = null;
    $distance_km    = null;
    if (!empty($order['estimated_date'])) {
        $estimated_date = date('d/m/Y', strtotime($order['estimated_date']));
    } elseif (in_array($order['order_status'], ['confirmed','processing','shipped'])) {
        // Geocode & tính khoảng cách
        $recv_addr  = trim($order['ward'].' '.$order['district'].' '.$order['province'].' Việt Nam');
        $shop_addr  = trim(($order['shop_address'] ?: 'Hà Nội').' Việt Nam');
        $recv_coord = geocode(urlencode($recv_addr));
        $shop_coord = geocode(urlencode($shop_addr));
        if ($recv_coord && $shop_coord) {
            $km = haversine($shop_coord['lat'],$shop_coord['lon'],$recv_coord['lat'],$recv_coord['lon']);
            $distance_km = round($km, 1);
            $days = $km<=30?1:($km<=100?2:($km<=500?3:($km<=1000?4:5)));
            $estimated_date = date('d/m/Y', strtotime("+{$days} days"));
        } else {
            $estimated_date = date('d/m/Y', strtotime('+3 days'));
        }
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'order_code'     => $order['order_code'],
            'order_status'   => $order['order_status'],
            'payment_method' => $order['payment_method'],
    'shop_avatar' => $order['shop_avatar'],

            'payment_status' => $order['payment_status'],
            'created_at'     => $order['created_at'],
            'subtotal'       => $order['subtotal'],
            'shipping_fee'   => $order['shipping_fee'],
            'discount_amount'=> $order['discount_amount'],
            'total_amount'   => $order['total_amount'],
            'coupon_code'    => $order['coupon_code'],
            'shop_name'      => $order['shop_name'],
            'estimated_date' => $estimated_date,
            'distance_km'    => $distance_km,
            'address'        => $order['addr_name'] ? [
                'full_name'   => $order['addr_name'],
                'phone'       => $order['addr_phone'],
                'province'    => $order['province'],
                'district'    => $order['district'],
                'ward'        => $order['ward'],
                'address_line'=> $order['address_line'],
            ] : null,
            'items' => $items,
        ]
    ]);

} catch(Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Lỗi server']);
}

function geocode(string $q): ?array {
    $url = "https://nominatim.openstreetmap.org/search?q={$q}&format=json&limit=1";
    $ctx = stream_context_create(['http'=>['timeout'=>5,'header'=>"User-Agent: MantaMarket/1.0\r\n"]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;
    $d = json_decode($res, true);
    return empty($d[0]) ? null : ['lat'=>(float)$d[0]['lat'],'lon'=>(float)$d[0]['lon']];
}

function haversine(float $lat1,float $lon1,float $lat2,float $lon2): float {
    $R=6371; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
    $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return $R*2*atan2(sqrt($a),sqrt(1-$a));
}