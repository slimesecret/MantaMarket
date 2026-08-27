<?php
$router  = new app_Libs_Router();
$db      = new app_Libs_DbConnection();
$danhMuc = new app_Models_Categories();
$opendm  = $danhMuc->buildQueryParams([
    "select" => "*",
    "where"  => "is_active = :is_active",
    "params" => [":is_active" => 1],
    "other"  => "ORDER BY sort_order ASC"
])->select();

$sanPham = new app_Models_Products();
$opensp  = $sanPham->buildQueryParams([
    "select" => "products.*, pi.image_url",
    "join"   => "LEFT JOIN product_images pi ON pi.product_id = products.id AND pi.is_primary = 1",
    "where"  => "products.status = :status",
    "params" => [":status" => "active"],
    "other"  => "ORDER BY products.created_at DESC"
])->select();

$topSellers = $db->query("
    SELECT s.shop_name, s.shop_slug, s.avatar_url, s.is_verified,
           COALESCE(AVG(p.avg_rating), 0) AS avg_rating,
           COALESCE(SUM(p.sold_count), 0) AS total_sales,
           COUNT(DISTINCT p.id) AS product_count
    FROM sellers s
    LEFT JOIN products p ON p.seller_id = s.id AND p.status = 'active'
    WHERE s.is_active = 1
    GROUP BY s.id
    HAVING avg_rating > 0
    ORDER BY avg_rating DESC, total_sales DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$topProducts = $db->query("
    SELECT p.id, p.name, p.base_price, p.avg_rating, p.sold_count, pi.image_url
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.status = 'active' AND p.avg_rating > 0
    ORDER BY p.avg_rating DESC, p.sold_count DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$router->partial('header');
?>

<!-- HERO — chỉ background/animation, KHÔNG có content bên trong -->
<section class="hero page-section" id="hero">
    <div class="layer layer-bg"></div>
    <div class="layer layer-back-mountain" id="layerBack"></div>
    <div class="layer layer-front-mountain" id="layerFront"></div>
    <div class="layer layer-fish" id="layerFish">
        <img src="../img/Manta-Fish-1.svg" alt="Manta Fish">
    </div>
    <div class="layer layer-labubu" id="layerLabubu">
        <img src="../img/labubu.svg" alt="Labubu">
    </div>
    <div class="layer layer-fish2">
        <img src="../img/Manta-Fish-2.svg" alt="Manta Fish">
    </div>

    <!-- ① TOP SẢN PHẨM — overlay phía dưới hero -->
    <div class="hero-top-products">
        <div class="hero-top-products-inner">
            <div class="section-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px 8px;">
                <div class="section-title" style="color:#111;text-shadow:0 2px 8px rgba(0,0,0,0.3);">
                    TOP SẢN PHẨM ĐƯỢC ĐÁNH GIÁ CAO NHẤT
                </div>
                <div class="shop-rank-nav">
                    <button class="shop-nav-btn" id="prodNavPrev" onclick="prodPagePrev()" disabled>&#8249; Xếp hạng cao hơn</button>
                    <span class="shop-nav-page" id="prodNavLabel">1 / 1</span>
                    <button class="shop-nav-btn" id="prodNavNext" onclick="prodPageNext()">Xếp hạng thấp hơn &#8250;</button>
                </div>
            </div>
            <div class="prod-rank-grid" id="prodRankGrid"></div>
        </div>
    </div>
</section>
<!-- PRODUCT PANEL CONTAINER -->
<div id="spPanelContainer" style="display:none; background:#f5f5f5; min-height:100vh;">
    <div id="spPanelContent"></div>
</div>

<!-- MAIN CONTENT — tất cả sections đặt ở đây, NGOÀI hero -->
<div class="main page-section">



    <!-- ② DANH MỤC -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">DANH MỤC</div>
        </div>
        <div class="categories-wrapper">
            <button class="categories-arrow hidden" id="arrowPrev">&#8249;</button>
            <div class="categories-track-clip">
                <div class="categories-track" id="categoriesTrack">
                    <?php foreach ($opendm as $r): ?>
                        <a class="category-item" href="javascript:void(0)"
                           onclick="openCategoryPanel('<?= htmlspecialchars($r['slug']) ?>')">
                            <div class="category-icon">
                                <img src="../<?= $r['image_url'] ?>" alt="<?= $r['name'] ?>">
                            </div>
                            <div class="category-name"><?= $r['name'] ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="categories-arrow" id="arrowNext">&#8250;</button>
        </div>
    </div>

    <!-- ③ GỢI Ý HÔM NAY -->
    <div class="section">
        <div class="today-header">
            <div class="today-title">GỢI Ý HÔM NAY</div>
        </div>
        <div class="products-grid">
            <?php foreach ($opensp as $p): ?>
                <div class="product-card" onclick="openSpPanel(<?= $p['id'] ?>)">
                    <div class="product-card-inner">
                        <div class="product-img">
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="<?= htmlspecialchars($p['image_url']) ?>"
                                     alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php else: ?>
                                <div class="no-img">No Image</div>
                            <?php endif; ?>
                            <?php if ($p['sold_count'] >= 2000): ?>
                                <div class="fav-badge">Bán Chạy</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
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
                                    <?php endif; endfor; ?>
                                    <span style="font-size:11px;color:#888;margin-left:3px;">
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
        <div class="load-more-container">
            <button class="btn-load-more">Xem Thêm</button>
        </div>
    </div>

    <!-- ④ TOP SHOP -->
    <div class="section">
        <div class="section-header" style="display:flex;align-items:center;justify-content:space-between;">
            <div class="section-title">TOP 10 SHOP CÓ ĐÁNH GIÁ CAO NHẤT</div>
            <div class="shop-rank-nav">
                <button class="shop-nav-btn" id="shopNavPrev" onclick="shopPagePrev()" disabled>
                    &#8249; Xếp hạng cao hơn
                </button>
                <span class="shop-nav-page" id="shopNavLabel">
                    1 / <?= ceil(count($topSellers) / 10) ?>
                </span>
                <button class="shop-nav-btn" id="shopNavNext" onclick="shopPageNext()">
                    Xếp hạng thấp hơn &#8250;
                </button>
            </div>
        </div>
        <div class="shop-rank-grid" id="shopRankGrid"></div>
    </div>

</div><!-- /.main.page-section -->

<script>
/* ── TOP PRODUCTS ── */
const PROD_DATA      = <?= json_encode(array_values($topProducts), JSON_UNESCAPED_UNICODE) ?>;
const PRODS_PER_PAGE = 10;
let   prodCurrentPage = 0;

function renderProdPage(page) {
    const totalPages = Math.ceil(PROD_DATA.length / PRODS_PER_PAGE) || 1;
    const start = page * PRODS_PER_PAGE;
    const slice = PROD_DATA.slice(start, start + PRODS_PER_PAGE);
    const grid  = document.getElementById('prodRankGrid');

    grid.innerHTML = slice.map((p, idx) => {
        const rank      = start + idx + 1;
        const rating    = parseFloat(p.avg_rating) || 0;
        const full      = Math.floor(rating);
        const half      = (rating - full) >= 0.5;
        let   stars     = '';
        for (let i = 1; i <= 5; i++) {
            stars += (i <= full || (half && i === full + 1))
                ? '<span style="color:#f5a623">★</span>'
                : '<span style="color:#ddd">★</span>';
        }
        const imgHtml = p.image_url
            ? `<img src="${p.image_url}" alt="" style="width:100%;height:100%;object-fit:cover;"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
               <div class="prod-rank-img-ph">${p.name.charAt(0).toUpperCase()}</div>`
            : `<div class="prod-rank-img-ph">${p.name.charAt(0).toUpperCase()}</div>`;

        const bc = {1:'#FFD700',2:'#C0C0C0',3:'#CD7F32'};
        const bs = bc[rank]
            ? `background:${bc[rank]};color:#333;`
            : `background:#f0eeff;color:#6f55ff;`;

        return `
        <div class="prod-rank-item" onclick="openSpPanel(${parseInt(p.id,10)})">
            <div class="prod-rank-badge" style="${bs}">#${rank}</div>
            <div class="prod-rank-img">${imgHtml}</div>
            <div class="prod-rank-info">
                <div class="prod-rank-name">${p.name}</div>
                <div class="prod-rank-price">${Number(p.base_price).toLocaleString('vi-VN')}đ</div>
                <div class="prod-rank-stars">${stars}
                    <span style="font-size:11px;color:#888;margin-left:2px;">${rating.toFixed(1)}</span>
                </div>
                <div class="prod-rank-sold">Đã bán ${Number(p.sold_count).toLocaleString('vi-VN')}</div>
            </div>
        </div>`;
    }).join('');

    document.getElementById('prodNavPrev').disabled = (page === 0);
    document.getElementById('prodNavNext').disabled = (page >= totalPages - 1);
    document.getElementById('prodNavLabel').textContent = `${page + 1} / ${totalPages}`;
}
function prodPageNext() {
    if (prodCurrentPage < Math.ceil(PROD_DATA.length/PRODS_PER_PAGE)-1) {
        prodCurrentPage++; renderProdPage(prodCurrentPage);
    }
}
function prodPagePrev() {
    if (prodCurrentPage > 0) { prodCurrentPage--; renderProdPage(prodCurrentPage); }
}

/* ── TOP SHOPS ── */
const SHOP_DATA      = <?= json_encode(array_values($topSellers), JSON_UNESCAPED_UNICODE) ?>;
const SHOPS_PER_PAGE = 10;
let   shopCurrentPage = 0;

function renderShopPage(page) {
    const totalPages = Math.ceil(SHOP_DATA.length / SHOPS_PER_PAGE);
    const start = page * SHOPS_PER_PAGE;
    const slice = SHOP_DATA.slice(start, start + SHOPS_PER_PAGE);
    const grid  = document.getElementById('shopRankGrid');

    grid.innerHTML = slice.map((s, idx) => {
        const rank    = start + idx + 1;
        const rating  = parseFloat(s.avg_rating) || 0;
        const full    = Math.floor(rating);
        const half    = (rating - full) >= 0.5;
        let   stars   = '';
        for (let i = 1; i <= 5; i++) {
            stars += (i <= full || (half && i === full+1))
                ? '<span style="color:#f5a623">★</span>'
                : '<span style="color:#ddd">★</span>';
        }
        const letter    = s.shop_name ? s.shop_name.charAt(0).toUpperCase() : '?';
        const avatarHtml = s.avatar_url
            ? `<img src="${s.avatar_url}" alt="${s.shop_name}"
                    onload="this.nextElementSibling.style.display='none'"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
               <div class="shop-avatar-fallback">${letter}</div>`
            : `<div class="shop-avatar-fallback">${letter}</div>`;
        const verified = s.is_verified == 1
            ? `<span class="verified-dot">✓</span>` : '';

        return `
        <a class="shop-rank-item" href="javascript:void(0)"
           onclick="openSellersPanel('${s.shop_slug}')">
            <div class="shop-rank-badge">#${rank}</div>
            <div class="shop-rank-avatar">${avatarHtml}${verified}</div>
            <div class="shop-rank-name">${s.shop_name}</div>
            <div class="shop-rank-stars">${stars}
                <span style="font-size:11px;color:#888;margin-left:2px;">${rating.toFixed(1)}</span>
            </div>
            <div class="shop-rank-sold">Đã bán ${parseInt(s.total_sales).toLocaleString('vi-VN')}</div>
        </a>`;
    }).join('');

    document.getElementById('shopNavPrev').disabled = (page === 0);
    document.getElementById('shopNavNext').disabled = (page >= totalPages - 1);
    document.getElementById('shopNavLabel').textContent = `${page + 1} / ${totalPages}`;
}
function shopPageNext() {
    if (shopCurrentPage < Math.ceil(SHOP_DATA.length/SHOPS_PER_PAGE)-1) {
        shopCurrentPage++; renderShopPage(shopCurrentPage);
    }
}
function shopPagePrev() {
    if (shopCurrentPage > 0) { shopCurrentPage--; renderShopPage(shopCurrentPage); }
}

document.addEventListener('DOMContentLoaded', () => {
    renderProdPage(0);
    renderShopPage(0);
});
</script>

<?php $router->partial('footer'); ?>