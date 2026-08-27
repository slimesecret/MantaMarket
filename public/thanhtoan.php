<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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
    die('<div style="padding:2rem;color:red">Lỗi kết nối database: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
// ── Auth ───────────────────────────────────────────────────────────────────
$user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: /MantaMarket/public/index.php?r=login');
    exit;
}
// ── Nhận cart_item_ids từ POST hoặc GET ───────────────────────────────────
$raw_ids       = $_POST['cart_item_ids'] ?? $_GET['cart_item_ids'] ?? [];
$cart_item_ids = array_values(array_filter(array_map('intval', (array)$raw_ids)));
if (empty($cart_item_ids)) {
    header('Location: /MantaMarket/public/index.php?page=myaccount&tab=cart');
    exit;
}
// ── 1. Thông tin user ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    die('<div style="padding:2rem;color:#555">Vui lòng đăng nhập.</div>');
}
// ── 2. Địa chỉ mặc định ───────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM user_addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, id DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$address = $stmt->fetch();

// ── 2b. Tất cả địa chỉ của user ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM user_addresses
    WHERE user_id = ?
    ORDER BY is_default DESC, id DESC
");
$stmt->execute([$user_id]);
$all_addresses = $stmt->fetchAll();

// ── 3. Query các cart items đã chọn ──────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($cart_item_ids), '?'));
$stmt = $pdo->prepare("
        SELECT
            ci.id           AS cart_item_id,
            ci.quantity,
            ci.product_id,
            ci.variant_id,
            p.name          AS product_name,
            p.base_price,
            p.slug          AS product_slug,
            pv.price        AS variant_price,
            pv.compare_price,
            pv.sku,
            pv.color,
            pv.size,
            pv.stock_quantity,
(
    SELECT pi.image_url 
    FROM product_images pi
    LEFT JOIN product_variants pv2 ON pv2.id = pi.variant_id
    WHERE pi.product_id = p.id
    ORDER BY 
        (pi.variant_id = ci.variant_id) DESC,
        (pv2.color = pv.color) DESC,
        pi.is_primary DESC,
        pi.sort_order ASC
    LIMIT 1
) AS image_url,
            s.shop_name,
            s.id            AS seller_id
        FROM cart_items ci
        JOIN cart              ct ON ct.id = ci.cart_id AND ct.user_id = ?
        JOIN products           p ON p.id  = ci.product_id AND p.status = 'active'
        LEFT JOIN product_variants pv ON pv.id = ci.variant_id
        LEFT JOIN sellers           s ON s.id = p.seller_id
        WHERE ci.id IN ($placeholders)
        ORDER BY s.shop_name, ci.created_at ASC
    ");
$stmt->execute(array_merge([$user_id], $cart_item_ids));
$cart_items = $stmt->fetchAll();
if (empty($cart_items)) {
    header('Location: /MantaMarket/public/index.php?page=myaccount&tab=cart');
    exit;
}
// ── 4. Nhóm theo shop ─────────────────────────────────────────────────────
$shops = [];
foreach ($cart_items as $item) {
    $sid = $item['seller_id'];
    if (!isset($shops[$sid])) {
        $shops[$sid] = ['shop_name' => $item['shop_name'], 'items' => []];
    }
    $shops[$sid]['items'][] = $item;
}
// ── 5. Tính tiền ──────────────────────────────────────────────────────────
$shipping_fee = isset($body['shipping_fee']) && $body['shipping_fee'] > 0
    ? (int)$body['shipping_fee']
    : 28700; // fallback nếu không có
$discount     = 0;
$subtotal     = 0;
foreach ($cart_items as $item) {
    $price     = (float)($item['variant_price'] ?? $item['base_price']);
    $subtotal += $price * $item['quantity'];
}
$total = $subtotal + $shipping_fee - $discount;
// ── 6. Lấy voucher của shop áp dụng được ─────────────────────────────
$seller_ids = array_keys($shops);
$ph = implode(',', array_fill(0, count($seller_ids), '?'));
$stmt = $pdo->prepare("
        SELECT c.*, s.shop_name
        FROM coupons c
        JOIN sellers s ON s.id = c.seller_id
        WHERE c.seller_id IN ($ph)
        AND c.is_active = 1
        AND NOW() BETWEEN c.start_date AND c.end_date
        AND (c.max_uses IS NULL OR c.used_count < c.max_uses)
        AND (c.min_order_value IS NULL OR c.min_order_value <= ?)
        ORDER BY c.value DESC
    ");
$stmt->execute(array_merge($seller_ids, [$subtotal]));
$available_coupons = $stmt->fetchAll();
// ── Helper ────────────────────────────────────────────────────────────────


function vnd(float $n): string
{
    return number_format($n, 0, ',', '.') . 'đ';
}
// ── Login URL ───────────────────────────────────────────────────────────
$login_url = '/MantaMarket/public/index.php?r=login';

/*
── Lấy địa chỉ shop đầu tiên để geocode ──────────────────────────────────────
Nếu bảng sellers có cột address, dùng query dưới. 
Nếu không có, tạm dùng địa chỉ mặc định hoặc để trống.
*/

// Lấy địa chỉ của seller đầu tiên trong đơn
$first_seller_id = array_key_first($shops ?? []);
$shop_address_for_map = '';
if ($first_seller_id) {
    $stmtShop = $pdo->prepare("SELECT address FROM sellers WHERE id = ? LIMIT 1");
    $stmtShop->execute([$first_seller_id]);
    $sellerRow = $stmtShop->fetch();
if ($sellerRow && !empty($sellerRow['address'])) {
    $addr = trim($sellerRow['address']);
    // Thêm Việt Nam nếu chưa có
    if (stripos($addr, 'việt nam') === false) {
        $addr .= ', Việt Nam';
    }
    $shop_address_for_map = $addr;
}
}

// Địa chỉ người mua
$buyer_address_for_map = '';
if ($address) {
    $buyer_address_for_map = implode(', ', array_filter([
        $address['address_line'] ?? '',
        $address['ward']         ?? '',
        $address['district']     ?? '',
        $address['province']     ?? '',
        'Việt Nam'
    ]));
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán – Manta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="../css/thanhtoan.css" />
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .route-map-card {
            padding: 18px 20px 14px;
            margin-bottom: 14px;
        }

        .route-map-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .route-map-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .route-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .route-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .distance-badge {
            background: #fff3f0;
            color: #ee4d2d;
            border: 1px solid #ffd3c8;
        }

        .fee-badge {
            background: #f0f7ff;
            color: #0b74e5;
            border: 1px solid #c2deff;
        }

        .route-legend {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 12.5px;
            color: #555;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-arrow {
            color: #bbb;
            font-size: 14px;
        }

        .route-info-bar {
            margin-top: 10px;
            display: flex;
            align-items: flex-start;
            gap: 0;
            background: #fafafa;
            border-radius: 6px;
            padding: 10px 14px;
            border: 1px solid #f0f0f0;
        }

        .route-info-item {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            flex: 1;
            font-size: 12.5px;
            color: #555;
            line-height: 1.4;
        }

        .route-info-divider {
            width: 1px;
            background: #e8e8e8;
            margin: 0 14px;
            align-self: stretch;
            flex-shrink: 0;
        }

        .route-addr-text {
            flex: 1;
        }

        /* Leaflet popup custom */
        .leaflet-popup-content-wrapper {
            border-radius: 8px !important;
            font-family: 'Be Vietnam Pro', sans-serif !important;
            font-size: 13px !important;
        }
    </style>
</head>

<body data-logged-in="<?= isset($_SESSION['userId']) ? '1' : '0' ?>">
    <!-- ===== CHECKOUT HEADER ===== -->
    <div class="header1">
        <div class="container logo-area">
            <div class="checkout-title">Thanh Toán</div>
        </div>
    </div>
    <!-- ===== MAIN ===== -->
    <div class="container main">
        <!-- LEFT -->
        <div class="left-column">
            <a href="javascript:void(0)"
                onclick="if(typeof openMyAccountPanel==='function'){history.back();}else{history.back();}">
                ← Quay lại giỏ hàng
            </a>
            <!-- ĐỊA CHỈ -->
            <div class="card address">
                <div class="address-title">
                    <i class="fa-solid fa-location-dot"></i>
                    Địa Chỉ Nhận Hàng
                </div>
                <?php if ($address): ?>
                    <!-- ID được đặt ở đây để JS cập nhật đúng chỗ -->
                    <div class="address-content" id="selectedAddressDisplay">
                        <div class="address-info" id="addressInfoBlock">
                            <div class="name-phone">
                                <span id="addrName"><?= htmlspecialchars($address['full_name']) ?></span>
                                &nbsp;(+84) <span id="addrPhone"><?= ltrim(htmlspecialchars($address['phone']), '0') ?></span>
                            </div>
                            <div class="address-text" id="addrText">
                                <?= htmlspecialchars($address['address_line']) ?>,
                                <?= htmlspecialchars($address['ward']) ?>,
                                <?= htmlspecialchars($address['district']) ?>,
                                <?= htmlspecialchars($address['province']) ?>
                            </div>
                            <?php if ($address['is_default']): ?>
                                <div class="default-tag" id="addrDefaultTag">Mặc Định</div>
                            <?php else: ?>
                                <div class="default-tag" id="addrDefaultTag" style="display:none">Mặc Định</div>
                            <?php endif; ?>
                        </div>
                        <a href="javascript:void(0)" class="change-btn" onclick="openAddressModal()">Thay Đổi</a>
                    </div>
                <?php else: ?>
                    <div class="no-address">
                        Chưa có địa chỉ.
                        <a href="/MantaMarket/public/index.php?page=myaccount&tab=address" style="color:#0b74e5">Thêm địa chỉ</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===================== HTML (dán SAU thẻ đóng của card address) ===================== -->
            <div class="card route-map-card" id="routeMapCard">
                <div class="route-map-header">
                    <div class="route-map-title">
                        <i class="fa-solid fa-route" style="color:#ee4d2d"></i>
                        Quãng đường vận chuyển
                    </div>
                    <div class="route-meta" id="routeMeta">
                        <span id="routeDistance" class="route-badge distance-badge">
                            <i class="fa-solid fa-arrows-left-right"></i> Đang tính...
                        </span>
                        <span id="routeShipFee" class="route-badge fee-badge" style="display:none">
                            <i class="fa-solid fa-truck"></i> Phí: <strong id="shipFeeText">—</strong>
                        </span>
                    </div>
                </div>

                <!-- Legend -->
                <div class="route-legend">
                    <div class="legend-item">
                        <div class="legend-dot" style="background:#ee4d2d"></div>
                        <span id="shopLegendName">Shop</span>
                    </div>
                </div>

                <!-- Map -->
                <div id="deliveryMap" style="height:280px;border-radius:8px;overflow:hidden;border:1px solid #f0f0f0;"></div>

                <!-- Info bar -->
                <div class="route-info-bar" id="routeInfoBar" style="display:none">
                    <div class="route-info-item">
                        <i class="fa-solid fa-location-dot" style="color:#ee4d2d"></i>
                        <span id="fromAddrText" class="route-addr-text">—</span>
                    </div>
                    <div class="route-info-divider"></div>
                    <div class="route-info-item">
                        <i class="fa-solid fa-location-dot" style="color:#0b74e5"></i>
                        <span id="toAddrText" class="route-addr-text">—</span>
                    </div>
                </div>

                <div id="mapErrorMsg" style="display:none;color:#ee4d2d;font-size:13px;padding:8px 0;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Không thể tính được quãng đường. Phí vận chuyển mặc định được áp dụng.
                </div>
            </div>

            <!-- SẢN PHẨM — nhóm theo shop -->
            <?php foreach ($shops as $seller_id => $shop): ?>
                <div class="card product-section">
                    <div class="section-header">
                        <div>Sản phẩm</div>
                        <div>Đơn giá</div>
                        <div>Số lượng</div>
                        <div>Thành tiền</div>
                    </div>
                    <div class="shop-row">
                        <strong><?= htmlspecialchars($shop['shop_name'] ?? 'Cửa hàng') ?></strong>
                    </div>
                    <?php foreach ($shop['items'] as $item):
                        $unit_price    = (float)($item['variant_price'] ?? $item['base_price']);
                        $compare_price = (float)($item['compare_price'] ?? 0);
                        $qty           = (int)$item['quantity'];
                        $line_total    = $unit_price * $qty;
                        $variant_parts = array_filter([$item['color'] ?? '', $item['size'] ?? '']);
                    ?>
                        <div class="product-row">
                            <div class="product-info">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                        alt="<?= htmlspecialchars($item['product_name']) ?>">
                                <?php else: ?>
                                    <div class="product-placeholder">🛍</div>
                                <?php endif; ?>
                                <div>
                                    <div class="product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <?php if ($variant_parts): ?>
                                        <div class="variant">Phân loại: <?= htmlspecialchars(implode(' / ', $variant_parts)) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="price-cell">
                                <?= vnd($unit_price) ?>
                                <?php if ($compare_price > $unit_price): ?>
                                    <span class="old-price"><?= vnd($compare_price) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="qty-cell"><?= $qty ?></div>
                            <div class="total-cell"><?= vnd($line_total) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div><!-- /.left-column -->
        <!-- RIGHT -->
        <div class="right-column">
            <div class="card payment-section">
                <div class="payment-title">Phương thức thanh toán</div>
                <div class="payment-tabs">
                    <div class="payment-tab active" data-type="cod" onclick="selectTab(this)">
                        QR Code
                    </div>
                    <div class="payment-tab" data-type="wallet" onclick="selectTab(this)">Ví điện tử</div>
                </div>
                <div class="checkout-wallet-box" id="mbBankBox" style="display:none;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/3/36/MetaMask_Fox.svg"
                        style="width:36px;height:36px;object-fit:contain;">
                    <div class="wallet-text">
                        MetaMask<br>
                        <span style="color:#998;font-size:12px;" id="metaMaskAddr"></span>
                    </div>
                </div>
                <div class="voucher-section" id="voucherSection">
                    <div class="voucher-header1">
                        <span><i class="fa-solid fa-ticket" style="color:#ee4d2d;margin-bottom: 6px; "></i> Voucher của Shop</span>
                        <span id="voucherCount" class="voucher-badge"></span>
                    </div>
                    <div id="voucherList"></div>
                </div>
                <div class="summary">
                    <div class="summary-row">
                        <span>Tổng tiền hàng (<?= count($cart_items) ?> sản phẩm)</span>
                        <span><?= vnd($subtotal) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Phí vận chuyển</span>
                        <span><?= vnd($shipping_fee) ?></span>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div class="summary-row discount-row">
                            <span>Voucher giảm giá</span>
                            <span>-<?= vnd($discount) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-row total-row">
                        <span style="font-size:14px;color:#333;font-weight:500;">Tổng thanh toán</span>
                        <span class="final-price"><?= vnd($total) ?></span>
                    </div>
                    <div class="summary-row" id="bnbPreviewRow" style="display:none;border-top:1px dashed #f0b90b;margin-top:8px;padding-top:8px;">
                        <span style="color:#f0b90b;font-size:13px;">≈ Quy đổi BNB</span>
                        <span id="bnbPreview" style="color:#f0b90b;font-weight:700;font-size:15px;">Đang tính...</span>
                    </div>
                </div>
                <div class="footer-order">
                    <button class="order-btn" id="orderBtn" onclick="placeOrder()">
                        Đặt hàng
                    </button>
                </div>
            </div>
        </div><!-- /.right-column -->
    </div><!-- /.main -->
    <!-- ===== FOOTER ===== -->
    <div class="footer page-section">
        <div class="footer-main">
            <div class="footer-cols">
                <div class="footer-col">
                    <div class="footer-col-title">DỊCH VỤ KHÁCH HÀNG</div>
                    <a href="#">Trung Tâm Trợ Giúp Manta</a>
                    <a href="#">Manta Blog</a>
                    <a href="#">MANTA MALL</a>
                    <a href="#">Hướng Dẫn Mua Hàng/Đặt Hàng</a>
                    <a href="#">Hướng Dẫn Bán Hàng</a>
                    <a href="#">Ví MantaPay</a>
                    <a href="#">Manta Xu</a>
                    <a href="#">Đơn Hàng</a>
                    <a href="#">Trả Hàng/Hoàn Tiền</a>
                    <a href="#">Liên Hệ Manta</a>
                    <a href="#">Chính Sách Bảo Hành</a>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">MANTA VIỆT NAM</div>
                    <a href="#">Về Manta</a>
                    <a href="#">Tuyển Dụng</a>
                    <a href="#">Điều Khoản Manta</a>
                    <a href="#">Chính Sách Bảo Mật</a>
                    <a href="#">MANTA MALL</a>
                    <a href="#">Kênh Người Bán</a>
                    <a href="#">Flash Sale</a>
                    <a href="#">Tiếp Thị Liên Kết</a>
                    <a href="#">Liên Hệ Truyền Thông</a>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">THANH TOÁN</div>
                    <div class="payment-icons">
                        <div class="payment-icon">VISA</div>
                        <div class="payment-icon">Mastercard</div>
                        <div class="payment-icon">JCB</div>
                        <div class="payment-icon">AMEX</div>
                        <div class="payment-icon">ATM</div>
                        <div class="payment-icon">TRẢ GÓP</div>
                        <div class="payment-icon" style="background:#EE4D2D;color:white">SPay</div>
                        <div class="payment-icon">SPaylater</div>
                    </div>
                    <div style="margin-top:16px">
                        <div class="footer-col-title">ĐƠN VỊ VẬN CHUYỂN</div>
                        <div class="delivery-icons">
                            <div class="delivery-icon">SPX</div>
                            <div class="delivery-icon">GHN</div>
                            <div class="delivery-icon">Viettel Post</div>
                            <div class="delivery-icon">Vietnam Post</div>
                            <div class="delivery-icon">J&T</div>
                            <div class="delivery-icon">GrabExpress</div>
                            <div class="delivery-icon">Ninja Van</div>
                            <div class="delivery-icon">be</div>
                            <div class="delivery-icon">Ahamove</div>
                        </div>
                    </div>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">THEO DÕI MANTA</div>
                    <div class="follow-icons">
                        <div class="follow-icon" style="background:#1877F2;color:white">f</div>
                        <div class="follow-icon" style="background:linear-gradient(45deg,#f09433,#dc2743,#bc1888);color:white">📸</div>
                        <div class="follow-icon" style="background:#0077B5;color:white">in</div>
                    </div>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">TẢI ỨNG DỤNG MANTA</div>
                    <div style="display:flex;gap:12px;align-items:flex-start">
                        <div class="qr-code"></div>
                        <div class="footer-app">
                            <div class="app-btn">App Store</div>
                            <div class="app-btn">Google Play</div>
                            <div class="app-btn">AppGallery</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <div style="font-size:12px;color:#888">© 2026 Manta. Tất cả các quyền được bảo lưu.</div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <span style="font-size:12px;color:#888">Quốc gia & Khu vực:</span>
                        <a href="#" style="font-size:12px;color:#888;text-decoration:none">Singapore</a>
                        <a href="#" style="font-size:12px;color:#888;text-decoration:none">Indonesia</a>
                        <a href="#" style="font-size:12px;color:#888;text-decoration:none">Thái Lan</a>
                        <a href="#" style="font-size:12px;color:#888;text-decoration:none">Malaysia</a>
                        <a href="#" style="font-size:12px;color:#888;text-decoration:none">Việt Nam</a>
                        <a href="#" style="font-size:12px;color:#888;text-decoration:none">Philippines</a>
                    </div>
                </div>
                <div class="footer-policies">
                    <a href="#">CHÍNH SÁCH BẢO MẬT</a>
                    <a href="#">QUY CHẾ HOẠT ĐỘNG</a>
                    <a href="#">CHÍNH SÁCH VẬN CHUYỂN</a>
                    <a href="#">CHÍNH SÁCH TRẢ HÀNG VÀ HOÀN TIỀN</a>
                </div>
                <div class="footer-certified">
                    <div class="certified-badge">ĐÃ ĐĂNG KÝ BỘ CÔNG THƯƠNG</div>
                    <div class="certified-badge">ĐÃ ĐĂNG KÝ BỘ CÔNG THƯƠNG</div>
                </div>
                <div class="footer-copyright">
                    Công ty TNHH Manta<br>
                    Địa chỉ: Tầng 4-5-6, Tòa nhà Capital Place, số 29 đường Liễu Giai, Phường Ngọc Hà, Thành phố Hà Nội, Việt Nam<br>
                    Chăm sóc khách hàng: Gọi tổng đài Manta (miễn phí) hoặc Trò chuyện với Manta ngay trên Trung tâm trợ giúp<br>
                    Mã số doanh nghiệp: 0106773786 do Sở Kế hoạch và Đầu tư TP Hà Nội cấp lần đầu ngày 10/02/2015<br>
                    © 2015 - Bản quyền thuộc về Công ty TNHH Manta
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SCRIPTS ===== -->
    <!-- 1. Biến PHP → JS -->
    <script>
        window.ALL_ADDRESSES = <?= json_encode($all_addresses) ?>;
        window.SELECTED_ADDRESS_ID = <?= $address ? (int)$address['id'] : 'null' ?>;
        window.AVAILABLE_COUPONS = <?= json_encode($available_coupons) ?>;
        window.IS_LOGGED_IN = <?= isset($_SESSION['userId']) ? 'true' : 'false' ?>;
        window.RECEIVER_WALLET = '0x2e160cc5136143D859Ef59adde676d726eC1492f';
        window.CONTRACT = '0xd568418D90a91Da3bFC7896e28D98b278328e8Bf'; // ← cập nhật sau khi deploy lại
        window.SUBTOTAL_VND = <?= (int)$subtotal ?>;
        window.SHIPPING_VND = <?= (int)$shipping_fee ?>;
        window.TOTAL_VND = <?= (int)$total ?>;
        window.CART_ITEM_IDS = <?= json_encode($cart_item_ids) ?>;
        window.CART_ITEMS_DATA = <?= json_encode(array_map(function ($item) {
                                        return [
                                            'product_id'   => $item['product_id'],
                                            'product_name' => $item['product_name'],
                                            'image_url'    => $item['image_url'] ?? '',
                                            'color'        => $item['color'] ?? '',
                                            'size'         => $item['size'] ?? '',
                                            'price'        => (float)($item['variant_price'] ?? $item['base_price']),
                                            'quantity'     => (int)$item['quantity'],
                                        ];
                                    }, $cart_items)) ?>;
    </script>

    <script>
        // ── Chọn tab thanh toán ──────────────────────────────────────────────
        function selectTab(tab) {
            document.querySelectorAll('.payment-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const type = tab.dataset.type;
            const btn = document.getElementById('orderBtn');
            const bnbRow = document.getElementById('bnbPreviewRow');
            const mbBankBox = document.getElementById('mbBankBox');
            const voucherSection = document.getElementById('voucherSection');
            if (type === 'wallet') {
                if (mbBankBox) mbBankBox.style.display = 'flex';
                if (bnbRow) bnbRow.style.display = '';
                if (voucherSection) voucherSection.style.display = '';
                recalcWithVoucher();
                btn.textContent = 'Thanh toán bằng BNB';
                btn.onclick = () => buyCartWithBNB();
            } else {
                if (mbBankBox) mbBankBox.style.display = 'none';
                if (bnbRow) bnbRow.style.display = 'none';
                if (voucherSection) voucherSection.style.display = '';
                recalcWithVoucher();
                btn.textContent = 'Đặt hàng';
                btn.onclick = () => placeOrder();
            }
        }

        // ── Render danh sách voucher ─────────────────────────────────────────
        function renderVouchers() {
            const list = document.getElementById('voucherList');
            const badge = document.getElementById('voucherCount');
            if (!window.AVAILABLE_COUPONS.length) {
                badge.textContent = 'Không có voucher khả dụng';
                list.innerHTML = '<div style="color:#998;margin-bottom:6px;font-size:13px;padding:8px 0">Hiện không có voucher nào áp dụng được</div>';
                return;
            }
            badge.textContent = window.AVAILABLE_COUPONS.length + ' voucher có thể dùng';
            list.innerHTML = window.AVAILABLE_COUPONS.map((c, i) => {
                let label, sublabel = '';
                if (c.type === 'percent') {
                    label = `Giảm ${c.value}%`;
                    if (c.max_discount) sublabel = ` · Tối đa ${Number(c.max_discount).toLocaleString('vi-VN')}đ`;
                } else if (c.type === 'free_ship') {
                    label = 'Miễn phí vận chuyển';
                } else {
                    label = `Giảm ${Number(c.value).toLocaleString('vi-VN')}đ`;
                }
                const isSelected = i === 0 ? 'selected' : '';
                return `<div class="voucher-item ${isSelected}" data-idx="${i}" onclick="selectVoucher(this)">
                    <div class="voucher-info">
                        <div style="font-size:13px;font-weight:500">${label} — ${c.shop_name}</div>
                        <div style="font-size:11px;color:#888">
                            Mã: ${c.code} · HSD: ${c.end_date?.slice(0,10)}${sublabel}
                        </div>
                    </div>
                    <div class="voucher-discount" style="color:#ee4d2d;font-size:13px;font-weight:600">${label}</div>
                </div>`;
            }).join('');
            selectedCouponIdx = 0;
            recalcWithVoucher();
        }

        let selectedCouponIdx = -1;

        function selectVoucher(el) {
            const idx = +el.dataset.idx;
            if (selectedCouponIdx === idx) {
                el.classList.remove('selected');
                selectedCouponIdx = -1;
            } else {
                document.querySelectorAll('.voucher-item').forEach(v => v.classList.remove('selected'));
                el.classList.add('selected');
                selectedCouponIdx = idx;
            }
            recalcWithVoucher();
        }

        function recalcWithVoucher() {
            const c = window.AVAILABLE_COUPONS[selectedCouponIdx];
            let disc = 0;
            if (c) {
                if (c.type === 'fixed') disc = +c.value;
                else if (c.type === 'free_ship') disc = window.SHIPPING_VND;
                else if (c.type === 'percent') {
                    disc = window.SUBTOTAL_VND * c.value / 100;
                    if (c.max_discount) disc = Math.min(disc, +c.max_discount);
                }
            }
            const newTotal = window.SUBTOTAL_VND + window.SHIPPING_VND - disc;
            document.querySelector('.final-price').textContent =
                new Intl.NumberFormat('vi-VN').format(newTotal) + 'đ';
            const discRow = document.querySelector('.discount-row') || (() => {
                const r = document.createElement('div');
                r.className = 'summary-row discount-row';
                document.querySelector('.total-row').insertAdjacentElement('beforebegin', r);
                return r;
            })();
            if (disc > 0) {
                discRow.style.display = '';
                discRow.innerHTML = `<span>Voucher giảm giá</span><span style="color:#ee4d2d">-${new Intl.NumberFormat('vi-VN').format(disc)}đ</span>`;
            } else {
                discRow.style.display = 'none';
            }
            const bnbPrice = window._wallet?.bnbPrice || 600;
            const bnbAmount = ((newTotal / 25000) / bnbPrice).toFixed(6);
            const p = document.getElementById('bnbPreview');
            if (p) p.textContent = `≈ ${bnbAmount} BNB`;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', renderVouchers);
        } else {
            renderVouchers();
        }

        // ── Đặt hàng thường (QR / COD) ──────────────────────────────────────
        async function placeOrder() {
            const btn = document.getElementById('orderBtn');
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';

            const c = window.AVAILABLE_COUPONS[selectedCouponIdx];

            try {
                const res = await fetch('/MantaMarket/public/api/place_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include', 
body: JSON.stringify({
    cart_item_ids: window.CART_ITEM_IDS,
    address_id: window.SELECTED_ADDRESS_ID,
    coupon_id: c ? c.id : null,
    payment_method: 'cod',
    shipping_fee: window.SHIPPING_VND,
})
                });
                const data = await res.json();
                console.log('place_order response:', data); // ← THÊM để debug
                if (data.success) {
                    alert('Đặt hàng thành công! Mã đơn: ' + data.orders.join(', ') + ' 🎉');
                    window.location.href = '/MantaMarket/public/index.php?page=myaccount&tab=orders';
                } else {
                    alert('Lỗi: ' + data.error);
                    btn.disabled = false;
                    btn.textContent = 'Đặt hàng';
                }
            } catch (err) {
                alert('Lỗi kết nối: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Đặt hàng';
            }
        }
        // ── Thanh toán BNB cho giỏ hàng ─────────────────────────────────────
        async function buyCartWithBNB() {
            let addr = window._wallet?.addr || null;
            if (!addr && window.ethereum) {
                const accounts = await ethereum.request({
                    method: 'eth_accounts'
                });
                addr = accounts[0] || null;
            }
            if (!addr) {
                showWalletPrompt('Vui lòng kết nối ví để thanh toán bằng BNB');
                return;
            }
            const btn = document.getElementById('orderBtn');
            btn.disabled = true;
            btn.textContent = 'Đang xử lý...';
            try {
                const walletData = new FormData();
                walletData.append('wallet', addr);
                await fetch('/MantaMarket/public/api/save_wallet.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: walletData
                });
            } catch (e) {
                console.warn('Lưu ví thất bại:', e);
            }
            try {
                const c = window.AVAILABLE_COUPONS[selectedCouponIdx];
                let disc = 0;
                if (c) {
                    if (c.type === 'fixed') disc = +c.value;
                    else if (c.type === 'free_ship') disc = window.SHIPPING_VND;
                    else if (c.type === 'percent') {
                        disc = window.SUBTOTAL_VND * c.value / 100;
                        if (c.max_discount) disc = Math.min(disc, +c.max_discount);
                    }
                }
                const finalTotal = window.SUBTOTAL_VND + window.SHIPPING_VND - disc;
                const bnbPrice = window._wallet?.bnbPrice || 600;
                const bnbAmount = (finalTotal / 25000) / bnbPrice;
                const weiHex = '0x' + BigInt(Math.floor(bnbAmount * 1e18)).toString(16);
                showToast('Đang gửi giao dịch...');
                const productNames = window.CART_ITEMS_DATA.map(i => i.product_name).join(', ');
                const memo = 'Thanh toan: ' + productNames;
                const memoHex = '0x' + Array.from(new TextEncoder().encode(memo))
                    .map(b => b.toString(16).padStart(2, '0')).join('');

                const txHash = await ethereum.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: addr,
                        to: window.RECEIVER_WALLET,
                        value: weiHex,
                        data: memoHex
                    }]
                });
                showToast('Đang chờ xác nhận...');
                await waitTx(txHash);
                showToast('Đang tạo NFT...');
                const provider = new ethers.BrowserProvider(window.ethereum);
                const signer = await provider.getSigner();
                const abi = ["function mintForBuyer(string tokenURI) returns (uint256)"];
                const contract = new ethers.Contract(window.CONTRACT, abi, signer);
                const mintResults = [];
                for (const item of window.CART_ITEMS_DATA) {
                    const res = await fetch('/MantaMarket/api/mint_nft.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            wallet: addr,
                            txHash,
                            productId: item.product_id,
                            image: item.image_url,
                            color: item.color,
                            size: item.size,
                            skipVerify: true,
                        })
                    });
                    const json = await res.json();
                    if (!json.success) {
                        console.warn('Mint metadata lỗi:', json.error);
                        continue;
                    }
                    const mintTx = await contract.mintForBuyer(json.tokenURI);
                    await mintTx.wait();
                    mintResults.push({
                        item: item.product_name,
                        tx: mintTx.hash
                    });
                }
                // ── Tạo đơn hàng trong DB sau khi thanh toán BNB ──
                const c2 = window.AVAILABLE_COUPONS[selectedCouponIdx];
                const orderRes = await fetch('/MantaMarket/public/api/place_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        cart_item_ids: window.CART_ITEM_IDS,
                        address_id: window.SELECTED_ADDRESS_ID,
                        coupon_id: c2 ? c2.id : null,
                        payment_method: 'bnb',
                        tx_hash: txHash,
                        buyer_wallet: addr,
                        shipping_fee: window.SHIPPING_VND,
                    })
                });
                const orderData = await orderRes.json();
                if (!orderData.success) {
                    console.warn('Tạo đơn hàng thất bại:', orderData.error);
                }


                await clearCartAfterPayment(window.CART_ITEM_IDS);
                showToast('🎉 Thanh toán thành công!');



                setTimeout(() => {
                    const nftList = mintResults.map(r => `• ${r.item}\n  TX: ${r.tx}`).join('\n');
                    alert(
                        'Thanh toán BNB thành công!\n\n' +
                        `Tổng: ${finalTotal.toLocaleString('vi-VN')}đ\n` 
                    );
                    openMyAccountPanel('orders');



                }, 800);
                if (typeof window.getBalance === 'function') await window.getBalance();
            } catch (err) {
                if (err.code === 4001) showToast('❌ Đã hủy giao dịch');
                else {
                    showToast('❌ Lỗi: ' + (err.message || 'Không xác định'));
                    console.error(err);
                }
                btn.disabled = false;
                btn.textContent = 'Thanh toán bằng BNB';
            }
        }

        // ── Xóa cart sau thanh toán ──────────────────────────────────────────
        async function clearCartAfterPayment(itemIds) {
            try {
                const data = new FormData();
                data.append('action', 'clear_after_payment');
                itemIds.forEach(id => data.append('item_ids[]', id));
                await fetch('/MantaMarket/public/api/cart.php', {
                    method: 'POST',
                    body: data
                });
            } catch (e) {
                console.warn('Clear cart error:', e);
            }
        }
        // ── waitTx ── định nghĩa ngay lập tức, không chờ DOMContentLoaded
        if (typeof window.waitTx === 'undefined') {
            window.waitTx = function(txHash) {
                return new Promise(resolve => {
                    const check = async () => {
                        try {
                            const receipt = await ethereum.request({
                                method: 'eth_getTransactionReceipt',
                                params: [txHash]
                            });
                            if (receipt && receipt.status === '0x1') resolve(receipt);
                            else setTimeout(check, 2000);
                        } catch (e) {
                            setTimeout(check, 2000);
                        }
                    };
                    check();
                });
            };
        }

        // ── Wallet prompt ────────────────────────────────────────────────────
        function showWalletPrompt(message = 'Bạn chưa kết nối ví!') {
            const old = document.getElementById('wallet-popup');
            if (old) old.remove();
            const popup = document.createElement('div');
            popup.id = 'wallet-popup';
            popup.innerHTML = `
                <div class="wallet-popup-overlay">
                    <div class="wallet-popup-box">
                        <div class="wallet-popup-close" onclick="closeWalletPrompt()">✕</div>
                        <div class="wallet-popup-title">Vui lòng kết nối ví</div>
                        <div class="wallet-popup-text">${message}</div>
                        <div class="wallet-popup-actions">
                            <button class="wallet-popup-btn cancel" onclick="closeWalletPrompt()">Để sau</button>
                            <button class="wallet-popup-btn confirm" onclick="connectWalletFromPopup()">Kết nối ví</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(popup);
        }

        function closeWalletPrompt() {
            const popup = document.getElementById('wallet-popup');
            if (popup) popup.remove();
        }

        async function connectWalletFromPopup() {
            try {
                if (!window.ethereum) {
                    alert('Vui lòng cài MetaMask!');
                    return;
                }
                const accounts = await ethereum.request({
                    method: 'eth_requestAccounts'
                });
                const addr = accounts[0];
                window._wallet = {
                    ...(window._wallet || {}),
                    addr
                };
                document.getElementById('metaMaskAddr').textContent = addr.slice(0, 6) + '...' + addr.slice(-4);
                closeWalletPrompt();
                showToast?.('✅ Kết nối ví thành công');
            } catch (err) {
                console.error(err);
            }
        }

        function requireLogin(msg) {
            showLoginPrompt(msg || 'Bạn cần đăng nhập để thực hiện thao tác này');
        }
    </script>

    <!-- ===== MODAL CHỌN ĐỊA CHỈ GIAO HÀNG ===== -->
    <div id="addressPickerModal" style="display:none;position:fixed;inset:0;z-index:999;
         background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:580px;
                    max-height:85vh;overflow-y:auto;padding:0;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:18px 24px;border-bottom:1px solid #f0f0f0;">
                <div style="font-size:16px;font-weight:600;color:#222;">Địa chỉ của tôi</div>
                <button onclick="closeAddressModal()"
                    style="background:none;border:none;font-size:22px;cursor:pointer;
                           color:#998;line-height:1;padding:0 4px;">&times;</button>
            </div>
            <!-- Danh sách địa chỉ -->
            <div id="addrPickerList" style="padding:12px 24px;"></div>
            <!-- Footer -->
            <div style="padding:14px 24px;border-top:1px solid #f0f0f0;
                        display:flex;justify-content:space-between;align-items:center;">
                <a href="/MantaMarket/public/index.php?page=myaccount&tab=address"
                    style="color:#0b74e5;font-size:13.5px;text-decoration:none;">
                    + Thêm địa chỉ mới
                </a>
                <div style="display:flex;gap:10px;">
                    <button onclick="closeAddressModal()"
                        style="padding:9px 22px;border:1px solid #ddd;border-radius:4px;
                               background:#fff;cursor:pointer;font-size:14px;color:#555;">
                        Trở Lại
                    </button>
                    <button onclick="confirmAddressSelection()"
                        style="padding:9px 22px;border:none;border-radius:4px;
                               background:#ee4d2d;color:#fff;cursor:pointer;font-size:14px;font-weight:500;">
                        Xác Nhận
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .addr-picker-item {
            display: flex;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background .15s;
        }

        .addr-picker-item:last-child {
            border-bottom: none;
        }

        .addr-picker-item:hover {
            background: #fafafa;
            margin: 0 -4px;
            padding-left: 4px;
            padding-right: 4px;
            border-radius: 4px;
        }

        .addr-picker-radio {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: #ee4d2d;
            flex-shrink: 0;
            cursor: pointer;
        }

        .addr-picker-name {
            font-size: 14px;
            font-weight: 600;
            color: #222;
        }

        .addr-picker-phone {
            font-size: 13px;
            color: #555;
            margin-top: 2px;
        }

        .addr-picker-detail {
            font-size: 12.5px;
            color: #777;
            margin-top: 3px;
            line-height: 1.5;
        }

        .addr-picker-default {
            display: inline-block;
            border: 1px solid #ee4d2d;
            color: #ee4d2d;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 2px;
            margin-top: 4px;
        }
    </style>

    <script>
        let _pendingAddressId = window.SELECTED_ADDRESS_ID;

        function openAddressModal() {
            _pendingAddressId = window.SELECTED_ADDRESS_ID;
            renderAddrPickerList();
            const modal = document.getElementById('addressPickerModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeAddressModal() {
            document.getElementById('addressPickerModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function renderAddrPickerList() {
            const list = document.getElementById('addrPickerList');
            if (!window.ALL_ADDRESSES || !window.ALL_ADDRESSES.length) {
                list.innerHTML = `<div style="padding:20px 0;color:#998;text-align:center;">
                    Bạn chưa có địa chỉ nào.
                    <a href="/MantaMarket/public/index.php?page=myaccount&tab=address"
                       style="color:#0b74e5">Thêm địa chỉ</a>
                </div>`;
                return;
            }
            list.innerHTML = window.ALL_ADDRESSES.map(addr => {
                const checked = addr.id == _pendingAddressId ? 'checked' : '';
                const phone = addr.phone.replace(/^0/, '');
                return `
                <div class="addr-picker-item" onclick="selectPickerAddress(${addr.id})">
                    <input type="radio" name="addrPicker" class="addr-picker-radio"
                           value="${addr.id}" ${checked}
                           onclick="event.stopPropagation();selectPickerAddress(${addr.id})">
                    <div style="flex:1">
                        <div class="addr-picker-name">${escHtml(addr.full_name)}</div>
                        <div class="addr-picker-phone">(+84) ${escHtml(phone)}</div>
                        <div class="addr-picker-detail">
                            ${escHtml(addr.address_line)}, ${escHtml(addr.ward)},
                            ${escHtml(addr.district)}, ${escHtml(addr.province)}
                        </div>
                        ${addr.is_default ? '<span class="addr-picker-default">Mặc Định</span>' : ''}
                    </div>
                </div>`;
            }).join('');
        }

        function selectPickerAddress(id) {
            _pendingAddressId = id;
            document.querySelectorAll('input[name="addrPicker"]').forEach(r => {
                r.checked = (r.value == id);
            });
        }

        function confirmAddressSelection() {
            const addr = window.ALL_ADDRESSES.find(a => a.id == _pendingAddressId);
            if (!addr) return;

            window.SELECTED_ADDRESS_ID = addr.id;

            // ── Cập nhật đúng khối địa chỉ đang hiển thị ──
            const phone = addr.phone.replace(/^0/, '');
            document.getElementById('addrName').textContent = addr.full_name;
            document.getElementById('addrPhone').textContent = phone;
            document.getElementById('addrText').textContent =
                `${addr.address_line}, ${addr.ward}, ${addr.district}, ${addr.province}`;

            const tag = document.getElementById('addrDefaultTag');
            if (tag) tag.style.display = addr.is_default ? '' : 'none';

            closeAddressModal();
            showToast?.('✅ Đã cập nhật địa chỉ giao hàng');
        }

        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        document.getElementById('addressPickerModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddressModal();
        });
    </script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    const SHOP_ADDRESS  = <?= json_encode($shop_address_for_map) ?>;
    const BUYER_ADDRESS = <?= json_encode($buyer_address_for_map) ?>;
    const SHOP_NAME     = <?= json_encode(array_values($shops ?? [])[0]['shop_name'] ?? 'Cửa hàng') ?>;
    const VND_PER_KM    = 1000;

    const map = L.map('deliveryMap', { zoomControl: true, scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    const shopIcon = L.divIcon({
        html: `<div style="background:#ee4d2d;color:#fff;border-radius:50% 50% 50% 0;
            width:30px;height:30px;display:flex;align-items:center;justify-content:center;
            font-size:14px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);
            transform:rotate(-45deg)"><span style="transform:rotate(45deg)">🏪</span></div>`,
        iconSize:[30,30], iconAnchor:[15,30], popupAnchor:[0,-32], className:''
    });
    const buyerIcon = L.divIcon({
        html: `<div style="background:#0b74e5;color:#fff;border-radius:50% 50% 50% 0;
            width:30px;height:30px;display:flex;align-items:center;justify-content:center;
            font-size:14px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);
            transform:rotate(-45deg)"><span style="transform:rotate(45deg)">🏠</span></div>`,
        iconSize:[30,30], iconAnchor:[15,30], popupAnchor:[0,-32], className:''
    });

    // Geocode tuần tự, cách nhau 1 giây để tránh rate limit Nominatim
// Geocode với fallback: thử địa chỉ đầy đủ → thử chỉ tỉnh/thành
async function geocodeSeq(address, retries = 2) {
    for (let i = 0; i <= retries; i++) {
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=${encodeURIComponent(address)}`;
            const res = await fetch(url, { headers: { 'Accept-Language': 'vi' } });
            const data = await res.json();
            if (data && data.length > 0) {
                return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) };
            }
            // Không tìm thấy → thử rút gọn địa chỉ (bỏ số nhà, chỉ giữ quận/tỉnh)
            if (i === 0) {
                const parts = address.replace(', Việt Nam', '').split(',');
                if (parts.length >= 2) {
                    const shorter = parts.slice(-2).join(',').trim() + ', Việt Nam';
                    const url2 = `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=${encodeURIComponent(shorter)}`;
                    await sleep(1000);
                    const res2 = await fetch(url2, { headers: { 'Accept-Language': 'vi' } });
                    const data2 = await res2.json();
                    if (data2 && data2.length > 0) {
                        return { lat: parseFloat(data2[0].lat), lng: parseFloat(data2[0].lon) };
                    }
                }
            }
            if (i < retries) await sleep(1500);
        } catch(e) {
            if (i < retries) await sleep(1500);
            else throw e;
        }
    }
    throw new Error('Không tìm thấy: ' + address);
}

    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat/2)**2 +
                  Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function fmtVnd(n) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ';
    }

    async function initMap() {
        document.getElementById('shopLegendName').textContent = SHOP_NAME;

        if (!SHOP_ADDRESS || !BUYER_ADDRESS) {
            map.setView([16.0, 108.0], 6);
            document.getElementById('routeDistance').innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i> Thiếu địa chỉ shop hoặc người mua';
            return;
        }

        map.setView([16.0, 108.0], 6); // view tạm trong lúc geocode

        let shopCoord, buyerCoord;

        // Geocode shop trước
        try {
            shopCoord = await geocodeSeq(SHOP_ADDRESS);
        } catch(e) {
            document.getElementById('mapErrorMsg').style.display = '';
            document.getElementById('routeDistance').innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i> Không tìm thấy địa chỉ shop';
            return;
        }

        // Đợi 1.2 giây rồi geocode người mua (tránh Nominatim rate limit)
        await sleep(2000);

        try {
            buyerCoord = await geocodeSeq(BUYER_ADDRESS);
        } catch(e) {
            // Chỉ hiện shop nếu không geocode được người mua
            L.marker([shopCoord.lat, shopCoord.lng], { icon: shopIcon })
                .addTo(map)
                .bindPopup(`<strong>🏪 ${SHOP_NAME}</strong>`);
            map.setView([shopCoord.lat, shopCoord.lng], 13);
            document.getElementById('mapErrorMsg').style.display = '';
            document.getElementById('routeDistance').innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i> Không tìm thấy địa chỉ người nhận';
            return;
        }

        // Vẽ cả 2 marker
        L.marker([shopCoord.lat, shopCoord.lng], { icon: shopIcon })
            .addTo(map)
            .bindPopup(`<strong>🏪 ${SHOP_NAME}</strong><br><small>${SHOP_ADDRESS}</small>`);

        L.marker([buyerCoord.lat, buyerCoord.lng], { icon: buyerIcon })
            .addTo(map)
            .bindPopup(`<strong>🏠 Địa chỉ nhận hàng</strong><br><small>${BUYER_ADDRESS}</small>`);

        // Đường chim bay
        const km = haversine(shopCoord.lat, shopCoord.lng, buyerCoord.lat, buyerCoord.lng);

        L.polyline([
            [shopCoord.lat, shopCoord.lng],
            [buyerCoord.lat, buyerCoord.lng]
        ], {
            color: '#ee4d2d',
            weight: 2.5,
            opacity: 0.75,
            dashArray: '10, 8'
        }).addTo(map);

        // Nhãn km ở giữa đường
        const midLat = (shopCoord.lat + buyerCoord.lat) / 2;
        const midLng = (shopCoord.lng + buyerCoord.lng) / 2;
        L.marker([midLat, midLng], {
            icon: L.divIcon({
                html: `<div style="background:#fff;border:1.5px solid #ee4d2d;border-radius:12px;
                    padding:2px 9px;font-size:12px;font-weight:600;color:#ee4d2d;
                    white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.15);">
                    ✈ ${km.toFixed(1)} km</div>`,
                className: '',
                iconAnchor: [36, 12]
            }),
            interactive: false
        }).addTo(map);

        // Fit bounds
        map.fitBounds([
            [shopCoord.lat, shopCoord.lng],
            [buyerCoord.lat, buyerCoord.lng]
        ], { padding: [50, 50] });

        // Cập nhật badge
// Tính phí ship: ≤100km → 1.000đ/km | >100km → 100km*1.000đ + phần dư*100đ
// 700 km (HN→ĐN)              100×1.000 + 600×100đ
const fee = km <= 100
    ? km * 1000
    : (100 * 1000) + (km - 100) * 100;

document.getElementById('routeDistance').innerHTML =
    `<i class="fa-solid fa-paper-plane"></i> ${km.toFixed(1)} km (đường chim bay)`;
document.getElementById('routeShipFee').style.display = '';
document.getElementById('shipFeeText').textContent = fmtVnd(fee);

// Cập nhật SHIPPING_VND để recalcWithVoucher() tính đúng tổng tiền
window.SHIPPING_VND = Math.round(fee);

// Cập nhật dòng phí vận chuyển trong summary bên phải
document.querySelectorAll('.summary-row').forEach(row => {
    const label = row.querySelector('span:first-child');
    if (label && label.textContent.includes('vận chuyển')) {
        row.querySelector('span:last-child').textContent = fmtVnd(fee);
    }
});
recalcWithVoucher();

        // Info bar
        document.getElementById('fromAddrText').textContent = SHOP_ADDRESS;
        document.getElementById('toAddrText').textContent   = BUYER_ADDRESS;
        document.getElementById('routeInfoBar').style.display = '';

        // Ẩn error nếu đã thành công
        document.getElementById('mapErrorMsg').style.display = 'none';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
})();
</script>
</body>

</html>