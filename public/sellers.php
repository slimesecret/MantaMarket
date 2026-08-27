<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mantamarket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('<div style="padding:2rem;color:red">Lỗi kết nối database</div>');
}
// ================= LẤY THÔNG TIN SHOP =================
// Lấy slug từ URL: shop.php?slug=iconic-mens-xuong-may
$shopSlug = trim($_GET['slug'] ?? '');
// Nếu không có slug trong URL → lấy shop active đầu tiên
if (empty($shopSlug)) {
    $stmtFirst = $pdo->query("SELECT shop_slug FROM sellers WHERE is_active = 1 LIMIT 1");
    $firstShop = $stmtFirst->fetchColumn();
    if ($firstShop) {
        $shopSlug = $firstShop;
    }
}
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.shop_name,
        s.shop_slug,
        s.avatar_url,
        s.is_verified,
        s.address,
        s.phone,
        -- Rating trung bình sản phẩm
        COALESCE(AVG(p.avg_rating), 0) AS rating,
        -- Tổng sản phẩm đã bán
        COALESCE(SUM(p.sold_count), 0) AS total_sales
    FROM sellers s
    LEFT JOIN products p 
        ON p.seller_id = s.id
        AND p.status = 'active'
    WHERE s.shop_slug = ?
      AND s.is_active = 1
    GROUP BY s.id
    LIMIT 1
