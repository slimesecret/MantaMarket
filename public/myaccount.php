<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'mantamarket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

// Lấy user_id từ session (giả sử đã đăng nhập)
$current_user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;

if (!$current_user_id) {
    die('<div style="padding:2rem;color:#555">Vui lòng đăng nhập để xem trang này.</div>');
}
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
// ============================================================
// 1. Lấy thông tin user
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$current_user_id]);
$user = $stmt->fetch();
if (!$user) {
    die('<div style="padding:2rem;color:#555">Không tìm thấy tài khoản.</div>');
}
// ============================================================
// 2. Lấy danh sách địa chỉ
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$current_user_id]);
$addresses = $stmt->fetchAll();
// ============================================================
// 3. Lấy đơn hàng kèm sản phẩm & thông tin vận chuyển
// ============================================================
$stmt = $pdo->prepare("
    SELECT o.*,
           s.shop_name, s.id AS seller_id, s.shop_slug, s.avatar_url AS shop_avatar,
           sh.provider AS shipping_provider, sh.tracking_number, sh.status AS shipping_status,
           c.code AS coupon_code
    FROM orders o
    LEFT JOIN sellers s ON s.id = o.seller_id
    LEFT JOIN shipping sh ON sh.order_id = o.id
    LEFT JOIN coupons c ON c.id = o.coupon_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$current_user_id]);
$orders = $stmt->fetchAll();
// Lấy order items cho từng đơn
$order_ids = array_column($orders, 'id');
$orders_with_items = [];
if (!empty($order_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$stmt = $pdo->prepare("
    SELECT oi.*,
           p.slug AS product_slug,
           (
               SELECT pi1.image_url
               FROM product_images pi1
               LEFT JOIN product_variants pv2 ON pv2.id = pi1.variant_id
               WHERE pi1.product_id = oi.product_id
               ORDER BY
                   (pi1.variant_id = oi.variant_id) DESC,
                   (pv2.color = oi.color) DESC,
                   pi1.is_primary DESC,
                   pi1.sort_order ASC
               LIMIT 1
           ) AS product_image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id IN ($in_placeholders)
    ORDER BY oi.created_at ASC
");
    $stmt->execute($order_ids);
    $all_items = $stmt->fetchAll();
    // Group items by order_id
    $items_by_order = [];
    foreach ($all_items as $item) {
        $items_by_order[$item['order_id']][] = $item;
    }
    foreach ($orders as $order) {
        $order['items'] = $items_by_order[$order['id']] ?? [];
        $orders_with_items[] = $order;
    }
} else {
    $orders_with_items = [];
}
// ============================================================
// 4. Lấy danh sách voucher (coupon) của user
// ============================================================
$stmt = $pdo->prepare("
    SELECT c.*,
           CASE
               WHEN c.end_date < NOW() THEN 'expired'
               WHEN c.used_count >= c.max_uses THEN 'used'
               ELSE 'valid'
           END AS coupon_status
    FROM coupons c
    WHERE c.is_active = 1
      AND (c.seller_id IS NULL OR c.seller_id IN (
            SELECT DISTINCT seller_id FROM orders WHERE user_id = ?
      ))
    ORDER BY c.end_date ASC
");
$stmt->execute([$current_user_id]);
$coupons = $stmt->fetchAll();


// ============================================================
// Helper functions
// ============================================================
function format_price(float $price): string
{
    return number_format($price, 0, ',', '.') . 'đ';
}
function order_status_label(string $status): array
{
    return match ($status) {
        'pending'    => ['CHỜ XÁC NHẬN',  'blue'],
        'confirmed'  => ['ĐÃ XÁC NHẬN',   'blue'],
        'processing' => ['ĐANG XỬ LÝ',    'blue'],
        'shipped'    => ['CHỜ GIAO HÀNG', ''],
        'delivered'  => ['HOÀN THÀNH',    'green'],
        'cancelled'  => ['ĐÃ HỦY',        ''],
        'returned'   => ['TRẢ HÀNG',      ''],
        default      => [strtoupper($status), ''],
    };
}
function order_data_status(string $status): string
{
    return match ($status) {
        'pending'    => 'cho-thanh-toan',          // Chờ xác nhận (chưa confirm)
        'confirmed',
        'processing',
        'shipped'    => 'cho-giao-hang',            // ✅ confirmed → chờ giao hàng
        'delivered'  => 'hoan-thanh',
        'cancelled'  => 'da-huy',
        'returned'   => 'tra-hang',
        default      => 'all',
    };
}
function mask_email(string $email): string
{
    [$local, $domain] = explode('@', $email, 2);
    $masked = substr($local, 0, 2) . str_repeat('*', max(3, strlen($local) - 2));
    return $masked . '@' . $domain;
}
function coupon_description(array $coupon): string
{
    if ($coupon['type'] === 'percent') {
        $desc = 'Giảm ' . $coupon['value'] . '%';
        if ($coupon['max_discount']) {
            $desc .= ' tối đa ' . format_price((float)$coupon['max_discount']);
        }
    } elseif ($coupon['type'] === 'fixed') {
        $desc = 'Giảm ' . format_price((float)$coupon['value']);
    } else {
        $desc = 'Miễn phí vận chuyển';
    }
    return $desc;
}
$display_name = $user['full_name'] ?: $user['username'] ?: 'Người dùng';
$display_username = $user['username'] ?: ('user' . $user['id']);
$avatar_url = $user['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($display_name) . '&background=ee4d2d&color=fff&size=80';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Của Tôi</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/myaccount.css" />
</head>

<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-user">
                <div class="sidebar-avatar">
                    <img src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                        onerror="this.parentElement.innerHTML='👤'">
                </div>
                <div>
                    <div class="sidebar-username"><?= htmlspecialchars($display_username) ?></div>
                    <div class="sidebar-edit">
                        <svg width="12" height="12" viewBox="0 0 12 12" style="margin-right:4px;">
                            <path d="M8.54 0L6.987 1.56l3.46 3.48L12 3.48M0 8.52l.073 3.428L3.46 12l6.21-6.18-3.46-3.48" fill="#9B9B9B" fill-rule="evenodd" />
                        </svg>
                        Sửa Hồ Sơ
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">
                        <span class="nav-icon">
                            <img src="https://down-vn.img.susercontent.com/file/ba61750a46794d8847c3f463c5e71cc4" alt="icon">
                        </span>
                        Tài Khoản Của Tôi
                    </div>
                    <div class="sidebar-subnav">
                        <a href="javascript:void(0)" onclick="showPage('profile',this)" class="active">Hồ Sơ</a>
                        <a href="javascript:void(0)" onclick="showPage('address',this)">Địa Chỉ</a>
                    </div>
                </div>
                <div class="sidebar-divider"></div>
                <div class="sidebar-nav-section">
                    <!-- SAU -->
                    <div class="sidebar-nav-title" style="cursor:pointer" onclick="showPage('orders',this);resetOrderFilter();">
                        <span class="nav-icon">
                            <img src="https://down-vn.img.susercontent.com/file/f0049e9df4e536bc3e7f140d071e9078">
                        </span>
                        Đơn Mua
                    </div>
                </div>
                <div class="sidebar-nav-section">
                    <!-- SAU -->
                    <div class="sidebar-nav-title" style="cursor:pointer" onclick="showPage('vouchers',this)">
                        <span class="nav-icon">
                            <img src="https://down-vn.img.susercontent.com/file/84feaa363ce325071c0a66d3c9a88748">
                        </span>
                        Kho Voucher
                    </div>
                </div>


                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title" style="cursor:pointer" onclick="showPage('cart',this)">

                        <span class="nav-icon">
                            <svg viewBox="0 0 26 20" width="20" height="20">
                                <polyline
                                    fill="none"
                                    points="2 1.7 5.5 1.7 9.6 18.3 21.2 18.3 24.6 6.1 7 6.1"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-miterlimit="10"
                                    stroke-width="2.5">
                                </polyline>
                            </svg>
                        </span>

                        Giỏ hàng

                    </div>






            </nav>
        </aside>
        <!-- Main Content -->
        <main class="content">
            <!-- ====== PROFILE PAGE ====== -->
            <div id="page-profile" class="page active">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h1>Hồ Sơ Của Tôi</h1>
                        <p>Quản lý thông tin hồ sơ để bảo mật tài khoản</p>
                    </div>
                    <div class="profile-card-body">


                        <!-- Tìm phần profile-form và thay toàn bộ bằng: -->
                        <div class="profile-form" id="profileForm">
                            <div class="form-row">
                                <label class="form-label">Tên đăng nhập</label>
                                <div class="form-control-wrap">


                                    <?php $has_real_username = !empty($user['username']) && empty($user['username_changed']); ?>
                                    <input type="text" id="inp-username" class="form-input"
                                        value="<?= htmlspecialchars($user['username'] ?? '') ?>"
                                        placeholder="Đặt tên đăng nhập"
                                        <?= !empty($user['username']) ? 'disabled' : '' ?>>
                                    <div class="form-hint">
                                        <?= !empty($user['username'])
                                            ? 'Tên đăng nhập không thể thay đổi.'
                                            : 'Bạn chưa có tên đăng nhập. Hãy đặt ngay!' ?>
                                    </div>


                                </div>
                            </div>
                            <div class="form-row">
                                <label class="form-label">Tên</label>
                                <div class="form-control-wrap">
                                    <input type="text" id="inp-fullname" class="form-input"
                                        value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="form-label">Email</label>
                                <div class="form-control-wrap">


                                    <div class="email-row">
                                        <span class="email-text" id="email-display">
                                            <?= $user['email'] ? htmlspecialchars(mask_email($user['email'])) : 'Chưa có email' ?>
                                        </span>
                                    </div>
                                    <div class="form-hint">Email không thể thay đổi.</div>


                                </div>
                            </div>
                            <div class="form-row">
                                <label class="form-label">Số điện thoại</label>
                                <div class="form-control-wrap">
                                    <input type="text" id="inp-phone" class="form-input"
                                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                        placeholder="Nhập số điện thoại">
                                </div>
                            </div>
                            <!-- Đổi mật khẩu -->
                            <div class="form-row">
                                <label class="form-label">Mật khẩu</label>
                                <div class="form-control-wrap">
                                    <?php $has_password = !empty($user['password']); ?>
                                    <button type="button" class="btn-change-pw" onclick="togglePasswordSection()">
                                        <?= $has_password ? 'Thay đổi mật khẩu' : 'Đặt mật khẩu' ?>
                                    </button>
                                </div>
                            </div>

                            <div id="password-section" style="display:none;">
                                <?php if ($has_password): ?>
                                    <div class="form-row">
                                        <label class="form-label">Mật khẩu hiện tại</label>
                                        <div class="form-control-wrap">
                                            <input type="password" id="inp-old-pw" class="form-input" placeholder="Nhập mật khẩu hiện tại">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="form-row">
                                    <label class="form-label">Mật khẩu mới</label>
                                    <div class="form-control-wrap">
                                        <input type="password" id="inp-new-pw" class="form-input" placeholder="Ít nhất 6 ký tự">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <label class="form-label">Xác nhận mật khẩu</label>
                                    <div class="form-control-wrap">
                                        <input type="password" id="inp-confirm-pw" class="form-input" placeholder="Nhập lại mật khẩu mới">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-label"></div>
                                    <div class="form-control-wrap">
                                        <button class="btn-save" onclick="savePassword(<?= $has_password ? 'true' : 'false' ?>)">
                                            <?= $has_password ? 'Cập nhật mật khẩu' : 'Đặt mật khẩu' ?>
                                        </button>
                                        <button class="btn-cancel-pw" onclick="togglePasswordSection()" style="margin-left:8px;">Hủy</button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-label"></div>
                                <div class="form-control-wrap">
                                    <button class="btn-save" onclick="saveProfile()">Lưu</button>
                                </div>
                            </div>
                        </div>





                        <!-- Thay phần avatar-section: -->
                        <div class="avatar-section">
                            <div class="avatar-circle" id="avatarCircle">
                                <img id="avatarPreview" src="<?= htmlspecialchars($avatar_url) ?>" alt="avatar"
                                    style="width:100%;height:100%;object-fit:cover;"
                                    onerror="this.parentElement.innerHTML='👤'">
                            </div>
                            <input type="file" id="avatarFileInput" accept=".jpg,.jpeg,.png,.webp" style="display:none"
                                onchange="uploadAvatar(this)">
                            <button class="btn-choose-img" onclick="document.getElementById('avatarFileInput').click()">
                                Chọn Ảnh
                            </button>
                            <div class="avatar-hint">Dung lượng file tối đa 1 MB<br>Định dạng: .JPEG, .PNG</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ====== ADDRESS PAGE ====== -->
            <div id="page-address" class="page">
                <div class="address-card">
                    <div class="address-card-header">
                        <h1>Địa chỉ của tôi</h1>
                        <button class="btn-add-bank" onclick="openAddressModal()"><span class="plus-icon">+</span> Thêm địa chỉ</button>
                    </div>
                    <div class="address-section-title">Địa chỉ</div>
                    <div class="address-list">
                        <?php if (empty($addresses)): ?>
                            <div style="padding:2rem;color:#999;text-align:center;">Bạn chưa có địa chỉ nào.</div>
                        <?php else: ?>
                            <?php foreach ($addresses as $addr): ?>
                                <div class="address-item">
                                    <div class="address-info">
                                        <div class="address-name-row">
                                            <span class="address-name"><?= htmlspecialchars($addr['full_name']) ?></span>
                                            <span class="address-divider-v"></span>
                                            <span class="address-phone">(+84) <?= htmlspecialchars(ltrim($addr['phone'], '0')) ?></span>
                                        </div>
                                        <div class="address-detail">
                                            <?= htmlspecialchars($addr['address_line']) ?><br>
                                            <?= htmlspecialchars($addr['ward']) ?>, <?= htmlspecialchars($addr['district']) ?>, <?= htmlspecialchars($addr['province']) ?>
                                        </div>
                                        <?php if ($addr['is_default']): ?>
                                            <span class="badge-macdinh">Mặc định</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="address-actions">
                                        <div class="address-action-links">
                                            <button class="btn-capnhat" onclick="openEditAddressModal(<?= $addr['id'] ?>)">Cập nhật</button>
                                            <?php if (!$addr['is_default']): ?>
                                                <button class="btn-xoa" onclick="deleteAddress(<?= $addr['id'] ?>)">Xóa</button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$addr['is_default']): ?>
                                            <button class="btn-thietlap" onclick="setDefaultAddress(<?= $addr['id'] ?>)">Thiết lập mặc định</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- ====== ORDERS PAGE ====== -->
            <div id="page-orders" class="page">
                <div class="order-card">
                    <div class="order-tabs">
                        <button class="order-tab active" data-filter="all">Tất cả</button>
                        <button class="order-tab" data-filter="cho-thanh-toan">Chờ xác nhận</button>
                        <button class="order-tab" data-filter="cho-giao-hang">Chờ giao hàng</button>
                        <button class="order-tab" data-filter="hoan-thanh">Hoàn thành</button>
                        <button class="order-tab" data-filter="da-huy">Đã hủy</button>
                        <button class="order-tab" data-filter="tra-hang">Trả hàng/Hoàn tiền</button>
                    </div>
                    <div class="order-search-bar">
                        <input type="text" id="order-search-input"
                            placeholder="Bạn có thể tìm kiếm theo tên Shop, ID đơn hàng hoặc Tên Sản phẩm"
                            oninput="filterOrderBySearch(this.value)">
                    </div>
                    <div id="order-empty-state" style="display:none;">
                        <div class="empty-order-state">
                            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="20" y="35" width="80" height="65" rx="4" fill="#e0e0e0" />
                                <rect x="30" y="20" width="60" height="20" rx="3" fill="#bdbdbd" />
                                <circle cx="45" cy="105" r="8" fill="#9e9e9e" />
                                <circle cx="75" cy="105" r="8" fill="#9e9e9e" />
                                <path d="M20 55 H100" stroke="#bdbdbd" stroke-width="2" />
                                <rect x="35" y="65" width="50" height="6" rx="3" fill="#bdbdbd" />
                                <rect x="45" y="78" width="30" height="6" rx="3" fill="#bdbdbd" />
                            </svg>
                            <p>Chưa có đơn hàng</p>
                        </div>
                    </div>
                    <?php if (empty($orders_with_items)): ?>
                        <div class="empty-order-state">
                            <p>Bạn chưa có đơn hàng nào.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders_with_items as $order):
                            [$status_label, $status_color] = order_status_label($order['order_status']);
                            $data_status = order_data_status($order['order_status']);
                            $is_delivered = $order['order_status'] === 'delivered';
                            $is_cancelled = $order['order_status'] === 'cancelled';
                            $is_returned  = $order['order_status'] === 'returned';
                            $is_pending   = in_array($order['order_status'], ['pending', 'confirmed', 'processing']);
                            $is_shipped   = $order['order_status'] === 'shipped';
                            // Shipping icon
                            $shipping_icon = match ($order['shipping_status'] ?? '') {
                                'in_transit', 'out_for_delivery' => '🚚',
                                'delivered'  => '✅',
                                'failed'     => '❌',
                                default      => '📦',
                            };
                            $shipping_label = match ($order['shipping_status'] ?? '') {
                                'waiting_pickup'    => 'Đang chờ lấy hàng',
                                'picked_up'         => 'Đã lấy hàng',
                                'in_transit'        => 'Đang vận chuyển',
                                'out_for_delivery'  => 'Đang giao hàng',
                                'delivered'         => 'Giao hàng thành công',
                                'failed'            => 'Giao hàng thất bại',
                                'returned'          => 'Đã hoàn hàng',
                                default             => '',
                            };
                        ?>
                            <div class="order-group"
                                data-status="<?= $data_status ?>"
                                data-order-id="<?= $order['id'] ?>"
                                data-shop="<?= htmlspecialchars(strtolower($order['shop_name'])) ?>"
                                onclick="openOrderDetail(<?= $order['id'] ?>, event)"
                                data-order-code="<?= htmlspecialchars(strtolower($order['order_code'])) ?>"
                                data-products="<?= htmlspecialchars(strtolower(implode(' ', array_column($order['items'], 'product_name')))) ?>">
                                <div class="order-shop-row">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="width:28px;height:28px;border-radius:6px;overflow:hidden;background:#f0f0f0;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid #eee;">
                                            <?php if (!empty($order['shop_avatar'])): ?>
                                                <img src="<?= htmlspecialchars($order['shop_avatar']) ?>"
                                                    alt="<?= htmlspecialchars($order['shop_name'] ?? '') ?>"
                                                    style="width:100%;height:100%;object-fit:cover;"
                                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                                <span style="display:none;font-size:12px;font-weight:700;color:#ee4d2d;">
                                                    <?= strtoupper(substr($order['shop_name'] ?? 'S', 0, 1)) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size:12px;font-weight:700;color:#ee4d2d;">
                                                    <?= strtoupper(substr($order['shop_name'] ?? 'S', 0, 1)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="/MantaMarket/public/index.php?seller=<?= htmlspecialchars($order['shop_slug'] ?? '') ?>"
                                            class="btn-view-shop" style="text-decoration:none;">
                                            <?= htmlspecialchars($order['shop_name'] ?? 'Xem Shop') ?>
                                        </a>
                                    </div>

                                    <div class="order-delivery-row" style="margin-left:auto;">
                                        <?php if ($shipping_label): ?>
                                            <span style="color:#555;font-size:12.5px;"><?= $shipping_icon ?> <?= htmlspecialchars($shipping_label) ?></span>
                                        <?php endif; ?>
                                        <span class="order-status-text <?= $status_color ?>"><?= $status_label ?></span>
                                    </div>
                                </div>


                                <?php foreach ($order['items'] as $idx => $item): ?>
                                    <div class="order-product <?= $idx >= 2 ? 'order-product-hidden' : '' ?>"
                                        style="<?= $idx >= 2 ? 'display:none;' : '' ?>">
                                        <img class="order-product-img"
                                            src="<?= htmlspecialchars($item['product_image'] ?? '') ?>"
                                            onerror="this.style.background='#f0f0f0';this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2264%22 height=%2264%22><rect fill=%22%23eee%22 width=%2264%22 height=%2264%22/><text x=%2250%25%22 y=%2255%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2224%22>📦</text></svg>'">
                                        <div class="order-product-info">
                                            <div class="order-product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <?php if ($item['color'] || $item['size']): ?>
                                                <div class="order-product-variant">
                                                    Phân loại: <?= htmlspecialchars(implode(' / ', array_filter([$item['color'], $item['size']]))) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="order-product-qty">x<?= (int)$item['quantity'] ?></div>
                                        </div>
                                        <div class="order-product-price">
                                            <?php if ($item['discount'] > 0): ?>
                                                <div class="order-price-original"><?= format_price((float)$item['unit_price']) ?></div>
                                                <div class="order-price-final"><?= format_price((float)($item['unit_price'] - $item['discount'])) ?></div>
                                            <?php else: ?>
                                                <div class="order-price-plain"><?= format_price((float)$item['unit_price']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($order['items']) > 2): ?>
                                    <div class="order-show-more" onclick="toggleOrderItems(this, event)">
                                        <span class="show-more-text">
                                            Xem thêm <?= count($order['items']) - 2 ?> sản phẩm ▾
                                        </span>
                                    </div>
                                <?php endif; ?>




                                <?php if ($is_pending): ?>
                                    <div class="order-pending-info">
                                        <span class="clock-icon">⏱</span>
                                        Mã đơn hàng: <strong style="margin:0 3px;"><?= htmlspecialchars($order['order_code']) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="order-total-row">
                                    <span class="order-total-label">Thành tiền:</span>
                                    <span class="order-total-price"><?= format_price((float)$order['total_amount']) ?></span>
                                </div>
                                <div class="order-action-row">
                                    <div>
                                        <?php if ($is_cancelled && $order['cancelled_reason']): ?>
                                            <div class="order-cancel-note"><?= htmlspecialchars($order['cancelled_reason']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($is_delivered): ?>
                                            <div class="order-review-note">Nhận hàng lúc: <?= $order['delivered_at'] ? date('d/m/Y', strtotime($order['delivered_at'])) : '' ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-buttons">
                                        <?php if ($is_pending): ?>
                                            <button class="btn-secondary-order"
                                                onclick="requestCancelOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_code']) ?>', event)">
                                                Hủy Đơn Hàng
                                            </button>
                                            <button class="btn-secondary-order">Liên Hệ Người Bán</button>


                                        <?php elseif ($is_shipped): ?>
                                            <button class="btn-secondary-order">Liên Hệ Người Bán</button>
                                        <?php elseif ($is_delivered): ?>
                                            <button class="btn-primary-order">Đánh Giá</button>
                                            <button class="btn-secondary-order">Yêu Cầu Trả Hàng/Hoàn Tiền</button>
                                    
                                        <?php elseif ($is_cancelled): ?>
                                            <button class="btn-primary-order">Mua Lại</button>
                                            <button class="btn-secondary-order">Liên Hệ Người Bán</button>
                                        <?php elseif ($is_returned): ?>
                                            <button class="btn-trao-doi">🔄 Trao Đổi Thêm</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- ====== VOUCHERS PAGE ====== -->
            <div id="page-vouchers" class="page">
                <div class="voucher-card">
                    <div class="voucher-header">
                        <h1>Kho Voucher</h1>
                    </div>
                    <div class="voucher-input-row">
                        <span class="voucher-input-label">Mã Voucher</span>
                        <input class="voucher-input" id="voucher-search-input" type="text"
                            placeholder="Nhập mã voucher để tìm kiếm "
                            oninput="filterVoucherByCode(this.value)">
                    </div>
                    <div class="voucher-cats">
                        <button class="voucher-cat active" data-type="all">Tất Cả</button>
                        <button class="voucher-cat" data-type="valid">Chưa sử dụng</button>
                        <button class="voucher-cat" data-type="used">Đã sử dụng</button>
                        <button class="voucher-cat" data-type="expired">Hết hạn</button>
                    </div>




                    <div class="voucher-grid">
                        <?php if (empty($coupons)): ?>
                            <div style="padding:2rem;color:#999;text-align:center;grid-column:1/-1;">Không có voucher nào.</div>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon):
                                $cstatus = $coupon['coupon_status'];
                                $exp_date = date('d.m.Y', strtotime($coupon['end_date']));
                                $min_val = $coupon['min_order_value'] ? format_price((float)$coupon['min_order_value']) : '0đ';
                            ?>
                                <div class="voucher-item <?= $cstatus ?>">
                                    <?php if ($cstatus === 'used'): ?>
                                        <div class="voucher-used-label">Đã sử dụng</div>
                                    <?php elseif ($cstatus === 'expired'): ?>
                                        <div class="voucher-used-label">Hết hạn</div>
                                    <?php elseif ($coupon['per_user_limit'] > 1): ?>
                                        <div class="voucher-count">x <?= (int)$coupon['per_user_limit'] ?></div>
                                    <?php endif; ?>
                                    <div class="voucher-left">
                                        <div class="voucher-logo-s">M</div>
                                        <div class="voucher-logo-text">MANTA</div>
                                    </div>
                                    <div class="voucher-right">
                                        <div class="voucher-info">
                                            <div class="voucher-title"><?= htmlspecialchars(coupon_description($coupon)) ?></div>
                                            <div class="voucher-code-badge">
                                                <span class="voucher-code-text"><?= htmlspecialchars($coupon['code']) ?></span>
                                                <button class="btn-copy-code" onclick="copyVoucherCode('<?= htmlspecialchars($coupon['code']) ?>', this)" title="Sao chép mã">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="voucher-condition">Đơn Tối Thiểu <?= $min_val ?></div>
                                            <div class="voucher-expiry">HSD: <?= $exp_date ?></div>
                                        </div>
                                        <?php if ($cstatus === 'valid'): ?>
                                            <button class="btn-dung-ngay">Dùng ngay</button>
                                        <?php elseif ($cstatus === 'used'): ?>
                                            <button class="btn-disabled" disabled>Đã dùng</button>
                                        <?php else: ?>
                                            <button class="btn-disabled" disabled>Hết hạn</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>



                </div>
            </div>

            <!-- ====== Cart PAGE ====== -->
            <!-- ====== Cart PAGE ====== -->
            <div id="page-cart" class="page">
                <div class="cart-page-wrap">

                    <!-- HEADER ROW -->
                    <div class="cart-col-header">
                        <label class="cart-check-wrap">
                            <input type="checkbox" id="checkAll" onchange="toggleCheckAll(this)">
                            <span>Sản Phẩm</span>
                        </label>
                        <span class="cart-col-price">Đơn Giá</span>
                        <span class="cart-col-qty">Số Lượng</span>
                        <span class="cart-col-total">Số Tiền</span>
                        <span class="cart-col-action">Thao Tác</span>
                    </div>

                    <!-- CART BODY -->
                    <div id="cartPageBody">
                        <div class="cart-loading">
                            <div class="cart-spinner"></div>
                            <span>Đang tải giỏ hàng...</span>
                        </div>
                    </div>

                    <!-- STICKY FOOTER -->
                    <div class="cart-sticky-footer">
                        <div class="cart-footer-left">
                            <label class="cart-check-wrap">
                                <input type="checkbox" id="checkAllBottom" onchange="toggleCheckAll(this)">
                                <span>Chọn Tất Cả (<span id="totalItemCount">0</span>)</span>
                            </label>
                            <button class="cart-btn-delete-selected" onclick="deleteSelected()">Xóa</button>
                        </div>
                        <div class="cart-footer-right">
                            <div class="cart-total-row">
                                <span class="cart-total-label">Tổng cộng (<span id="selectedCount">0</span> sản phẩm):</span>
                                <span class="cart-total-price" id="cartTotalPrice">0đ</span>
                            </div>
                            <button class="cart-checkout-btn" onclick="proceedCheckout()">
                                Mua Hàng
                            </button>
                        </div>
                    </div>

                </div>
            </div>


        </main>



    </div>
    <!-- ====== MODAL CẬP NHẬT ĐỊA CHỈ ====== -->
    <div id="modal-edit-address" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;padding:28px 32px;position:relative;">
            <div style="font-size:17px;font-weight:600;margin-bottom:20px;">Cập nhật địa chỉ</div>
            <input type="hidden" id="edit-addr-id">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="addr-field">
                    <label class="addr-label">Họ và tên</label>
                    <input class="addr-input" id="edit-addr-fullname" type="text" placeholder="Nhập họ tên">
                </div>
                <div class="addr-field">
                    <label class="addr-label">Số điện thoại</label>
                    <input class="addr-input" id="edit-addr-phone" type="text" placeholder="Nhập số điện thoại">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
                <div class="addr-field">
                    <label class="addr-label">Tỉnh / Thành phố</label>
                    <select class="addr-input" id="edit-addr-province" onchange="loadEditDistricts(this.value)">
                        <option value="">Chọn tỉnh/thành</option>
                    </select>
                </div>
                <div class="addr-field">
                    <label class="addr-label">Quận / Huyện</label>
                    <select class="addr-input" id="edit-addr-district" onchange="loadEditWards(this.value)" disabled>
                        <option value="">Chọn quận/huyện</option>
                    </select>
                </div>
                <div class="addr-field">
                    <label class="addr-label">Phường / Xã</label>
                    <select class="addr-input" id="edit-addr-ward" disabled>
                        <option value="">Chọn phường/xã</option>
                    </select>
                </div>
            </div>

            <div class="addr-field" style="margin-top:14px;">
                <label class="addr-label">Địa chỉ cụ thể</label>
                <input class="addr-input" id="edit-addr-line" type="text" placeholder="Số nhà, tên đường...">
            </div>

            <div style="margin-top:16px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;cursor:pointer;">
                    <input type="checkbox" id="edit-addr-default" style="width:15px;height:15px;accent-color:#ee4d2d;">
                    Đặt làm địa chỉ mặc định
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:22px;">
                <button class="btn-cancel-pw" onclick="closeEditAddressModal()">Trở Lại</button>
                <button class="btn-save" onclick="submitEditAddress()">Hoàn thành</button>
            </div>

            <button onclick="closeEditAddressModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:20px;cursor:pointer;color:#999;">×</button>
        </div>
    </div>
    <!-- ====== MODAL THÊM ĐỊA CHỈ ====== -->
    <div id="modal-add-address" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;padding:28px 32px;position:relative;">
            <div style="font-size:17px;font-weight:600;margin-bottom:20px;">Địa chỉ mới</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="addr-field">
                    <label class="addr-label">Họ và tên</label>
                    <input class="addr-input" id="addr-fullname" type="text" placeholder="Nhập họ tên">
                </div>
                <div class="addr-field">
                    <label class="addr-label">Số điện thoại</label>
                    <input class="addr-input" id="addr-phone" type="text" placeholder="Nhập số điện thoại">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
                <div class="addr-field">
                    <label class="addr-label">Tỉnh / Thành phố</label>
                    <select class="addr-input" id="addr-province" onchange="loadDistricts(this.value)">
                        <option value="">Chọn tỉnh/thành</option>
                    </select>
                </div>
                <div class="addr-field">
                    <label class="addr-label">Quận / Huyện</label>
                    <select class="addr-input" id="addr-district" onchange="loadWards(this.value)" disabled>
                        <option value="">Chọn quận/huyện</option>
                    </select>
                </div>
                <div class="addr-field">
                    <label class="addr-label">Phường / Xã</label>
                    <select class="addr-input" id="addr-ward" disabled>
                        <option value="">Chọn phường/xã</option>
                    </select>
                </div>
            </div>

            <div class="addr-field" style="margin-top:14px;">
                <label class="addr-label">Địa chỉ cụ thể</label>
                <input class="addr-input" id="addr-line" type="text" placeholder="Số nhà, tên đường...">
            </div>

            <div style="margin-top:16px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;cursor:pointer;">
                    <input type="checkbox" id="addr-default" style="width:15px;height:15px;accent-color:#ee4d2d;">
                    Đặt làm địa chỉ mặc định
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:22px;">
                <button class="btn-cancel-pw" onclick="closeAddressModal()">Trở Lại</button>
                <button class="btn-save" onclick="submitAddress()">Hoàn thành</button>
            </div>

            <button onclick="closeAddressModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:20px;cursor:pointer;color:#999;">×</button>
        </div>
    </div>
    <!-- ====== MODAL CHI TIẾT ĐƠN HÀNG ====== -->
    <div id="modal-order-detail" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
            <!-- Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px 14px;border-bottom:1px solid #f0f0f0;">
                <div>
                    <div style="font-size:16px;font-weight:600;color:#222;">Chi Tiết Đơn Hàng</div>
                    <div style="font-size:12px;color:#999;margin-top:2px;" id="od-code"></div>
                </div>
                <button onclick="closeOrderDetail()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#aaa;line-height:1;">×</button>
            </div>
            <!-- Loading -->
            <div id="od-loading" style="text-align:center;padding:48px;color:#aaa;">
                <div style="font-size:28px;margin-bottom:8px;">⏳</div>
                Đang tải...
            </div>
            <!-- Content -->
            <div id="od-content" style="display:none;padding:20px 24px 24px;">

                <!-- Trạng thái -->
                <div id="od-status-bar" style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:8px;margin-bottom:18px;background:#f8f9fa;">
                    <span id="od-status-icon" style="font-size:20px;"></span>
                    <div>
                        <div id="od-status-text" style="font-weight:600;font-size:14px;"></div>
                        <div id="od-status-sub" style="font-size:12px;color:#888;margin-top:1px;"></div>
                    </div>
                    <div id="od-estimate-badge" style="margin-left:auto;font-size:12px;color:#15803d;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:4px 10px;display:none;"></div>
                </div>

                <!-- Thông tin đơn -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
                    <div class="od-info-box">
                        <div class="od-info-label">Mã đơn hàng</div>
                        <div class="od-info-value" id="od-code2"></div>
                    </div>
                    <div class="od-info-box">
                        <div class="od-info-label">Ngày đặt hàng</div>
                        <div class="od-info-value" id="od-date"></div>
                    </div>
                    <div class="od-info-box">
                        <div class="od-info-label">Phương thức thanh toán</div>
                        <div class="od-info-value" id="od-payment"></div>
                    </div>
                    <div class="od-info-box">
                        <div class="od-info-label">Trạng thái thanh toán</div>
                        <div class="od-info-value" id="od-payment-status"></div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#fff7f5;border-radius:8px;margin-bottom:18px;border:1px solid #fde8e3;">
                    <div>
                        <div id="od-shop" style="font-weight:600;font-size:14px;color:#ee4d2d;"></div>
                    </div>
                </div>

                <!-- Địa chỉ nhận -->
                <div style="padding:12px 14px;background:#f8f9fa;border-radius:8px;margin-bottom:18px;">
                    <div style="font-size:12px;color:#888;margin-bottom:4px;">Địa chỉ nhận hàng</div>
                    <div id="od-address" style="font-size:13.5px;color:#333;"></div>
                </div>

                <!-- Danh sách sản phẩm -->
                <div style="margin-bottom:18px;">
                    <div style="font-size:13px;font-weight:600;color:#555;margin-bottom:10px;">Sản phẩm</div>
                    <div id="od-items" style="display:flex;flex-direction:column;gap:10px;"></div>
                </div>

                <!-- Tóm tắt tiền -->
                <div style="border-top:1px solid #f0f0f0;padding-top:14px;">
                    <div class="od-money-row">
                        <span>Tạm tính</span>
                        <span id="od-subtotal"></span>
                    </div>
                    <div class="od-money-row">
                        <span>Phí vận chuyển</span>
                        <span id="od-shipping-fee"></span>
                    </div>
                    <div class="od-money-row" id="od-voucher-row" style="display:none;">
                        <span>Voucher <span id="od-coupon-code" style="font-size:11px;background:#fff0ee;color:#ee4d2d;border-radius:4px;padding:1px 6px;margin-left:4px;"></span></span>
                        <span id="od-discount" style="color:#ee4d2d;"></span>
                    </div>
                    <div class="od-money-row" style="font-weight:700;font-size:15px;border-top:1px solid #f0f0f0;margin-top:8px;padding-top:10px;">
                        <span>Tổng cộng</span>
                        <span id="od-total" style="color:#ee4d2d;font-size:17px;"></span>
                    </div>
                </div>

            </div>
        </div>
    </div>


</body>

</html>