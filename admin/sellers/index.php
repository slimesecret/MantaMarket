<?php
$user   = new app_Libs_UserIdentity();
$router = new app_Libs_Router();
$seller = new app_Models_Sellers();
$db     = new app_Libs_DbConnection();
$action = $router->getPOST("action");
$id     = intval($router->getPOST("id") ?? $router->getGET("id"));

// ── XÓA 1 NGƯỜI BÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $seller->buildQueryParams([
        "where"  => "id = :id",
        "params" => [":id" => $id]
    ])->delete();
    header("Location: /MantaMarket/admin/index.php?page=sellers");
    exit();
}
// ── XÓA NHIỀU NGƯỜI BÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    foreach ($ids as $uid) {
        $uid = intval($uid);
        if ($uid) {
            $seller->buildQueryParams([
                "where"  => "id = :id",
                "params" => [":id" => $uid]
            ])->delete();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=sellers");
    exit();
}
// ── ẨN/HIỆN NHIỀU NGƯỜI BÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_toggle') {
    $ids    = $_POST['ids'] ?? [];
    $status = intval($_POST['is_active'] ?? 1);
    foreach ($ids as $uid) {
        $uid = intval($uid);
        if ($uid) {
            $seller->buildQueryParams([
                "value"  => "is_active = :is_active",
                "where"  => "id = :id",
                "params" => [":is_active" => $status, ":id" => $uid]
            ])->update();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=sellers");
    exit();
}
// ── XÁC MINH / BỎ XÁC MINH NHIỀU NGƯỜI BÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_verify') {
    $ids    = $_POST['ids'] ?? [];
    $status = intval($_POST['is_verified'] ?? 1);
    foreach ($ids as $uid) {
        $uid = intval($uid);
        if ($uid) {
            $seller->buildQueryParams([
                "value"  => "is_verified = :is_verified",
                "where"  => "id = :id",
                "params" => [":is_verified" => $status, ":id" => $uid]
            ])->update();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=sellers");
    exit();
}
// ── THÊM NGƯỜI BÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'insert') {
    $shop_name  = trim($router->getPOST("shop_name"));
    $shop_slug  = trim($router->getPOST("shop_slug"));
    $email      = trim($router->getPOST("email"));
    $phone      = trim($router->getPOST("phone"));
    $address    = trim($router->getPOST("address"));
    $avatar_url = trim($router->getPOST("avatar_url"));
    $is_verified = intval($router->getPOST("is_verified") ?? 0);
    $is_active   = intval($router->getPOST("is_active")   ?? 1);

    // Kiểm tra email trùng
    $checkEmail = $seller->buildQueryParams([
        "where"  => "email = :email",
        "params" => [":email" => $email]
    ])->selectOne();
    if ($checkEmail) {
        header("Location: /MantaMarket/admin/index.php?page=sellers&error=email");
        exit();
    }
    // Kiểm tra slug trùng
    $checkSlug = $seller->buildQueryParams([
        "where"  => "shop_slug = :slug",
        "params" => [":slug" => $shop_slug]
    ])->selectOne();
    if ($checkSlug) {
        header("Location: /MantaMarket/admin/index.php?page=sellers&error=slug");
        exit();
    }

    $seller->buildQueryParams([
        "field" => "(shop_name, shop_slug, email, phone, address, avatar_url, is_verified, is_active) VALUES (:shop_name, :shop_slug, :email, :phone, :address, :avatar_url, :is_verified, :is_active)",
        "value" => [
            ":shop_name"   => $shop_name,
            ":shop_slug"   => $shop_slug,
            ":email"       => $email,
            ":phone"       => $phone,
            ":address"     => $address,
            ":avatar_url"  => $avatar_url,
            ":is_verified" => $is_verified,
            ":is_active"   => $is_active,
        ]
    ])->insert();
    header("Location: /MantaMarket/admin/index.php?page=sellers");
    exit();
}
// ── CẬP NHẬT NGƯỜI BÁN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $id) {
    $shop_name  = trim($router->getPOST("shop_name"));
    $shop_slug  = trim($router->getPOST("shop_slug"));
    $email      = trim($router->getPOST("email"));
    $phone      = trim($router->getPOST("phone"));
    $address    = trim($router->getPOST("address"));
    $avatar_url = trim($router->getPOST("avatar_url"));
    $is_verified = intval($router->getPOST("is_verified") ?? 0);
    $is_active   = intval($router->getPOST("is_active")   ?? 1);

    // Kiểm tra email trùng (trừ chính seller đó)
    $checkEmail = $seller->buildQueryParams([
        "where"  => "email = :email AND id != :id",
        "params" => [":email" => $email, ":id" => $id]
    ])->selectOne();
    if ($checkEmail) {
        header("Location: /MantaMarket/admin/index.php?page=sellers&error=email");
        exit();
    }
    // Kiểm tra slug trùng (trừ chính seller đó)
    $checkSlug = $seller->buildQueryParams([
        "where"  => "shop_slug = :slug AND id != :id",
        "params" => [":slug" => $shop_slug, ":id" => $id]
    ])->selectOne();
    if ($checkSlug) {
        header("Location: /MantaMarket/admin/index.php?page=sellers&error=slug");
        exit();
    }

    $seller->buildQueryParams([
        "value"  => "shop_name = :shop_name, shop_slug = :shop_slug, email = :email, phone = :phone, address = :address, avatar_url = :avatar_url, is_verified = :is_verified, is_active = :is_active",
        "where"  => "id = :id",
        "params" => [
            ":shop_name"   => $shop_name,
            ":shop_slug"   => $shop_slug,
            ":email"       => $email,
            ":phone"       => $phone,
            ":address"     => $address,
            ":avatar_url"  => $avatar_url,
            ":is_verified" => $is_verified,
            ":is_active"   => $is_active,
            ":id"          => $id,
        ]
    ])->update();
    header("Location: /MantaMarket/admin/index.php?page=sellers");
    exit();
}
// ── LẤY DỮ LIỆU ──
$sellerList      = $seller->buildQueryParams([])->select();
$totalAll        = count($sellerList);
$totalActive     = count(array_filter($sellerList, fn($r) => $r['is_active']   == 1));
$totalInactive   = $totalAll - $totalActive;
$totalVerified   = count(array_filter($sellerList, fn($r) => $r['is_verified'] == 1));
?>
<div class="page" id="page-sellers">
    <!-- HEADER -->
    <div class="page-header">
        <h1 class="page-title">Quản lý người bán hàng</h1>
        <!-- STAT CARDS -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div class="stat-card">
                <div class="stat-label">Tổng người bán</div>
                <div class="stat-value"><?= $totalAll ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Đang hoạt động</div>
                <div class="stat-value"><?= $totalActive ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Đã khóa</div>
                <div class="stat-value"><?= $totalInactive ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Đã xác minh</div>
                <div class="stat-value"><?= $totalVerified ?></div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:14px;color:#dc2626;font-size:14px;font-weight:600;">
            <i class="fas fa-exclamation-circle"></i>
            <?php if ($_GET['error'] === 'email'): ?>
                Email đã tồn tại, vui lòng dùng email khác!
            <?php elseif ($_GET['error'] === 'slug'): ?>
                Slug shop đã tồn tại, vui lòng chọn slug khác!
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="order-filter-bar" style="flex-wrap:wrap;gap:8px">
        <!-- Chọn tất cả -->
        <div class="order-filter-select" style="gap:6px">
            <div class="checkbox-custom" id="sellerCheckAll" onclick="toggleSellerAll(this)" style="width:14px;height:14px"></div>
            <span style="font-size:13px;color:var(--muted)">Chọn tất cả</span>
        </div>
        <!-- Lọc trạng thái -->
        <div class="order-filter-select2">
            <select id="sellerStatusFilter" onchange="sellerPage=1;renderSel()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Hoạt động</option>
                <option value="0">Đã khóa</option>
            </select>
        </div>
        <!-- Lọc xác minh -->
        <div class="order-filter-select2">
            <select id="sellerVerifyFilter" onchange="sellerPage=1;renderSel()">
                <option value="">Tất cả xác minh</option>
                <option value="1">Đã xác minh</option>
                <option value="0">Chưa xác minh</option>
            </select>
        </div>
        <!-- Tìm kiếm -->
        <div class="order-search-wrap" style="flex:1;min-width:180px">
            <i class="fas fa-search"></i>
            <input class="order-search-input" type="text" id="sellerSearch"
                placeholder="Tìm theo tên shop, email, SĐT..." oninput="sellerPage=1;renderSel()">
        </div>
        <!-- Thêm người bán -->
        <div class="page-actions">
            <button class="btn-primary" onclick="openSellerModal()">
                <i class="fas fa-plus"></i> Thêm người bán
            </button>
        </div>
        <!-- Nút xóa nhiều -->
        <button class="btn-action" onclick="sellerBulkDelete()" title="Xóa đã chọn">
            <i class="fas fa-trash"></i> Xóa
        </button>
        <!-- Nút khóa/mở -->
        <button class="btn-action" onclick="sellerBulkToggle(1)" title="Mở khóa đã chọn">
            <i class="fas fa-unlock"></i>
        </button>
        <button class="btn-action" onclick="sellerBulkToggle(0)" title="Khóa đã chọn">
            <i class="fas fa-lock"></i>
        </button>
        <!-- Nút xác minh/bỏ xác minh -->
        <button class="btn-action" onclick="sellerBulkVerify(1)" title="Xác minh đã chọn">
            <i class="fas fa-badge-check"></i>
        </button>
        <button class="btn-action" onclick="sellerBulkVerify(0)" title="Bỏ xác minh đã chọn">
            <i class="fas fa-times-circle"></i>
        </button>
    </div>

    <!-- TABLE -->
    <div class="order-table-card">
        <div class="order-table-head">
            <span style="font-size:13px;color:var(--muted)" id="sellerSelLabel">Chọn 0 người bán</span>
            <div class="pagination" id="sellerTopPag"></div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th onclick="setSellerSort('id')" style="cursor:pointer">
                            ID <i class="fas fa-sort" id="sort-sid" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Avatar</th>
                        <th onclick="setSellerSort('shop_name')" style="cursor:pointer">
                            Tên shop <i class="fas fa-sort" id="sort-shop_name" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setSellerSort('shop_slug')" style="cursor:pointer">
                            Slug <i class="fas fa-sort" id="sort-shop_slug" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setSellerSort('email')" style="cursor:pointer">
                            Email <i class="fas fa-sort" id="sort-email" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Điện thoại</th>
                        <th>Địa chỉ</th>
                        <th onclick="setSellerSort('rating')" style="cursor:pointer">
                            Đánh giá <i class="fas fa-sort" id="sort-rating" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setSellerSort('total_sales')" style="cursor:pointer">
                            Tổng bán <i class="fas fa-sort" id="sort-total_sales" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setSellerSort('is_verified')" style="cursor:pointer">
                            Xác minh <i class="fas fa-sort" id="sort-is_verified" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Trạng thái</th>
                        <th onclick="setSellerSort('created_at')" style="cursor:pointer">
                            Ngày tạo <i class="fas fa-sort" id="sort-created_at" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="sellerTableBody"></tbody>
            </table>
        </div>
        <div class="order-table-footer">
            <div style="font-size:13px;color:var(--muted)" id="sellerTableInfo"></div>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="pagination" id="sellerBotPag"></div>
                <span style="font-size:13px;color:var(--muted)" id="sellerPageLabel"></span>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL THÊM / SỬA ── -->
