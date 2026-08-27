<?php
// ajax_view.php — Lấy chi tiết sản phẩm (variants, images, attributes, tags, reviews)
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Cho phép fetch từ trang chủ

 $id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['product' => null, 'variants' => [], 'attributes' => [], 'images' => [], 'tags' => [], 'reviews' => []], JSON_UNESCAPED_UNICODE);
    exit();
}

// Dùng singleton từ DbConnection
 $db  = new app_Libs_DbConnection();
 $pdo = $db->connect();

// Khởi tạo mảng mặc định
 $product    = null;
 $variants   = [];
 $images     = [];
 $attributes = [];
 $tags       = [];
 $reviews    = [];

try {
    // 1. Lấy thông tin sản phẩm chính
    $stmt = $pdo->prepare("
        SELECT p.*, pi.image_url
        FROM products p
        LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // Không tìm thấy sản phẩm -> trả về rỗng
        echo json_encode(['product' => null, 'variants' => [], 'attributes' => [], 'images' => [], 'tags' => [], 'reviews' => []], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Lấy Variants
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Lấy Images
    $stmt = $pdo->prepare("
        SELECT pi.*, pv.color AS variant_color
        FROM product_images pi
        LEFT JOIN product_variants pv ON pv.id = pi.variant_id
        WHERE pi.product_id = ?
        ORDER BY pi.is_primary DESC, pi.sort_order ASC
    ");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Lấy Attributes
    $stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$id]);
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Lấy Tags
    // ⚠️ Nếu bảng của bạn lưu trực tiếp tên tag (cột 'tag'), giữ nguyên câu dưới:
    $stmt = $pdo->prepare("SELECT tag FROM product_tags WHERE product_id = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* 
    // 👉 NẾU LỖI TAGS: Bảng của bạn dùng tag_id (bảng trung gian), hãy xóa 3 dòng trên 
    // và dùng 2 dòng join dưới đây thay thế:
    
    $stmt = $pdo->prepare("
        SELECT t.name AS tag 
        FROM product_tags pt 
        LEFT JOIN tags t ON t.id = pt.tag_id 
        WHERE pt.product_id = ?
    ");
    $stmt->execute([$id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    */

    // 6. Lấy Reviews
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

    echo json_encode([
        'product'    => $product,
        'variants'   => $variants,
        'attributes' => $attributes,
        'images'     => $images,
        'tags'       => $tags,
        'reviews'    => $reviews,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Bắt lỗi CSDL (ví dụ: thiếu bảng product_reviews, product_variants...)
    http_response_code(500);
    echo json_encode([
        'error'   => $e->getMessage(), 
        'product' => $product, // Vẫn trả về sản phẩm gốc nếu có
        'variants'   => $variants,
        'attributes' => $attributes,
        'images'     => $images,
        'tags'       => $tags,
        'reviews'    => $reviews
    ], JSON_UNESCAPED_UNICODE);
}