");
$stmt->execute([$shopSlug]);
$shop = $stmt->fetch();
if (!$shop) {
    die("Shop không tồn tại hoặc đã bị khóa.");
}
$sellerId = $shop['id'];
// ================= LẤY THỐNG KÊ BỔ SUNG =================
// Tổng sản phẩm active
$stmtProducts = $pdo->prepare("
    SELECT COUNT(*) AS total FROM products
    WHERE seller_id = ? AND status = 'active'
");
$stmtProducts->execute([$sellerId]);
$totalProducts = $stmtProducts->fetchColumn();
// ================= LẤY SẢN PHẨM =================
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;
$stmtItems = $pdo->prepare("
    SELECT
      p.id,
      p.name,
      p.base_price,
      p.avg_rating,
      p.sold_count,
      p.slug,
      COUNT(DISTINCT pv.id)             AS variant_count,
      COALESCE(SUM(pv.stock_quantity),0) AS total_stock,
      (SELECT image_url FROM product_images
       WHERE product_id = p.id AND is_primary = 1
       LIMIT 1)                         AS primary_image
    FROM products p
    LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
    LEFT JOIN product_images   pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.seller_id = ? AND p.status = 'active'
    GROUP BY p.id
    ORDER BY p.sold_count DESC, p.created_at DESC
    LIMIT ? OFFSET ?
");
// QUAN TRỌNG: Phải bindValue với PARAM_INT cho LIMIT và OFFSET
$stmtItems->bindValue(1, $sellerId, PDO::PARAM_STR); // Hoặc PARAM_INT nếu seller_id là số
$stmtItems->bindValue(2, $limit, PDO::PARAM_INT);
$stmtItems->bindValue(3, $offset, PDO::PARAM_INT);
// Chỉ truyền mảng rỗng hoặc không truyền gì vào execute()
$stmtItems->execute();
$products = $stmtItems->fetchAll();
// Tổng trang
$totalPages = ceil($totalProducts / $limit);
// ================= LẤY VOUCHER =================
$stmtVoucher = $pdo->prepare("
    SELECT code, name, type, value, min_order_value, max_discount, end_date
    FROM coupons
    WHERE seller_id = ? AND is_active = 1 AND end_date >= NOW()
    ORDER BY value DESC
    LIMIT 3
");
$stmtVoucher->execute([$sellerId]);
$vouchers = $stmtVoucher->fetchAll();
// ================= ĐỊNH DẠNG GIÁ =================
function formatPrice($price)
{
    return '₫' . number_format($price, 0, ',', '.');
}
function formatDiscount($type, $value, $minOrder, $maxDiscount = null)
{
    if ($type === 'percent') {
        $label = "Giảm {$value}%";
        if ($maxDiscount > 0) {
            $label .= ' · Tối đa ' . formatPrice($maxDiscount);
        }
        return $label;
    }
    if ($type === 'free_ship') return "Miễn phí vận chuyển";
    return "Giảm " . formatPrice($value);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shop['shop_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/sellers.css" />
</head>

<body>
    <div class="container">
        <!-- ==================== SHOP HEADER ==================== -->
        <div class="shop-header">
            <div class="shop-left">
                <div class="shop-info">
                    <img class="shop-avatar"
                        src="<?= htmlspecialchars($shop['avatar_url'] ?? 'https://i.pinimg.com/736x/0d/f4/6a/0df46a48c2cdd2b574edcb9c8f8d9c27.jpg') ?>"
                        alt="<?= htmlspecialchars($shop['shop_name']) ?>">
                    <div>
                        <div class="shop-name-wrap">
                            <div class="shop-name"><?= htmlspecialchars($shop['shop_name']) ?></div>
                            <?php if ($shop['is_verified']): ?>
                                <span class="verified-badge"><i class="fa-solid fa-check"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="shop-actions">
                    <button class="shop-btn"><i class="fa-solid fa-plus"></i> Theo Dõi</button>
                    <button class="shop-btn"><i class="fa-regular fa-comment"></i> Chat</button>
                </div>
            </div>
            <div class="shop-right">
                <div class="shop-stat">
                    <i class="fa-solid fa-box"></i> Sản Phẩm:
                    <span class="highlight"><?= number_format($totalProducts) ?></span>
                </div>
                <div class="shop-stat">
                    <i class="fa-solid fa-cart-shopping"></i> Đã Bán:
                    <span class="highlight">
                        <?= number_format($shop['total_sales']) ?>
                    </span>
                </div>
                <div class="shop-stat">
                    <i class="fa-regular fa-star"></i> Đánh Giá:
                    <span class="highlight">
                        <?= number_format($shop['rating'], 1) ?>
                    </span>
                </div>
                <div class="shop-stat">
                    <i class="fa-solid fa-location-dot"></i> Địa Chỉ:
                    <span class="highlight"><?= htmlspecialchars($shop['address'] ?? 'Chưa cập nhật') ?></span>
                </div>
                <div class="shop-stat">
                    <i class="fa-solid fa-phone"></i> Hotline:
                    <span class="highlight"><?= htmlspecialchars($shop['phone'] ?? 'Chưa cập nhật') ?></span>
                </div>
                <div class="shop-stat">
                    <i class="fa-solid fa-shield-halved"></i> Trạng Thái:
                    <span class="highlight"><?= $shop['is_verified'] ? 'Đã Xác Thực' : 'Chưa Xác Thực' ?></span>
                </div>
            </div>
        </div>
        <!-- ==================== MENU ==================== -->
        <div class="shop-menu">
            <a href="#" class="active">Dạo</a>
        </div>
        <!-- ==================== VOUCHER ==================== -->
        <?php if ($vouchers): ?>
            <div class="voucher-section">
                <div class="section-title">VOUCHER CỦA SHOP</div>
                <div class="voucher-list">
                    <?php foreach ($vouchers as $v): ?>
                        <div class="voucher-card">
                            <div class="voucher-left">
<div class="voucher-title">
    <?= formatDiscount($v['type'], $v['value'], $v['min_order_value'], $v['max_discount']) ?><br>
    <?php if ($v['min_order_value'] > 0): ?>
        Đơn Tối Thiểu <?= formatPrice($v['min_order_value']) ?>
    <?php else: ?>
        Áp Dụng Mọi Đơn Hàng
    <?php endif; ?>
</div>
                                <div class="voucher-code"><?= htmlspecialchars($v['code']) ?></div>
                                <div class="voucher-date">HSD: <?= date('d.m.Y', strtotime($v['end_date'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>




        <!-- ==================== SẢN PHẨM ==================== -->
        <div class="products-section">
            <div class="products-header">
                <div class="products-title">SẢN PHẨM CỦA SHOP (<?= number_format($totalProducts) ?>)</div>
                <a href="#" class="view-all">Xem Tất Cả <i class="fa-solid fa-angle-right"></i></a>
            </div>
            <div class="product-grid">


                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <i class="fa-solid fa-box-open" style="font-size:48px;margin-bottom:16px;display:block;"></i>
                        Shop chưa có sản phẩm nào.
                    </div>
                <?php else: ?>


<?php foreach ($products as $p): ?>
    <div class="product-card" onclick="openSpPanel(<?= $p['id'] ?>)">
        <div class="product-card-inner">
            <div class="product-img">
                <?php if (!empty($p['primary_image'])): ?>
                    <img src="<?= htmlspecialchars($p['primary_image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                    <div class="no-img">No Image</div>
                <?php endif; ?>
                <?php if ($p['sold_count'] >= 100): ?>
                    <div class="fav-badge">Bán Chạy</div>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <div class="product-name">
                    <?= htmlspecialchars($p['name']) ?>
                </div>
                <div class="product-price">
                    <?= number_format($p['base_price'], 0, ',', '.') ?>đ
                </div>
                <?php if (!empty($p['avg_rating']) && $p['avg_rating'] > 0): ?>
                    <div class="product-rating">
                        <?php
                        $full = floor($p['avg_rating']);
                        $half = ($p['avg_rating'] - $full) >= 0.5;
                        for ($i = 1; $i <= 5; $i++):
                            if ($i <= $full): ?>
                                <span style="color:#f5a623;">★</span>
                            <?php elseif ($half && $i === $full + 1): ?>
                                <span style="color:#f5a623;">★</span>
                            <?php else: ?>
                                <span style="color:#ddd;">★</span>
                            <?php endif;
                        endfor; ?>
                        <span style="font-size:11px; color:#888; margin-left:3px;">
                            <?= number_format($p['avg_rating'], 1) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="product-meta">
                    <span>Đã bán <?= number_format($p['sold_count']) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>







                    
                <?php endif; ?>
            </div>
            <!-- ===== PHÂN TRANG ===== -->
            <?php if ($totalPages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a href="?slug=<?= urlencode($shopSlug) ?>&page=<?= $page - 1 ?>"><i class="fa-solid fa-chevron-left"></i></a></li>
                    <?php else: ?>
                        <li><span class="disabled"><i class="fa-solid fa-chevron-left"></i></span></li>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1) echo '<li><a href="?slug=' . urlencode($shopSlug) . '&page=1">1</a></li>';
                    if ($start > 2) echo '<li><span>...</span></li>';
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li>
                            <?php if ($i === $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?slug=<?= urlencode($shopSlug) ?>&page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages - 1) echo '<li><span>...</span></li>'; ?>
                    <?php if ($end < $totalPages) echo '<li><a href="?slug=' . urlencode($shopSlug) . '&page=' . $totalPages . '">' . $totalPages . '</a></li>'; ?>
                    <?php if ($page < $totalPages): ?>
                        <li><a href="?slug=<?= urlencode($shopSlug) ?>&page=<?= $page + 1 ?>"><i class="fa-solid fa-chevron-right"></i></a></li>
                    <?php else: ?>
                        <li><span class="disabled"><i class="fa-solid fa-chevron-right"></i></span></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>






    </div>

    <script src="../js/sellers.js"></script>

</body>

</html>