<div class="modal-bg" id="sellerModal" style="display:none">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
            <h2 style="font-size:20px;font-weight:800;color:var(--text)" id="sellerModalTitle">Thêm người bán</h2>
            <button onclick="closeSellerModal()" style="background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="sellerForm" method="POST" action="">
            <input type="hidden" name="action" id="sellerAction" value="insert">
            <input type="hidden" name="id"     id="sellerId"     value="">
            <div style="display:grid;gap:14px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="modal-label">Tên shop <span style="color:red">*</span></label>
                        <input class="modal-input" type="text" name="shop_name" id="sellerShopName"
                            placeholder="Tech Store Official" required>
                    </div>
                    <div>
                        <label class="modal-label">Slug <span style="color:red">*</span></label>
                        <input class="modal-input" type="text" name="shop_slug" id="sellerShopSlug"
                            placeholder="tech-store-official" required>
                    </div>
                </div>
                <div>
                    <label class="modal-label">Email <span style="color:red">*</span></label>
                    <input class="modal-input" type="email" name="email" id="sellerEmail"
                        placeholder="shop@example.com" required>
                </div>
                <div>
                    <label class="modal-label">Số điện thoại</label>
                    <input class="modal-input" type="text" name="phone" id="sellerPhone"
                        placeholder="0912345678">
                </div>
                <div>
                    <label class="modal-label">Địa chỉ</label>
                    <input class="modal-input" type="text" name="address" id="sellerAddress"
                        placeholder="123 Nguyễn Huệ, Quận 1, TP.HCM">
                </div>
                <div>
                    <label class="modal-label">Link avatar</label>
                    <input class="modal-input" type="text" name="avatar_url" id="sellerAvatar"
                        placeholder="https://cdn.store.vn/avatar.jpg">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="modal-label">Xác minh</label>
                        <select class="modal-input" name="is_verified" id="sellerIsVerified">
                            <option value="0">Chưa xác minh</option>
                            <option value="1">Đã xác minh</option>
                        </select>
                    </div>
                    <div>
                        <label class="modal-label">Trạng thái</label>
                        <select class="modal-input" name="is_active" id="sellerIsActive">
                            <option value="1">Hoạt động</option>
                            <option value="0">Khóa</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px">
                <button type="button" onclick="closeSellerModal()"
                    style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                    Hủy
                </button>
                <button type="button" onclick="submitSellerForm()" class="btn-primary" style="flex:1;justify-content:center">
                    <i class="fas fa-check"></i> Lưu người bán
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL XÁC NHẬN XÓA ── -->
<div class="modal-bg" id="sellerConfirmModal" style="display:none">
    <div class="modal-box" style="max-width:400px">
        <div style="text-align:center;padding:10px 0">
            <div style="font-size:48px;margin-bottom:12px">🗑️</div>
            <h2 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:8px">Xác nhận xóa</h2>
            <p style="font-size:14px;color:var(--muted)" id="sellerConfirmMsg">Bạn có chắc muốn xóa người bán này?</p>
        </div>
        <input type="hidden" id="sellerDeleteId" value="">
        <div style="display:flex;gap:10px;margin-top:20px">
            <button onclick="closeSellerConfirm()"
                style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                Hủy
            </button>
            <button onclick="submitDeleteSeller()"
                style="flex:1;background:#ef4444;border:none;border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:#fff;cursor:pointer;font-family:inherit">
                <i class="fas fa-trash"></i> Xóa
            </button>
        </div>
    </div>
