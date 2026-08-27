<?php
// ajax_view.php — Lấy chi tiết sản phẩm (variants, images, attributes, tags)
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo '{"variants":[],"attributes":[],"images":[],"tags":[]}';
    exit();
}

// Dùng singleton từ DbConnection — không tạo PDO mới
$db  = new app_Libs_DbConnection();
$pdo = $db->connect(); // Trả về PDO instance đã có
// Lấy thông tin sản phẩm chính
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Thêm sau phần query tags
$stmt = $pdo->prepare("
    SELECT r.*, u.username, u.avatar
    FROM product_reviews r
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
try {
    $stmt = $pdo->prepare("
        SELECT * FROM product_variants
        WHERE product_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT pi.*, pv.color AS variant_color
        FROM   product_images pi
        LEFT JOIN product_variants pv ON pv.id = pi.variant_id
        WHERE  pi.product_id = ?
        ORDER  BY pi.is_primary DESC, pi.sort_order ASC
    ");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT * FROM product_attributes
        WHERE product_id = ?
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$id]);
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT tag FROM product_tags
        WHERE product_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'product' => $product,
        'variants'   => $variants,
        'attributes' => $attributes,
        'images'     => $images,
        'tags'       => $tags,
        'reviews'    => $reviews,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit();