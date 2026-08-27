<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mantamarket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

$slug = trim($_GET['slug'] ?? '');

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

// Lấy thông tin danh mục
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    die('<div style="padding:2rem;color:#555">Không tìm thấy danh mục.</div>');
}

// Lấy TẤT CẢ sản phẩm để build filter options
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url,
           b.name AS brand_name, b.id AS brand_id, b.logo_url AS brand_logo_url,
           s.shop_name AS seller_name, s.id AS seller_id
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    LEFT JOIN brands b ON b.id = p.brand_id
    LEFT JOIN sellers s ON s.id = p.seller_id
    WHERE p.category_id = ? AND p.status = 'active'
    ORDER BY p.sold_count DESC
");
$stmt->execute([$category['id']]);
$all_products = $stmt->fetchAll();

// Build danh sách brand & seller từ sản phẩm
// Build danh sách brand & seller từ sản phẩm
$brands_map  = [];
$sellers_map = [];
foreach ($all_products as $p) {
    if ($p['brand_id'] && $p['brand_name']) {
        $brands_map[$p['brand_id']] = [
            'name'     => $p['brand_name'],
            'logo_url' => $p['brand_logo_url'] ?? ''
        ];
    }
    if ($p['seller_id'] && $p['seller_name']) {
        $sellers_map[$p['seller_id']] = $p['seller_name'];
    }
}
// Tổng số sản phẩm
$total_products = count($all_products);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($category['name']) ?> — MantaMarket</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/homeproduct.css" />
    <link rel="stylesheet" href="../css/categories.css" />


</head>

<body>
    <div class="crumb">
        <a href="javascript:void(0)" onclick="closeSpPanel()" style="color:inherit;text-decoration:none;">Trang chủ</a>
        &rsaquo;
        <span><?= htmlspecialchars($category['name']) ?></span>
    </div>

    <div class="container pg">
        <div class="cat-page">

            <!-- ===== SIDEBAR ===== -->
            <aside class="cat-sidebar">

                <!-- Thương hiệu -->
                <?php if ($brands_map): ?>
                    <div class="filter-block">
                        <div class="filter-title">Thương Hiệu</div>
                        <div class="filter-body">
                            <div class="brand-list" id="brandList">
                                <?php foreach ($brands_map as $bid => $brand): ?>
                                    <label class="filter-check">
                                        <input type="checkbox" class="filter-brand" value="<?= $bid ?>">


                                        <?= htmlspecialchars($brand['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Khoảng giá -->
                <div class="filter-block">
                    <div class="filter-title">Khoảng Giá</div>
                    <div class="filter-body">
                        <!-- Nút giá nhanh -->
                        <div class="price-presets">
                            <button class="price-preset-btn" onclick="setPresetPrice(0, 100)">Dưới 100k</button>
                            <button class="price-preset-btn" onclick="setPresetPrice(0, 500)">Dưới 500k</button>
                            <button class="price-preset-btn" onclick="setPresetPrice(0, 1000)">Dưới 1 triệu</button>
                            <button class="price-preset-btn" onclick="setPresetPrice(0, 2000)">Dưới 2 triệu</button>
                            <button class="price-preset-btn" onclick="setPresetPrice(0, 5000)">Dưới 5 triệu</button>
                        </div>
                        <div style="font-size:11px;color:#999;margin-bottom:6px;margin-top:8px;">Nhập theo nghìn đồng (k):</div>
                        <div class="price-inputs">
                            <input type="number" class="price-input" id="priceMin" placeholder="Từ (k)" min="0">
                            <span class="price-sep">—</span>
                            <input type="number" class="price-input" id="priceMax" placeholder="Đến (k)" min="0">
                        </div>
                        <button class="btn-apply" onclick="applyFilters()">ÁP DỤNG</button>
                    </div>
                </div>
                <!-- Seller -->
                <?php if ($sellers_map): ?>
                    <div class="filter-block">
                        <div class="filter-title">Loại Shop</div>
                        <div class="filter-body">
                            <div class="brand-list" id="sellerList">
                                <?php foreach ($sellers_map as $sid => $sname): ?>
                                    <label class="filter-check">
                                        <input type="checkbox" class="filter-seller" value="<?= $sid ?>">
                                        <?= htmlspecialchars($sname) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Đánh giá -->
                <div class="filter-block">
                    <div class="filter-title">Đánh Giá</div>
                    <div class="filter-body">
                        <div class="rating-opts" id="ratingOpts">
                            <?php foreach ([5, 4, 3, 2] as $star): ?>
                                <div class="rating-opt" data-rating="<?= $star ?>" onclick="toggleRating(<?= $star ?>, this)">
                                    <span class="stars-row"><?= str_repeat('★', $star) ?><?= str_repeat('☆', 5 - $star) ?></span>
                                    <?php if ($star < 5): ?><span style="font-size:12px;color:#777">trở lên</span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Reset -->
                <button class="btn-reset" onclick="resetFilters()">Xóa Bộ Lọc</button>
            </aside>

            <!-- ===== MAIN ===== -->
            <div class="cat-main">

                <!-- Header -->
                <div class="cat-header">

                    <div class="cat-info">
                        <div class="cat-title"><?= htmlspecialchars($category['name']) ?></div>
                        <div class="cat-count" id="catCount"><?= $total_products ?> sản phẩm</div>
                        <?php if ($brands_map): ?>
                            <div class="cat-brands-row">

<?php foreach (array_slice($brands_map, 0, 8, true) as $bid => $brand): ?>
    <span class="cat-brand-chip">
        <?php if (!empty($brand['logo_url'])): ?>
            <img
                src="<?= htmlspecialchars($brand['logo_url']) ?>"
                class="brand-logo">
        <?php endif; ?>
    </span>
<?php endforeach; ?>
                                        <?php if (count($brands_map) > 8): ?>
                                            <span class="cat-brand-chip">+<?= count($brands_map) - 8 ?> thương hiệu</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                    </div>
                </div>

                <!-- Sort toolbar -->
                <div class="cat-toolbar">
                    <span class="sort-lbl">Sắp xếp theo</span>
                    <button class="sort-btn active" data-sort="sold" onclick="setSort('sold', this)">Phổ Biến</button>
                    <button class="sort-btn" data-sort="newest" onclick="setSort('newest', this)">Mới Nhất</button>
                    <button class="sort-btn" data-sort="price_asc" onclick="setSort('price_asc', this)">Giá Thấp</button>
                    <button class="sort-btn" data-sort="price_desc" onclick="setSort('price_desc', this)">Giá Cao</button>
                    <span class="result-count" id="resultCount"><?= $total_products ?> kết quả</span>
                </div>

                <!-- Grid -->
                <div class="cat-products-grid" id="productGrid">
                    <?php foreach ($all_products as $sp): ?>
                        <div class="cat-product-card"
                            onclick="openSpPanel(<?= $sp['id'] ?>)"
                            data-brand="<?= (int)$sp['brand_id'] ?>"
                            data-seller="<?= (int)$sp['seller_id'] ?>"
                            data-price="<?= (float)$sp['base_price'] ?>"
                            data-rating="<?= (float)($sp['avg_rating'] ?? 0) ?>"
                            data-sold="<?= (int)$sp['sold_count'] ?>"
                            data-created="<?= strtotime($sp['created_at'] ?? 'now') ?>">
                            <div class="cat-product-img">
                                <?php if (!empty($sp['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($sp['image_url']) ?>" alt="<?= htmlspecialchars($sp['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <svg width="60" height="60" viewBox="0 0 56 56" fill="none">
                                        <rect x="14" y="4" width="28" height="48" rx="5" fill="#e0e0e0" />
                                        <rect x="18" y="10" width="20" height="32" rx="2" fill="#f5f5f5" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="cat-product-info">
                                <div class="cat-product-name"><?= htmlspecialchars($sp['name']) ?></div>
                                <div class="cat-product-price"><?= number_format($sp['base_price'], 0, ',', '.') ?>đ</div>
                                <?php if (!empty($sp['avg_rating']) && $sp['avg_rating'] > 0):
                                    $r = (float)$sp['avg_rating'];
                                ?>
                                    <div class="cat-product-rating">
                                        <?php for ($i = 1; $i <= 5; $i++) echo $r >= $i ? '★' : ($r >= $i - 0.5 ? '★' : '☆'); ?>
                                        <?= number_format($r, 1) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="cat-product-sold">Đã bán <?= number_format((int)$sp['sold_count']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- end .cat-main -->
        </div><!-- end .cat-page -->
    </div>

</body>

</html>