</div>

<script>
// ── DỮ LIỆU TỪ PHP ──
const SELLER_DATA = <?= json_encode(array_values($sellerList)) ?>;

// ── STATE ──
var sellerPage    = 1;
const SELLER_PER  = 10;
var sellerSortKey = 'id';
var sellerSortAsc = true;

// ── AUTO-SLUG ──
document.getElementById('sellerShopName').addEventListener('input', function () {
    if (document.getElementById('sellerAction').value !== 'insert') return;
    document.getElementById('sellerShopSlug').value = this.value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
});

// ── SORT ──
function setSellerSort(key) {
    if (sellerSortKey === key) sellerSortAsc = !sellerSortAsc;
    else { sellerSortKey = key; sellerSortAsc = true; }
    document.querySelectorAll('[id^="sort-"]').forEach(el => {
        el.className = 'fas fa-sort';
        el.style.color = 'var(--muted)';
    });
    const ic = document.getElementById('sort-' + key);
    if (ic) {
        ic.className = sellerSortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
        ic.style.color = 'var(--primary,#7c3aed)';
    }
    sellerPage = 1;
    renderSel();
}

// ── FILTER ──
function getFilteredSellers() {
    const search = document.getElementById('sellerSearch').value.toLowerCase();
    const status = document.getElementById('sellerStatusFilter').value;
    const verify = document.getElementById('sellerVerifyFilter').value;
    return SELLER_DATA
        .filter(s => {
            const matchSearch = !search ||
                (s.shop_name && s.shop_name.toLowerCase().includes(search)) ||
                (s.email      && s.email.toLowerCase().includes(search))     ||
                (s.phone      && s.phone.includes(search));
            const matchStatus = status === '' || String(s.is_active)   === status;
            const matchVerify = verify === '' || String(s.is_verified)  === verify;
            return matchSearch && matchStatus && matchVerify;
        })
        .sort((a, b) => {
            let va = a[sellerSortKey] ?? '';
            let vb = b[sellerSortKey] ?? '';
            if (['id', 'rating', 'total_sales', 'is_verified', 'is_active'].includes(sellerSortKey)) {
                va = Number(va); vb = Number(vb);
            } else if (typeof va === 'string') {
                va = va.toLowerCase(); vb = vb.toLowerCase();
            }
            if (va < vb) return sellerSortAsc ? -1 : 1;
            if (va > vb) return sellerSortAsc ?  1 : -1;
            return 0;
        });
}

