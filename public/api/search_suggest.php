<?php
// /MantaMarket/public/api/search_suggest.php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Tìm và load DbConnection đúng cách
$possiblePaths = [
    __DIR__ . '/../../app/Libs/DbConnection.php',
    __DIR__ . '/../../../app/Libs/DbConnection.php',
    dirname(__DIR__, 2) . '/app/Libs/DbConnection.php',
];
$loaded = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    http_response_code(500);
    echo json_encode(['error' => 'Không tìm thấy DbConnection.php. Paths tried: ' . implode(', ', $possiblePaths)]);
    exit;
}

$keyword = trim($_GET['q'] ?? '');

if (strlen($keyword) < 1) {
    echo json_encode(['products' => [], 'categories' => []]);
    exit;
}

try {
    $db  = new app_Libs_DbConnection();
    $kw  = '%' . $keyword . '%';

    // Sản phẩm
    $products = $db->query("
        SELECT
            p.id,
            p.name,
            p.base_price,
            p.avg_rating,
            p.sold_count,
            pi.image_url
        FROM products p
        LEFT JOIN product_images pi
            ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE p.status = 'active'
          AND p.name LIKE :kw
        ORDER BY p.sold_count DESC, p.avg_rating DESC
        LIMIT 8
    ", [':kw' => $kw])->fetchAll(PDO::FETCH_ASSOC);

    // Danh mục
    $categories = $db->query("
        SELECT id, name, slug
        FROM categories
        WHERE is_active = 1
          AND name LIKE :kw
        ORDER BY sort_order ASC
        LIMIT 3
    ", [':kw' => $kw])->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'products'   => $products,
        'categories' => $categories,
        'keyword'    => $keyword,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}