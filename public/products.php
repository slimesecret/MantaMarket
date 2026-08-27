<?php
session_start();
// ============================================================
// CONFIG KẾT NỐI DATABASE
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'mantamarket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);
$product_id = intval($_GET['id'] ?? 2);
// ============================================================
// KẾT NỐI PDO
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#A32D2D;background:#FCEBEB;
         border-radius:8px;margin:2rem;border:1px solid #F09595">
         <strong>Lỗi kết nối database:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>
         Kiểm tra lại <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code> ở đầu file.
    </div>');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating  = max(1, min(5, intval($_POST['rating'] ?? 5)));
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $user_id = 1;
    if ($content !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO reviews (
                product_id, user_id, rating, title, content,
                is_approved, is_verified_purchase, helpful_count, created_at
            ) VALUES (?, ?, ?, ?, ?, 1, 0, 0, NOW())
        ");
        $stmt->execute([$product_id, $user_id, $rating, $title, $content]);
    }
    // Trả về JSON, không redirect
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
// Trả về danh sách reviews dạng JSON
if (isset($_GET['action']) && $_GET['action'] === 'get_reviews') {
    $stmt = $pdo->prepare("
        SELECT r.*,
               COALESCE(u.full_name, u.username, CONCAT('Khách hàng #', r.user_id)) AS reviewer_name,
               pv.color AS variant_color,
               pv.size  AS variant_size
        FROM   reviews r
        LEFT JOIN users            u  ON u.id  = r.user_id
        LEFT JOIN product_variants pv ON pv.id = r.variant_id
        WHERE  r.product_id = ? AND r.is_approved = 1
        ORDER  BY r.helpful_count DESC, r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$product_id]);
    $rv_list = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($rv_list, JSON_UNESCAPED_UNICODE);
    exit;
}
// ============================================================
// QUERY: SẢN PHẨM + BRAND + SELLER + CATEGORY
// ============================================================
$stmt = $pdo->prepare("
    SELECT p.*,
           b.name       AS brand_name,
           s.shop_name  AS seller_name,
           s.shop_name  AS seller_shop,
           c.name       AS category_name,
           c.slug       AS category_slug
    FROM   products p
    LEFT JOIN brands     b ON b.id = p.brand_id
    LEFT JOIN sellers    s ON s.id = p.seller_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE  p.id = ?
    LIMIT 1
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#555">
         Không tìm thấy sản phẩm ID = ' . $product_id . '
    </div>');
}
// ============================================================
// QUERY: HÌNH ẢNH
// ============================================================
$stmt = $pdo->prepare("
    SELECT pi.*, pv.color AS variant_color
    FROM   product_images pi
    LEFT JOIN product_variants pv ON pv.id = pi.variant_id
    WHERE  pi.product_id = ?
    ORDER  BY pi.is_primary DESC, pi.sort_order ASC
");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();
// ============================================================
// QUERY: BIẾN THỂ
// ============================================================
$stmt = $pdo->prepare("
    SELECT pv.*,
           COALESCE(MIN(pi.sort_order), 9999) AS img_sort
    FROM   product_variants pv
    LEFT JOIN product_images pi ON pi.variant_id = pv.id AND pi.product_id = pv.product_id
    WHERE  pv.product_id = ?
    GROUP  BY pv.id
    ORDER  BY img_sort ASC, pv.id ASC
");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();
$colors_list = [];
$sizes_list  = [];
foreach ($variants as $v) {
    $c  = $v['color'] ?? '';
    $sz = $v['size']  ?? '';
    if ($c  && !in_array($c,  $colors_list)) $colors_list[] = $c;
    if ($sz && !in_array($sz, $sizes_list))  $sizes_list[]  = $sz;
}
// Tìm variant mặc định
$primary_img = null;
foreach ($images as $img) {
    if ((int)$img['is_primary']) {
        $primary_img = $img;
        break;
    }
}
$default_v = null;
if ($primary_img && $primary_img['variant_id']) {
    foreach ($variants as $v) {
        if ((int)$v['id'] === (int)$primary_img['variant_id'] && (int)$v['is_active']) {
            $default_v = $v;
            break;
        }
    }
}
if (!$default_v) {
    foreach ($variants as $v) {
        if ((int)$v['is_active'] && (int)$v['stock_quantity'] > 0) {
            $default_v = $v;
            break;
        }
    }
}
if (!$default_v) $default_v = $variants[0] ?? [];
$def_price   = (float)($default_v['price']          ?? $product['base_price']);
$def_compare = (float)($default_v['compare_price']   ?? 0);
$def_stock   = (int)  ($default_v['stock_quantity']  ?? 0);
$def_alert   = (int)  ($default_v['low_stock_alert'] ?? 5);
$def_sku     = $default_v['sku']   ?? '';
$def_color   = $default_v['color'] ?? ($colors_list[0] ?? '');
$def_size    = $default_v['size']  ?? ($sizes_list[0]  ?? '');
function fmtPrice(float $n): string
{
    return number_format($n, 0, ',', '.') . 'đ';
}
function discPct(float $p, float $c): int
{
    return ($c > $p) ? (int)round((1 - $p / $c) * 100) : 0;
}
// ============================================================
// QUERY: THUỘC TÍNH
// ============================================================
$stmt = $pdo->prepare("
    SELECT * FROM product_attributes
    WHERE  product_id = ?
    ORDER  BY sort_order ASC
");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll();
// ============================================================
// QUERY: TAGS
// ============================================================
$stmt = $pdo->prepare("SELECT tag FROM product_tags WHERE product_id = ? ORDER BY id ASC");
$stmt->execute([$product_id]);
$tags = array_column($stmt->fetchAll(), 'tag');
// ============================================================
// QUERY: SẢN PHẨM LIÊN QUAN
// ============================================================
$related = [];
if ($product['category_id']) {
    $stmt = $pdo->prepare("
    SELECT  p.id, p.name, p.base_price, p.avg_rating, p.sold_count,
            p.is_featured,
            b.name AS brand_name,
            pi.image_url
    FROM   products p
    LEFT JOIN brands b ON b.id = p.brand_id
    LEFT JOIN product_images pi
            ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE  p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER  BY p.sold_count DESC
    LIMIT 8
");
    $stmt->execute([$product['category_id'], $product_id]);
    $related = $stmt->fetchAll();
}
$pct = discPct($def_price, $def_compare);
// Build map: color => first image URL
$colorImgMap = [];
foreach ($images as $img) {
    $c = $img['variant_color'] ?? '';
    if ($c && !isset($colorImgMap[$c])) {
        $colorImgMap[$c] = $img['image_url'];
    }
}
// Primary image URL
$primary_img_url = '';
$primary_img_alt = '';
if ($primary_img && $primary_img['image_url']) {
    $primary_img_url = $primary_img['image_url'];
    $primary_img_alt = $primary_img['alt_text'] ?? $product['name'];
} elseif ($images) {
    $primary_img_url = $images[0]['image_url'] ?? '';
    $primary_img_alt = $images[0]['alt_text']  ?? $product['name'];
}
if ($def_color && isset($colorImgMap[$def_color])) {
    $primary_img_url = $colorImgMap[$def_color];
}
$variant_map_json = json_encode(array_values(array_map(function ($v) {
    return [
        'id'            => (int)$v['id'],   // ← THÊM DÒNG NÀY
        'key'           => ($v['color'] ?? '') . '|' . ($v['size'] ?? ''),
        'sku'           => $v['sku'],
        'price'         => (float)$v['price'],
        'compare_price' => (float)($v['compare_price'] ?? 0),
        'stock'         => (int)$v['stock_quantity'],
        'alert'         => (int)$v['low_stock_alert'],
        'is_active'     => (bool)$v['is_active'],
    ];
}, $variants)), JSON_UNESCAPED_UNICODE);
$color_img_json = json_encode($colorImgMap, JSON_UNESCAPED_UNICODE);
// ============================================================
// QUERY: REVIEWS
// ============================================================
$stmt = $pdo->prepare("
    SELECT r.*,
            u.full_name AS user_name
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ?
    AND r.is_approved = 1
    ORDER BY r.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();
// TÍNH RATING TRUNG BÌNH
$avg_rating = 0;
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / $total_reviews, 1);
}
$reviews     = [];
$rating_dist = array_fill(1, 5, 0);
try {
    $stmt = $pdo->prepare("
        SELECT  r.*,
                COALESCE(u.full_name, u.username, CONCAT('Khách hàng #', r.user_id)) AS reviewer_name,
                pv.color AS variant_color,
                pv.size  AS variant_size
        FROM   reviews r
        LEFT JOIN users            u  ON u.id  = r.user_id
        LEFT JOIN product_variants pv ON pv.id = r.variant_id
        WHERE  r.product_id = ? AND r.is_approved = 1
        ORDER  BY r.helpful_count DESC, r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("
            SELECT  r.*,
                    CONCAT('Khách hàng #', r.user_id) AS reviewer_name,
                    pv.color AS variant_color,
                    pv.size  AS variant_size
            FROM   reviews r
            LEFT JOIN product_variants pv ON pv.id = r.variant_id
            WHERE  r.product_id = ? AND r.is_approved = 1
            ORDER  BY r.helpful_count DESC, r.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$product_id]);
        $reviews = $stmt->fetchAll();
    } catch (PDOException $e2) { /* bảng reviews chưa tồn tại */
    }
}
foreach ($reviews as $rv) {
    $star = max(1, min(5, (int)$rv['rating']));
    $rating_dist[$star]++;
}
// ── Helpers ──
$seller_display = $product['seller_shop'] ?: ($product['seller_name'] ?: 'Seller #' . $product['seller_id']);
$brand_display  = $product['brand_name'] ?: '—';
$pct            = discPct($def_price, $def_compare);






// ============================================================
// QUERY: THÔNG TIN SELLER CHI TIẾT
// ============================================================
$seller_info = [];
if ($product['seller_id']) {
    $stmt = $pdo->prepare("
    SELECT s.*,
           s.shop_slug,
           COUNT(DISTINCT p.id)                                    AS total_products,
           SUM(p.sold_count)                                       AS total_sold,
           AVG(p.avg_rating)                                       AS shop_rating,
           SUM(p.review_count)                                     AS total_reviews,
           -- Tỉ lệ phản hồi = số review có reply / tổng review * 100
           COUNT(DISTINCT r.id)                                    AS rv_total,
           COUNT(DISTINCT CASE WHEN r.reply IS NOT NULL AND r.reply != '' THEN r.id END) AS rv_replied
    FROM   sellers s
    LEFT JOIN products p  ON p.seller_id = s.id AND p.status = 'active'
    LEFT JOIN reviews  r  ON r.product_id = p.id AND r.is_approved = 1
    WHERE  s.id = ?
    GROUP  BY s.id
    LIMIT 1
");
    $stmt->execute([$product['seller_id']]);
    $seller_info = $stmt->fetch() ?: [];
}
?>








<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($product['name']) ?> — MantaMarket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/homeproduct.css" />
</head>

<body>
    <div class="crumb">
        <a href="javascript:void(0)" onclick="closeSpPanel()"
            style="color:inherit;text-decoration:none;">Trang chủ</a> &rsaquo;
        <?php
        $cat_name = $product['category_name'] ?? '';
        $cat_slug = $product['category_slug'] ?? '';
        if ($cat_name && $cat_slug): ?>
            <a href="index.php?slug=<?= htmlspecialchars($cat_slug) ?>"
                style="color:inherit;text-decoration:none;">
                <?= htmlspecialchars($cat_name) ?>
            </a> &rsaquo;
        <?php elseif ($cat_name): ?>
            <?= htmlspecialchars($cat_name) ?> &rsaquo;
        <?php endif; ?>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>
    <div class="container pg">
        <!-- LAYOUT CHÍNH -->
        <div class="layout">
            <!-- GALLERY -->
            <div class="gallery">
                <div class="main-img" id="mainImg">
                    <?php if ($primary_img_url): ?>
                        <img id="mainImgEl"
                            src="<?= htmlspecialchars($primary_img_url) ?>"
                            alt="<?= htmlspecialchars($primary_img_alt) ?>">
                    <?php else: ?>
                        <svg width="180" height="180" viewBox="0 0 180 180" fill="none">
                            <rect x="48" y="10" width="76" height="158" rx="14" fill="#888780" stroke="#5F5E5A" stroke-width="1.5" />
                            <rect x="52" y="14" width="68" height="150" rx="11" fill="#2C2C2A" />
                            <rect x="57" y="35" width="58" height="100" rx="4" fill="#1D9E75" opacity=".14" />
                            <rect x="64" y="55" width="44" height="5" rx="2" fill="#9FE1CB" opacity=".5" />
                            <rect x="64" y="67" width="32" height="3" rx="2" fill="#9FE1CB" opacity=".3" />
                            <rect x="64" y="77" width="38" height="3" rx="2" fill="#9FE1CB" opacity=".3" />
                            <circle cx="86" cy="22" r="3" fill="#444" />
                            <rect x="53" y="15" width="20" height="20" rx="4" fill="#333" />
                            <circle cx="63" cy="25" r="5" fill="#111" stroke="#555" stroke-width="1" />
                            <rect x="124" y="58" width="4" height="62" rx="2" fill="#5DCAA5" opacity=".65" />
                        </svg>
                    <?php endif; ?>
                </div>
                <!-- THUMBNAILS — sắp xếp theo sort_order (đã ORDER BY trong query) -->
                <?php if (count($images) > 1): ?>
                    <div class="thumb-row">
                        <?php foreach ($images as $i => $img):
                            // Thumb active = ảnh của màu mặc định
                            $isActive = ($img['variant_color'] === $def_color) || ($i === 0 && !$def_color);
                        ?>
                            <div class="thumb <?= $isActive ? 'active' : '' ?>"
                                data-color="<?= htmlspecialchars($img['variant_color'] ?? '') ?>"
                                onclick="setThumb(this,
                  '<?= htmlspecialchars(addslashes($img['image_url'])) ?>',
                  '<?= htmlspecialchars(addslashes($img['alt_text'] ?? '')) ?>')">
                                <?php if ($img['image_url']): ?>
                                    <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="<?= htmlspecialchars($img['alt_text'] ?? '') ?>">
                                <?php else: ?>
                                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                                        <rect x="9" y="2" width="14" height="28" rx="3" fill="#888780" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- INFO -->
            <div class="info">
                <!-- BADGES -->
                <div class="badge-row">
                    <span class="b-mall badge">Mall</span>
                    <?php
                    $sLabel = ['active' => 'Đang bán', 'inactive' => 'Đang ẩn', 'draft' => 'Bản nháp', 'banned' => 'Bị khoá'];
                    $sCls   = ['active' => 'b-active', 'inactive' => 'b-inactive', 'draft' => 'b-inactive', 'banned' => 'b-out'];
                    ?>
                    <span class="badge <?= $sCls[$product['status']] ?? 'b-inactive' ?>">
                        <?= $sLabel[$product['status']] ?? htmlspecialchars($product['status']) ?>
                    </span>
                    <?php if ((int)$product['is_featured']): ?>
                        <span class="badge b-featured">★ Nổi bật</span>
                    <?php endif; ?>
                </div>
                <div class="prod-name"><?= htmlspecialchars($product['name']) ?></div>
                <!-- RATING ROW -->
                <div class="rating-row">
                    <?php $avgR = (float)$product['avg_rating']; ?>
                    <span class="rating-val"><?= number_format($avgR, 1) ?></span>
                    <span class="stars">
                        <?php for ($i = 1; $i <= 5; $i++) echo $avgR >= $i ? '★' : ($avgR >= $i - .5 ? '★' : '☆'); ?>
                    </span>
                    <div class="rating-sep"></div>
                    <span><?= number_format((int)$product['review_count']) ?> Đánh Giá</span>
                    <div class="rating-sep"></div>
                    <span class="sold-txt"><?= number_format((int)$product['sold_count']) ?> Đã Bán</span>
                </div>
                <!-- GIÁ -->
                <div class="price-box">
                    <span class="price-main" id="priceMain"><?= fmtPrice($def_price) ?></span>
                    <span class="price-compare" id="priceCmp" <?= $def_compare <= $def_price ? 'style="display:none"' : '' ?>>
                        <?= $def_compare > $def_price ? fmtPrice($def_compare) : '' ?>
                    </span>
                    <span class="price-badge" id="pricePct" <?= $pct <= 0 ? 'style="display:none"' : '' ?>>
                        <?= $pct > 0 ? '-' . $pct . '%' : '' ?>
                    </span>
                </div>
                <!-- VOUCHER -->
                <div class="voucher-row">
                    <span class="lbl">Mã Giảm Giá Của Shop</span>
                    <span class="voucher-tag">Giảm 1kđ</span>
                </div>
                <!-- CHỌN MÀU -->
                <?php if ($colors_list): ?>
                    <div class="variant-section">
                        <div class="variant-row">
                            <span class="variant-lbl">Màu Sắc</span>
                            <div class="variant-opts" id="colorChips">
                                <?php foreach ($colors_list as $c):
                                    $imgUrl = $colorImgMap[$c] ?? '';
                                ?>
                                    <div class="color-chip-shopee <?= $c === $def_color ? 'active' : '' ?>"
                                        data-color="<?= htmlspecialchars($c) ?>"
                                        onclick="selectColor('<?= htmlspecialchars(addslashes($c)) ?>',this)">
                                        <?php if ($imgUrl): ?>
                                            <img class="color-thumb"
                                                src="<?= htmlspecialchars($imgUrl) ?>"
                                                alt="<?= htmlspecialchars($c) ?>"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                            <div class="color-thumb-placeholder" style="display:none"></div>
                                        <?php else: ?>
                                            <div class="color-thumb-placeholder"></div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($c) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- CHỌN SIZE -->
                        <?php if ($sizes_list): ?>
                            <div class="variant-row">
                                <span class="variant-lbl">Dung Lượng</span>
                                <div class="variant-opts" id="sizeChips">
                                    <?php foreach ($sizes_list as $sz):
                                        $hasStock = false;
                                        foreach ($variants as $v) {
                                            if ($v['size'] === $sz && (int)$v['is_active'] && (int)$v['stock_quantity'] > 0) {
                                                $hasStock = true;
                                                break;
                                            }
                                        }
                                    ?>
                                        <div class="size-chip <?= $sz === $def_size ? 'active' : '' ?> <?= !$hasStock ? 'out' : '' ?>"
                                            data-size="<?= htmlspecialchars($sz) ?>"
                                            onclick="selectSize('<?= htmlspecialchars(addslashes($sz)) ?>',this)">
                                            <?= htmlspecialchars($sz) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <!-- SỐ LƯỢNG / TỒN KHO -->
                <div class="qty-section">
                    <span class="qty-lbl">Số Lượng</span>
                    <span class="stock-txt" id="stockLabel">
                        <?php if ($def_stock <= 0): ?>
                            <span style="color:#ee4d2d">Hết hàng</span>
                        <?php elseif ($def_stock <= $def_alert): ?>
                            <span style="color:#e5a000">Còn <?= $def_stock ?> sản phẩm</span>
                        <?php else: ?>
                            <?= number_format($def_stock) ?> sản phẩm có sẵn
                        <?php endif; ?>
                    </span>
                </div>
                <!-- ACTION BUTTONS -->
                <div class="action-row">
                    <button class="btn-cart" id="btnCart"
                        onclick="addToCart(<?= $product_id ?>, <?= $default_v['id'] ?? 'null' ?>)"
                        <?= $def_stock <= 0 ? 'disabled' : '' ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <path d="M16 10a4 4 0 01-8 0" />
                        </svg>
                        <?= $def_stock <= 0 ? 'Hết Hàng' : 'Thêm Vào Giỏ Hàng' ?>
                    </button>
                </div>
                <!-- GUARANTEE -->
                <div class="guarantee-row">
                    <span class="lbl">An Tâm Mua Sắm</span>
                    <div class="guarantee-tags">
                        <span class="gtag">Trả hàng miễn phí 15 ngày</span>
                        <span class="gtag">Chính hãng 100%</span>
                        <span class="gtag">Miễn phí vận chuyển</span>
                    </div>
                </div>
                <!-- META -->
                <div class="meta-row">
                    <span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        <?= htmlspecialchars($seller_display) ?>
                    </span>
                    <span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        Cập nhật: <?= date('d/m/Y', strtotime($product['updated_at'])) ?>
                    </span>
                    <span>SKU: <span id="curSku"><?= htmlspecialchars($def_sku) ?></span></span>
                </div>
            </div><!-- end .info -->
        </div><!-- end .layout -->
        <!-- TABS -->



        <!-- SELLER CARD -->
        <?php if ($seller_info):
            $joined_at = $seller_info['created_at'] ?? null;
            $years_ago = '';
            if ($joined_at) {
                $diff = (new DateTime())->diff(new DateTime($joined_at));
                $years_ago = $diff->y > 0 ? $diff->y . ' năm trước' : ($diff->m . ' tháng trước');
            }
            $rv_total   = (int)($seller_info['rv_total']   ?? 0);
            $rv_replied = (int)($seller_info['rv_replied'] ?? 0);
            $response_rate = $rv_total > 0
                ? round($rv_replied / $rv_total * 100)
                : 0;
            $response_time = $seller_info['response_time'] ?? 'trong vài giờ';
            $followers     = $seller_info['follower_count'] ?? 0;
            $total_reviews_shop = (int)($seller_info['total_reviews'] ?? 0);
            $total_products_shop = (int)($seller_info['total_products'] ?? 0);
            $is_mall       = (int)($seller_info['is_mall'] ?? 0);
        ?>
            <div class="seller-card">
                <div class="seller-left">
                    <div class="seller-avatar">
                        <?php if (!empty($seller_info['avatar'])): ?>
                            <img src="<?= htmlspecialchars($seller_info['avatar']) ?>" alt="<?= htmlspecialchars($seller_info['shop_name']) ?>">
                        <?php else: ?>
                            <div class="seller-avatar-placeholder"><?= mb_strtoupper(mb_substr($seller_info['shop_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="seller-name-wrap">
                        <div class="seller-shop-name"><?= htmlspecialchars($seller_info['shop_name']) ?></div>

                        <?php if ($is_mall): ?><span class="seller-mall-badge">Shopee Mall</span><?php endif; ?>
                        <div class="seller-actions">

                            <button class="seller-btn-view"
                                onclick="window.location.href='index.php?seller=<?= htmlspecialchars($seller_info['shop_slug'] ?? '') ?>'">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                </svg>
                                Xem Shop
                            </button>
                        </div>
                    </div>
                </div>
                <div class="seller-stats">
                    <div class="seller-stat-col">
                        <div class="ss-item">
                            <span class="ss-label">Đánh Giá</span>
                            <span class="ss-val red">
                                <?= number_format((float)($seller_info['shop_rating'] ?? 0), 1) ?> ★
                            </span>
                        </div>
                        <div class="ss-item">
                            <span class="ss-label">Sản Phẩm</span>
                            <span class="ss-val red"><?= number_format($total_products_shop) ?></span>
                        </div>
                    </div>
                    <div class="seller-stat-col">
                        <div class="ss-item">
                            <span class="ss-label">Tỉ Lệ Phản Hồi</span>
                            <span class="ss-val red"><?= $response_rate ?>%</span>
                        </div>
                        <div class="ss-item">
                            <span class="ss-label">Thời Gian Phản Hồi</span>
                            <span class="ss-val red"><?= htmlspecialchars($response_time) ?></span>
                        </div>
                    </div>
                    <div class="seller-stat-col">
                        <div class="ss-item">
                            <span class="ss-label">Tham Gia</span>
                            <span class="ss-val red"><?= $years_ago ?: '—' ?></span>
                        </div>

                    </div>
                </div>
            </div>
        <?php endif; ?>








        <div class="tabs-wrap">
            <div class="tabs-bar">
                <div class="tab active" onclick="switchTab('desc',this)">Mô tả</div>
                <div class="tab" onclick="switchTab('attrs',this)">Thông số kỹ thuật</div>
                <div class="tab" onclick="switchTab('variants',this)">Biến thể &amp; Kho (<?= count($variants) ?>)</div>
                <div class="tab" onclick="switchTab('tags',this)">Tags (<?= count($tags) ?>)</div>
                <div class="tab" onclick="switchTab('reviews',this)">Đánh giá (<?= $total_reviews ?>)</div>
            </div>
            <div class="tab-panel">
                <!-- MÔ TẢ -->
                <div class="tab-content active" id="tab-desc">
                    <?php if ($product['description']): ?>
                        <p style="font-size:14px;line-height:1.85;color:#555;max-width:760px">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </p>
                    <?php else: ?>
                        <div class="empty">Chưa có mô tả.</div>
                    <?php endif; ?>
                </div>
                <!-- THÔNG SỐ -->
                <div class="tab-content" id="tab-attrs">
                    <?php if ($attributes): ?>
                        <table class="attr-table">
                            <tbody>
                                <?php foreach ($attributes as $a): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($a['attr_name']) ?></td>
                                        <td><?= htmlspecialchars($a['attr_value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty">Chưa có thông số kỹ thuật.</div>
                    <?php endif; ?>
                </div>
                <!-- BIẾN THỂ -->
                <div class="tab-content" id="tab-variants">
                    <?php if ($variants): ?>
                        <div style="overflow-x:auto">
                            <table class="vt">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Màu sắc</th>
                                        <th>Size</th>
                                        <th>Chất liệu</th>
                                        <th>Giá bán</th>
                                        <th>Giá g.ngang</th>
                                        <th>Giá vốn</th>
                                        <th>Tồn kho</th>
                                        <th>Trọng lượng</th>
                                        <th>Barcode</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($variants as $v):
                                        $vp  = (float)$v['price'];
                                        $vc  = (float)($v['compare_price'] ?? 0);
                                        $vst = (int)$v['stock_quantity'];
                                        $val = (int)$v['low_stock_alert'];
                                        $isLow = $vst > 0 && $vst <= $val;
                                        $isOut = $vst <= 0;
                                    ?>
                                        <tr>
                                            <td style="font-family:monospace;font-size:11px;white-space:nowrap"><?= htmlspecialchars($v['sku']) ?></td>
                                            <td><?= $v['color'] ? htmlspecialchars($v['color']) : '—' ?></td>
                                            <td><?= htmlspecialchars($v['size'] ?? '—') ?></td>
                                            <td style="font-size:12px;color:#aaa;max-width:140px"><?= htmlspecialchars($v['material'] ?? '—') ?></td>
                                            <td style="font-weight:500;color:#533AB7;white-space:nowrap"><?= fmtPrice($vp) ?></td>
                                            <td style="color:#bbb;white-space:nowrap;<?= $vc > $vp ? 'text-decoration:line-through' : '' ?>"><?= $vc > 0 ? fmtPrice($vc) : '—' ?></td>
                                            <td style="color:#bbb;white-space:nowrap"><?= $v['cost_price'] ? fmtPrice((float)$v['cost_price']) : '—' ?></td>
                                            <td style="white-space:nowrap">
                                                <?php if ($isOut): ?><span class="s-out">● 0 — Hết</span>
                                                <?php elseif ($isLow): ?><span class="s-low">● <?= $vst ?> — Sắp hết!</span>
                                                <?php else: ?><span class="s-ok">● <?= number_format($vst) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap"><?= $v['weight'] ? number_format((float)$v['weight'], 3) . ' kg' : '—' ?></td>
                                            <td style="font-family:monospace;font-size:11px;color:#bbb"><?= htmlspecialchars($v['barcode'] ?? '—') ?></td>
                                            <td>
                                                <?php if (!(int)$v['is_active']): ?><span class="badge b-inactive">Ngừng bán</span>
                                                <?php elseif ($isOut): ?><span class="badge b-out">Hết hàng</span>
                                                <?php elseif ($isLow): ?><span class="badge b-featured">Sắp hết</span>
                                                <?php else: ?><span class="badge b-active">Đang bán</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty">Chưa có biến thể nào.</div>
                    <?php endif; ?>
                </div>
                <!-- TAGS -->
                <div class="tab-content" id="tab-tags">
                    <?php if ($tags): ?>
                        <div><?php foreach ($tags as $tag): ?><span class="tag"><?= htmlspecialchars($tag) ?></span><?php endforeach; ?></div>
                    <?php else: ?>
                        <div class="empty">Chưa có tags.</div>
                    <?php endif; ?>
                </div>
                <!-- ĐÁNH GIÁ -->
                <div class="tab-content" id="tab-reviews">
                    <div class="db-note">
                        Nguồn: <code>reviews</code> · product_id = <?= $product_id ?>
                        · avg_rating = <?= number_format((float)$product['avg_rating'], 2) ?>
                        · review_count = <?= number_format((int)$product['review_count']) ?>
                    </div>
                    <div class="rv-summary">
                        <div style="text-align:center;flex-shrink:0">
                            <div class="rv-big"><?= number_format((float)$product['avg_rating'], 2) ?></div>
                            <div class="stars" style="font-size:20px">
                                <?php $r = (float)$product['avg_rating'];
                                for ($i = 1; $i <= 5; $i++) echo $r >= $i ? '★' : ($r >= $i - 0.5 ? '★' : '☆'); ?>
                            </div>
                            <div style="font-size:12px;color:#bbb;margin-top:6px"><?= number_format((int)$product['review_count']) ?> đánh giá</div>
                        </div>
                        <div style="flex:1;min-width:0">
                            <?php
                            $totalRv   = array_sum($rating_dist);
                            $barColors = [5 => '#533AB7', 4 => '#7F77DD', 3 => '#AFA9EC', 2 => '#CECBF6', 1 => '#D3D1C7'];
                            $distEst   = [5 => 72, 4 => 17, 3 => 7, 2 => 3, 1 => 1];
                            foreach ([5, 4, 3, 2, 1] as $s):
                                $pctBar = $totalRv > 0 ? (int)round($rating_dist[$s] / $totalRv * 100) : $distEst[$s];
                            ?>
                                <div class="rv-bar-row">
                                    <span style="width:22px;text-align:right;font-size:12px"><?= $s ?>★</span>
                                    <div class="rv-bar-bg">
                                        <div class="rv-bar-fill" style="width:<?= $pctBar ?>%;background:<?= $barColors[$s] ?>"></div>
                                    </div>
                                    <span style="width:32px;font-size:12px"><?= $pctBar ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="review-form-box">
                        <h3>Gửi đánh giá của bạn</h3>
                        <!-- Div thông báo - LUÔN có mặt trong DOM -->
                        <div id="reviewMsg" style="display:none;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;"></div>
                        <form method="POST" id="reviewForm">
                            <div class="review-stars-input">
                                <label>Chọn số sao:</label>
                                <select name="rating" required>
                                    <option value="5">5 ★★★★★</option>
                                    <option value="4">4 ★★★★</option>
                                    <option value="3">3 ★★★</option>
                                    <option value="2">2 ★★</option>
                                    <option value="1">1 ★</option>
                                </select>
                            </div>
                            <input type="text" name="title" placeholder="Tiêu đề đánh giá" class="review-input">
                            <textarea name="content" rows="5" placeholder="Chia sẻ trải nghiệm của bạn..." required class="review-textarea"></textarea>
                            <button type="button" onclick="submitReview()" class="review-submit-btn">
                                Gửi đánh giá
                            </button>
                        </form>
                    </div>
                    <div id="reviewsList">
                        <?php if ($reviews): ?>
                            <?php foreach ($reviews as $rv):
                                $varInfo = trim(($rv['variant_color'] ?? '') . ' ' . ($rv['variant_size'] ?? ''));
                            ?>
                                <div class="rv-card">
                                    <div class="rv-head">
                                        <div>
                                            <div class="rv-name"><?= htmlspecialchars($rv['reviewer_name']) ?></div>
                                            <div class="rv-star"><?= str_repeat('★', (int)$rv['rating']) ?><?= str_repeat('☆', 5 - (int)$rv['rating']) ?></div>
                                        </div>
                                        <div class="rv-meta">
                                            <?= date('d/m/Y', strtotime($rv['created_at'])) ?>
                                            <?php if ($varInfo): ?><br>Đã mua: <?= htmlspecialchars($varInfo) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($rv['title']): ?><div class="rv-title"><?= htmlspecialchars($rv['title']) ?></div><?php endif; ?>
                                    <?php if ($rv['content']): ?><div class="rv-text"><?= nl2br(htmlspecialchars($rv['content'])) ?></div><?php endif; ?>
                                    <div class="rv-badges">
                                        <?php if ((int)$rv['is_verified_purchase']): ?><span class="rv-badge v">✓ Đã mua hàng xác nhận</span><?php endif; ?>
                                        <?php if ((int)$rv['helpful_count'] > 0): ?><span class="rv-badge"><?= $rv['helpful_count'] ?> người thấy hữu ích</span><?php endif; ?>
                                    </div>
                                    <?php if ($rv['reply']): ?>
                                        <div class="rv-reply">
                                            <strong>Phản hồi từ người bán <?= $rv['replied_at'] ? '· ' . date('d/m/Y', strtotime($rv['replied_at'])) : '' ?></strong>
                                            <?= nl2br(htmlspecialchars($rv['reply'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-rv" id="noRvMsg">Chưa có đánh giá nào.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- end .tabs-wrap -->
            <hr class="divider">
            <!-- SẢN PHẨM LIÊN QUAN -->
            <?php if ($related): ?>
                <div class="sec-title">Sản phẩm liên quan</div>
                <div class="products-grid">


                    <?php foreach ($related as $p): ?>
                        <div class="product-card" onclick="openSpPanel(<?= $p['id'] ?>)">
                            <div class="product-card-inner">
                                <div class="product-img">
                                    <?php if (!empty($p['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                    <?php else: ?>
                                        <div class="no-img">No Image</div>
                                    <?php endif; ?>
                                    <?php if ($p['sold_count'] >= 2000): ?>
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
                </div>




            <?php endif; ?>
        </div><!-- end .container -->
        <script>
            const IS_LOGGED_IN = <?= isset($_SESSION['userId']) ? 'true' : 'false' ?>;

            function handleBuyNow(productId) {
                // Nếu chưa login → hiện popup login (nếu có) rồi dừng
                if (!IS_LOGGED_IN) {
                    if (typeof showLoginPrompt === 'function') {
                        showLoginPrompt('Bạn cần đăng nhập để mua hàng!');
                    } else {
                        alert('Bạn cần đăng nhập để mua hàng!');
                    }
                    return;
                }
                // Lấy địa chỉ ví từ nhiều nguồn
                const addr = window.walletAddress ||
                    (window.top && window.top.walletAddress) ||
                    localStorage.getItem('walletAddress');
                if (!addr) {
                    showWalletPrompt();
                    return;
                }
                buyWithNFT(productId);
            }

            function showWalletPrompt() {
                const old = document.getElementById('walletPromptOverlay');
                if (old) old.remove();
                const overlay = document.createElement('div');
                overlay.id = 'walletPromptOverlay';
                overlay.style.cssText = `
        position:fixed; inset:0; background:rgba(0,0,0,.75);
        display:flex; align-items:center; justify-content:center;
        z-index:99999;
    `;
                overlay.innerHTML = `
        <div style="
            background:#fff; border-radius:16px; padding:36px 32px;
            max-width:380px; width:90%; text-align:center;
            box-shadow:0 12px 40px rgba(0,0,0,.2);
        ">
            <div style="font-size:52px; margin-bottom:12px">💳</div>
            <h3 style="margin:0 0 10px; font-size:17px; color:#222; font-weight:700">
                Vui lòng kết nối ví
            </h3>
            <p style="margin:0 0 28px; color:#666; font-size:14px; line-height:1.6">
                Bạn cần kết nối ví để thực hiện giao dịch này
            </p>
            <div style="display:flex; gap:10px; justify-content:center">
                <button onclick="document.getElementById('walletPromptOverlay').remove()"
                    style="padding:10px 22px; border:1.5px solid #ddd; border-radius:8px;
                           background:#fff; cursor:pointer; font-size:14px; color:#555">
                    Để sau
                </button>
                <button onclick="document.getElementById('walletPromptOverlay').remove(); openWallet();"
                    style="padding:10px 22px; background:#ee4d2d; color:#fff; border-radius:8px;
                           border:none; cursor:pointer; font-size:14px; font-weight:600">
                    Kết nối ví
                </button>
            </div>
        </div>
    `;
                overlay.addEventListener('click', e => {
                    if (e.target === overlay) overlay.remove();
                });
                document.body.appendChild(overlay);
            }
        </script>
        <script>
            const variants = <?= $variant_map_json ?>;
            const colorImgMap = <?= $color_img_json ?>;
            const vMap = {};
            variants.forEach(v => {
                vMap[v.key] = v;
            });
            let activeColor = <?= json_encode($def_color) ?>;
            let activeSize = <?= json_encode($def_size) ?>;

            function setThumb(el, url, alt) {
                document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
                if (el) el.classList.add('active');
                if (!url) return;
                let img = document.getElementById('mainImgEl');
                const wrap = document.getElementById('mainImg');
                if (!img) {
                    wrap.innerHTML = '<img id="mainImgEl" style="width:100%;height:100%;object-fit:contain;transition:transform .3s" src="" alt="">';
                    img = document.getElementById('mainImgEl');
                }
                img.src = url;
                img.alt = alt || '';
            }

            function updateUI() {
                const key = activeColor + '|' + activeSize;
                const v = vMap[key];
                if (!v) return;
                const p = v.price,
                    c = v.compare_price || 0;
                document.getElementById('priceMain').textContent = fmtVND(p);
                const cmpEl = document.getElementById('priceCmp');
                const pctEl = document.getElementById('pricePct');
                if (c > p) {
                    cmpEl.textContent = fmtVND(c);
                    cmpEl.style.display = '';
                    pctEl.textContent = '-' + Math.round((1 - p / c) * 100) + '%';
                    pctEl.style.display = '';
                } else {
                    cmpEl.style.display = 'none';
                    pctEl.style.display = 'none';
                }
                const st = v.stock,
                    al = v.alert;
                const isLow = st > 0 && st <= al;
                const isOut = st <= 0;
                const lbl = document.getElementById('stockLabel');
                if (lbl) {
                    lbl.innerHTML = isOut ?
                        '<span style="color:#ee4d2d">Hết hàng</span>' :
                        isLow ?
                        `<span style="color:#e5a000">Còn ${st} sản phẩm</span>` :
                        `${st.toLocaleString('vi-VN')} sản phẩm có sẵn`;
                }
            }

            function selectColor(name, el) {
                document.querySelectorAll('.color-chip-shopee').forEach(c => c.classList.remove('active'));
                el.classList.add('active');
                activeColor = name;
                // lọc size phù hợp với màu
                updateSizeByColor();
                // tìm variant hợp lệ đầu tiên theo màu
                let foundVariant = null;
                for (const v of variants) {
                    const [vc, vs] = v.key.split('|');
                    if (vc === name && v.is_active && v.stock > 0) {
                        foundVariant = v;
                        activeSize = vs;
                        break;
                    }
                }
                // cập nhật active size UI
                if (foundVariant) {
                    document.querySelectorAll('#sizeChips .size-chip').forEach(s => {
                        s.classList.toggle('active', s.dataset.size === activeSize);
                    });
                }
                // đổi ảnh
                const imgUrl = colorImgMap[name];
                if (imgUrl) {
                    setThumb(null, imgUrl, name);
                }
                updateUI();
                updateCartBtn();
            }

            function selectSize(sz, el) {
                if (el.classList.contains('out')) return;
                document.querySelectorAll('#sizeChips .size-chip').forEach(c => c.classList.remove('active'));
                el.classList.add('active');
                activeSize = sz;
                updateUI();
                updateCartBtn();
            }

            function updateCartBtn() {
                const key = activeColor + '|' + activeSize;
                const v = vMap[key];
                const btn = document.getElementById('btnCart');
                if (!btn || !v) return;
                btn.setAttribute('onclick', `addToCart(${<?= $product_id ?>}, ${v.id ?? 'null'})`);
                btn.disabled = v.stock <= 0;
                btn.textContent = v.stock <= 0 ? 'Hết Hàng' : 'Thêm Vào Giỏ Hàng';
            }

            function switchTab(id, el) {
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.getElementById('tab-' + id).classList.add('active');
                el.classList.add('active');
            }

            function fmtVND(n) {
                return n.toLocaleString('vi-VN') + 'đ';
            }

            function updateSizeByColor() {
                document.querySelectorAll('#sizeChips .size-chip').forEach(el => {
                    const sz = el.dataset.size;
                    const key = activeColor + '|' + sz;
                    const v = vMap[key];
                    if (!v || !v.is_active || v.stock <= 0) {
                        el.classList.add('out');
                    } else {
                        el.classList.remove('out');
                    }
                });
            }

            function submitReview() {
                const form = document.getElementById('reviewForm');
                const msg = document.getElementById('reviewMsg');
                const btn = document.querySelector('.review-submit-btn');
                const content = form.querySelector('[name="content"]').value.trim();
                if (!content) {
                    msg.style.cssText = 'display:block;background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;';
                    msg.textContent = '⚠ Vui lòng nhập nội dung đánh giá.';
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Đang gửi...';
                const data = new FormData(form);
                data.append('submit_review', '1');
                fetch('products.php?id=<?= $product_id ?>', {
                        method: 'POST',
                        body: data
                    })
                    .then(res => res.json()) // đổi thành json()
                    .then(json => {
                        if (json.success) {
                            msg.style.cssText = 'display:block;background:#f0fff4;border:1px solid #27a100;color:#27a100;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;';
                            msg.textContent = '✔ Đánh giá của bạn đã được gửi thành công, chờ duyệt!';
                            form.reset();
                        }
                        btn.disabled = false;
                        btn.textContent = 'Gửi đánh giá';
                        msg.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    })
                    .catch(() => {
                        msg.style.cssText = 'display:block;background:#fff0f0;border:1px solid #ee4d2d;color:#ee4d2d;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;';
                        msg.textContent = '✕ Có lỗi xảy ra, vui lòng thử lại.';
                        btn.disabled = false;
                        btn.textContent = 'Gửi đánh giá';
                    });
            }

            function submitReview() {
                const form = document.getElementById('reviewForm');
                const msg = document.getElementById('reviewMsg');
                const btn = document.querySelector('.review-submit-btn');
                const content = form.querySelector('[name="content"]').value.trim();
                if (!content) {
                    showMsg('warning', '⚠ Vui lòng nhập nội dung đánh giá.');
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Đang gửi...';
                const data = new FormData(form);
                data.append('submit_review', '1');
                fetch('products.php?id=<?= $product_id ?>', {
                        method: 'POST',
                        body: data
                    })
                    .then(res => res.json())
                    .then(json => {
                        if (json.success) {
                            showMsg('success', 'Đánh giá thành công!');
                            form.reset();
                            // Load lại danh sách reviews ngay lập tức
                            loadReviews();
                        }
                        btn.disabled = false;
                        btn.textContent = 'Gửi đánh giá';
                        msg.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    })
                    .catch(() => {
                        showMsg('error', '✕ Có lỗi xảy ra, vui lòng thử lại.');
                        btn.disabled = false;
                        btn.textContent = 'Gửi đánh giá';
                    });
            }

            function showMsg(type, text) {
                const msg = document.getElementById('reviewMsg');
                const styles = {
                    success: 'background:#f0fff4;border:1px solid #27a100;color:#27a100;',
                    warning: 'background:#fff3cd;border:1px solid #ffc107;color:#856404;',
                    error: 'background:#fff0f0;border:1px solid #ee4d2d;color:#ee4d2d;'
                };
                msg.style.cssText = `display:block;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;${styles[type]}`;
                msg.textContent = text;
            }

            function loadReviews() {
                fetch('products.php?id=<?= $product_id ?>&action=get_reviews')
                    .then(res => res.json())
                    .then(reviews => {
                        const container = document.getElementById('reviewsList');
                        if (!reviews.length) {
                            container.innerHTML = '<div class="no-rv">Chưa có đánh giá nào được duyệt.</div>';
                            return;
                        }
                        container.innerHTML = reviews.map(rv => {
                            const stars = '★'.repeat(rv.rating) + '☆'.repeat(5 - rv.rating);
                            const varInfo = [rv.variant_color, rv.variant_size].filter(Boolean).join(' ');
                            const date = new Date(rv.created_at).toLocaleDateString('vi-VN');
                            const verified = rv.is_verified_purchase == 1 ?
                                '<span class="rv-badge v">✓ Đã mua hàng xác nhận</span>' : '';
                            const helpful = rv.helpful_count > 0 ?
                                `<span class="rv-badge">${rv.helpful_count} người thấy hữu ích</span>` : '';
                            const reply = rv.reply ?
                                `<div class="rv-reply"><strong>Phản hồi từ người bán</strong><br>${rv.reply}</div>` : '';
                            const title = rv.title ?
                                `<div class="rv-title">${rv.title}</div>` : '';
                            const varHtml = varInfo ?
                                `<br>Đã mua: ${varInfo}` : '';
                            return `
                    <div class="rv-card">
                        <div class="rv-head">
                            <div>
                                <div class="rv-name">${rv.reviewer_name}</div>
                                <div class="rv-star">${stars}</div>
                            </div>
                            <div class="rv-meta">${date}${varHtml}</div>
                        </div>
                        ${title}
                        ${rv.content ? `<div class="rv-text">${rv.content.replace(/\n/g,'<br>')}</div>` : ''}
                        <div class="rv-badges">${verified}${helpful}</div>
                        ${reply}
                    </div>`;
                        }).join('');
                    });
            }
        </script>
</body>

</html>