// ── RENDER ──
function renderSel() {
    const filtered   = getFilteredSellers();
    const totalPages = Math.max(1, Math.ceil(filtered.length / SELLER_PER));
    if (sellerPage > totalPages) sellerPage = totalPages;
    const start = (sellerPage - 1) * SELLER_PER;
    const page  = filtered.slice(start, start + SELLER_PER);

    document.getElementById('sellerTableBody').innerHTML = page.map(s => {
        // Trạng thái
        const isActive    = s.is_active   == 1;
        const isVerified  = s.is_verified == 1;
        const statusBg    = isActive   ? '#d1fae5' : '#fee2e2';
        const statusColor = isActive   ? '#059669' : '#dc2626';
        const verifyBg    = isVerified ? '#ede9fe' : '#f3f4f6';
        const verifyColor = isVerified ? '#7c3aed' : '#6b7280';

        // Avatar
        const avatarHtml = s.avatar_url
            ? `<img src="${s.avatar_url}" style="width:38px;height:38px;object-fit:cover;border-radius:50%;border:2px solid var(--border)">`
            : `<div style="width:38px;height:38px;border-radius:50%;background:var(--primary,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;font-weight:700">
                   ${(s.shop_name || '?')[0].toUpperCase()}
               </div>`;

        // Rating stars
        const rating = parseFloat(s.rating) || 0;
        const stars  = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

        const createdAt = s.created_at ? s.created_at.substring(0, 10) : '—';
        const address   = s.address ? (s.address.length > 30 ? s.address.substring(0, 30) + '…' : s.address) : '—';

        return `<tr>
            <td><div class="checkbox-custom row-check seller-check" data-id="${s.id}" onclick="toggleSellerRow(this)"></div></td>
            <td style="font-size:13px;color:var(--muted);font-weight:600">${s.id}</td>
            <td>${avatarHtml}</td>
            <td style="font-weight:600;font-size:14px">${s.shop_name || '—'}</td>
            <td style="font-size:12px;color:var(--muted)">${s.shop_slug || '—'}</td>
            <td style="font-size:13px">${s.email || '—'}</td>
            <td style="font-size:13px">${s.phone || '—'}</td>
            <td style="font-size:12px;color:var(--muted)" title="${s.address || ''}">${address}</td>
            <td>
                <span style="font-size:13px;color:#f59e0b" title="${rating}/5">${stars}</span>
                <span style="font-size:11px;color:var(--muted);margin-left:2px">${rating.toFixed(2)}</span>
            </td>
            <td style="font-size:13px;font-weight:600">${Number(s.total_sales).toLocaleString('vi-VN')}</td>
            <td>
                <span class="badge" style="background:${verifyBg};color:${verifyColor}">
                    ${isVerified ? '<i class="fas fa-check-circle" style="margin-right:4px"></i>Đã xác minh' : 'Chưa xác minh'}
                </span>
            </td>
            <td>
                <span class="badge" style="background:${statusBg};color:${statusColor}">
                    ${isActive ? 'Hoạt động' : 'Đã khóa'}
                </span>
            </td>
            <td style="font-size:12px">${createdAt}</td>
            <td class="actions-cell">
                <button class="btn-edit" onclick='openEditSeller(${JSON.stringify(s)})'><i class="fas fa-edit"></i> Sửa</button>
                <button class="btn-del"  onclick="confirmDeleteSeller(${s.id},'${(s.shop_name||'').replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i> Xóa</button>
            </td>
        </tr>`;
    }).join('');

    // Pagination
    const pagHtml = () => {
        let h = '';
        if (sellerPage > 1)         h += `<button class="page-btn" onclick="goToSellerPage(${sellerPage-1})"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
        for (let i = 1; i <= totalPages; i++)
            h += `<button class="page-btn${i===sellerPage?' active':''}" onclick="goToSellerPage(${i})">${i}</button>`;
        if (sellerPage < totalPages) h += `<button class="page-btn" onclick="goToSellerPage(${sellerPage+1})"><i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
        return h;
    };
    document.getElementById('sellerTopPag').innerHTML  = pagHtml();
    document.getElementById('sellerBotPag').innerHTML  = pagHtml();
    document.getElementById('sellerTableInfo').textContent =
        `${start + 1} – ${Math.min(start + SELLER_PER, filtered.length)} trên ${filtered.length} người bán`;
    document.getElementById('sellerPageLabel').textContent = `${sellerPage} / ${totalPages}`;
    updateSellerSelLabel();
}

// ── MODAL THÊM/SỬA ──
function openSellerModal() {
    document.getElementById('sellerModalTitle').textContent = 'Thêm người bán mới';
    document.getElementById('sellerAction').value    = 'insert';
    document.getElementById('sellerId').value        = '';
    document.getElementById('sellerShopName').value  = '';
    document.getElementById('sellerShopSlug').value  = '';
    document.getElementById('sellerEmail').value     = '';
    document.getElementById('sellerPhone').value     = '';
    document.getElementById('sellerAddress').value   = '';
    document.getElementById('sellerAvatar').value    = '';
    document.getElementById('sellerIsVerified').value = '0';
    document.getElementById('sellerIsActive').value   = '1';
    document.getElementById('sellerModal').style.display = 'flex';
}

function openEditSeller(s) {
    document.getElementById('sellerModalTitle').textContent = 'Chỉnh sửa người bán';
    document.getElementById('sellerAction').value    = 'update';
    document.getElementById('sellerId').value        = s.id;
    document.getElementById('sellerShopName').value  = s.shop_name  || '';
    document.getElementById('sellerShopSlug').value  = s.shop_slug  || '';
    document.getElementById('sellerEmail').value     = s.email      || '';
    document.getElementById('sellerPhone').value     = s.phone      || '';
    document.getElementById('sellerAddress').value   = s.address    || '';
    document.getElementById('sellerAvatar').value    = s.avatar_url || '';
    document.getElementById('sellerIsVerified').value = s.is_verified;
    document.getElementById('sellerIsActive').value   = s.is_active;
    document.getElementById('sellerModal').style.display = 'flex';
}

function submitSellerForm() {
    const form = document.getElementById('sellerForm');
    const data = new FormData(form);
    fetch('/MantaMarket/admin/index.php?page=sellers', {
        method: 'POST', body: data, redirect: 'follow'
    }).then(res => {
        if (res.url && res.url.includes('error=email')) {
            alert('Email đã tồn tại, vui lòng dùng email khác!'); return;
        }
        if (res.url && res.url.includes('error=slug')) {
            alert('Slug shop đã tồn tại, vui lòng chọn slug khác!'); return;
        }
        closeSellerModal();
        location.hash = 'sellers';
        location.reload();
    }).catch(() => alert('Có lỗi xảy ra, vui lòng thử lại!'));
}

function closeSellerModal() {
    document.getElementById('sellerModal').style.display = 'none';
}

// ── XÁC NHẬN XÓA ──
function confirmDeleteSeller(id, name) {
    document.getElementById('sellerConfirmMsg').textContent = `Bạn có chắc muốn xóa shop "${name}"?`;
    document.getElementById('sellerDeleteId').value = id;
    document.getElementById('sellerConfirmModal').style.display = 'flex';
}
function closeSellerConfirm() {
    document.getElementById('sellerConfirmModal').style.display = 'none';
}
function submitDeleteSeller() {
    const id   = document.getElementById('sellerDeleteId').value;
    const data = new FormData();
    data.append('action', 'delete');
    data.append('id', id);
    fetch('/MantaMarket/admin/index.php?page=sellers', { method: 'POST', body: data })
        .then(() => { closeSellerConfirm(); location.hash = 'sellers'; location.reload(); });
}

// ── CHECKBOX ──
function toggleSellerAll(el) {
    el.classList.toggle('checked');
    el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
    const checked = el.classList.contains('checked');
    document.querySelectorAll('#sellerTableBody .seller-check').forEach(c => {
        c.className = 'checkbox-custom row-check seller-check' + (checked ? ' checked' : '');
        c.innerHTML = checked ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
    });
    updateSellerSelLabel();
}
function toggleSellerRow(el) {
    el.classList.toggle('checked');
    el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
    updateSellerSelLabel();
}
function updateSellerSelLabel() {
    const count = document.querySelectorAll('#sellerTableBody .seller-check.checked').length;
    const lbl   = document.getElementById('sellerSelLabel');
    if (lbl) lbl.textContent = `Chọn ${count} người bán`;
}
function getCheckedSellerIds() {
    return [...document.querySelectorAll('#sellerTableBody .seller-check.checked')].map(el => el.dataset.id);
}

// ── XÓA NHIỀU ──
function sellerBulkDelete() {
    const ids = getCheckedSellerIds();
    if (!ids.length) { alert('Vui lòng chọn người bán cần xóa'); return; }
    if (!confirm(`Bạn có chắc muốn xóa ${ids.length} người bán đã chọn?`)) return;
    const data = new FormData();
    data.append('action', 'bulk_delete');
    ids.forEach(id => data.append('ids[]', id));
    fetch('/MantaMarket/admin/index.php?page=sellers', { method: 'POST', body: data })
        .then(() => { location.hash = 'sellers'; location.reload(); });
}

// ── ẨN/HIỆN NHIỀU ──
function sellerBulkToggle(status) {
    const ids = getCheckedSellerIds();
    if (!ids.length) { alert('Vui lòng chọn người bán'); return; }
    if (!confirm(`${status == 1 ? 'Mở khóa' : 'Khóa'} ${ids.length} người bán đã chọn?`)) return;
    const data = new FormData();
    data.append('action', 'bulk_toggle');
    data.append('is_active', status);
    ids.forEach(id => data.append('ids[]', id));
    fetch('/MantaMarket/admin/index.php?page=sellers', { method: 'POST', body: data })
        .then(() => { location.hash = 'sellers'; location.reload(); });
}

// ── XÁC MINH NHIỀU ──
function sellerBulkVerify(status) {
    const ids = getCheckedSellerIds();
    if (!ids.length) { alert('Vui lòng chọn người bán'); return; }
    if (!confirm(`${status == 1 ? 'Xác minh' : 'Bỏ xác minh'} ${ids.length} người bán đã chọn?`)) return;
    const data = new FormData();
    data.append('action', 'bulk_verify');
    data.append('is_verified', status);
    ids.forEach(id => data.append('ids[]', id));
    fetch('/MantaMarket/admin/index.php?page=sellers', { method: 'POST', body: data })
        .then(() => { location.hash = 'sellers'; location.reload(); });
}

// ── ĐÓNG MODAL KHI CLICK NGOÀI ──
['sellerModal', 'sellerConfirmModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function (e) {
        if (e.target === this) this.style.display = 'none';
    });
});

function goToSellerPage(p) { sellerPage = p; renderSel(); }
document.addEventListener('DOMContentLoaded', () => { if (typeof renderSel === 'function') renderSel(); });
</script>