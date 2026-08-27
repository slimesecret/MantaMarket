<?php
$router   = new app_Libs_Router();
$user     = new app_Libs_UserIdentity();
$action   = $router->getPOST("action");
$id       = intval($router->getPOST("id") ?? $router->getGET("id"));
$products = new app_Models_Products();
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
// ── XÓA 1 SẢN PHẨM ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $products->buildQueryParams([
        "where"  => "id = :id",
        "params" => [":id" => $id]
    ])->delete();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'id' => $id]);
        exit();
    }
    header("Location: /MantaMarket/admin/index.php#products");
    exit();
}
// ── XÓA NHIỀU SẢN PHẨM ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    $deletedCount = 0;
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $products->buildQueryParams([
                "where"  => "id = :id",
                "params" => [":id" => $did]
            ])->delete();
            $deletedCount++;
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'count' => $deletedCount]);
        exit();
    }
    header("Location: /MantaMarket/admin/index.php#products");
    exit();
}
// ── THÊM SẢN PHẨM MỚI ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'insert') {
    $seller_id   = intval($router->getPOST("seller_id"));
    $name        = trim($router->getPOST("name"));
    $slug        = trim($router->getPOST("slug"));
    $description = trim($router->getPOST("description") ?? '');
    $base_price  = floatval(str_replace(',', '', $router->getPOST("base_price") ?? 0));
    $brand_id    = intval($router->getPOST("brand_id")) ?: null;
    $status      = $router->getPOST("status") ?? 'draft';
    $is_featured = intval($router->getPOST("is_featured") ?? 0);
    if (!in_array($status, ['draft', 'active', 'inactive', 'banned'])) $status = 'draft';
    if (!$seller_id || !$name || !$slug) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đủ tên, slug và seller.']);
            exit();
        }
        header("Location: /MantaMarket/admin/index.php#products");
        exit();
    }
    $checkSlug = $products->buildQueryParams([
        "where"  => "slug = :slug",
        "params" => [":slug" => $slug]
    ])->selectOne();
    if ($checkSlug) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Slug đã tồn tại trong hệ thống.']);
            exit();
        }
        header("Location: /MantaMarket/admin/index.php#products");
        exit();
    }
    $newId = $products->buildQueryParams([
        "field" => "(seller_id, brand_id, name, slug, description, base_price, status, is_featured)
                    VALUES (:seller_id, :brand_id, :name, :slug, :description, :base_price, :status, :is_featured)",
        "value" => [
            ":seller_id"   => $seller_id,
            ":brand_id"    => $brand_id,
            ":name"        => $name,
            ":slug"        => $slug,
            ":description" => $description,
            ":base_price"  => $base_price,
            ":status"      => $status,
            ":is_featured" => $is_featured,
        ]
    ])->insert();
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'new_id' => $newId]);
        exit();
    }
    header("Location: /MantaMarket/admin/index.php#products");
    exit();
}
// Action 'update' đã xóa — chức năng update chuyển sang ajax_edit.php (section=basic)
// ── LẤY DỮ LIỆU DANH SÁCH ──
$productsList = $products->buildQueryParams([])->select();
$totalAll    = count($productsList);
$totalActive = count(array_filter($productsList, fn($r) => $r['status'] === 'active'));
$totalInact  = count(array_filter($productsList, fn($r) => $r['status'] === 'inactive'));
$totalDraft  = count(array_filter($productsList, fn($r) => $r['status'] === 'draft'));
$productsJson = json_encode(array_map(fn($p) => [
    'id'           => $p['id'],
    'seller_id'    => $p['seller_id'],
    'brand_id'     => $p['brand_id'],
    'name'         => $p['name'],
    'slug'         => $p['slug'],
    'description'  => $p['description'] ?? '',
    'base_price'   => $p['base_price'],
    'status'       => $p['status'],
    'is_featured'  => $p['is_featured'],
    'view_count'   => $p['view_count'],
    'sold_count'   => $p['sold_count'],
    'avg_rating'   => $p['avg_rating'],
    'review_count' => $p['review_count'],
], $productsList), JSON_UNESCAPED_UNICODE);
$brands     = new app_Models_Brands();
$brandsList = $brands->buildQueryParams(["where" => "is_active = 1", "params" => []])->select();
$brandsJson = json_encode(array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name']], $brandsList), JSON_UNESCAPED_UNICODE);
$sellers     = new app_Models_Sellers();
$sellersList = $sellers->buildQueryParams([])->select();
$sellersJson = json_encode(array_map(fn($s) => [
    'id'   => $s['id'],
    'name' => $s['name'] ?? ($s['shop_name'] ?? 'Seller #' . $s['id']),
], $sellersList), JSON_UNESCAPED_UNICODE);
?>
<!-- ── PHẦN 1: DANH SÁCH SẢN PHẨM ── -->
<div class="page" id="page-products">
    <div class="page-header">
        <h1 class="page-title">Quản lý Sản phẩm</h1>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">

        <div class="stat-card">
            <div class="stat-label">Tổng số sản phẩm</div>
            <div class="stat-value"><?= $totalAll ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Đang hiển thị</div>
            <div class="stat-value"><?= $totalActive ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Đang ẩn</div>
            <div class="stat-value"><?= $totalInact ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Bản nháp</div>
            <div class="stat-value"><?= $totalDraft ?></div>
        </div>
    </div>
    </div>

    <div class="filter-bar">
        <select class="filter-select" id="statusFilter" onchange="renderProducts()">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hiển thị</option>
            <option value="inactive">Đang ẩn</option>
            <option value="draft">Bản nháp</option>
            <option value="banned">Bị khoá</option>
        </select>
        <select class="filter-select" id="brandFilter" onchange="renderProducts()">
            <option value="">Tất cả thương hiệu</option>
        </select>
        <input class="filter-search" type="text" placeholder="Tìm kiếm sản phẩm..." id="productSearch">
        <div style="display:flex;gap:6px;margin-left:auto">
            <button class="btn-action" onclick="renderProducts()"><i class="fas fa-sync"></i></button>
        </div>
                <div class="page-actions">
            <button class="btn-action" onclick="confirmBulkDelete()"><i class="fas fa-trash"></i></button>
            <button class="btn-action" onclick="renderProducts()"><i class="fas fa-sync"></i></button>
            <button class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Thêm sản phẩm</button>
        </div>
    </div>
    <div class="product-table-card">
        <div class="product-table-head">
            <div class="select-all-row">
                <div class="checkbox-custom" id="checkAll" onclick="toggleAll(this)"></div>
                <span style="font-size:13px;color:var(--muted)">Chọn tất cả</span>
            </div>
            <div class="pagination" id="topPagination"></div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>ID</th>
                        <th>Tên sản phẩm</th>
                        <th>Seller</th>
                        <th>Thương hiệu</th>
                        <th>Giá gốc</th>
                        <th>Đã bán</th>
                        <th>Lượt xem</th>
                        <th>Đánh giá</th>
                        <th>Trạng thái</th>
                        <th>Nổi bật</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="productTableBody"></tbody>
            </table>
        </div>
        <div class="table-footer">
            <div class="table-info" id="tableInfo"></div>
            <div class="pagination" id="bottomPagination"></div>
        </div>
    </div>
</div>
<!-- ── MODAL XEM / CHỈNH SỬA CHI TIẾT ── -->
<div id="viewModal" class="modal-bg">
    <div style="background:#fff;border-radius:16px;width:1400px;max-width:96vw;height:88vh;
                display:flex;flex-direction:column;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,0.15)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-shrink:0">
            <h3 style="font-size:20px;font-weight:800;color:var(--text);margin:0">Chi tiết sản phẩm</h3>
            <button onclick="document.getElementById('viewModal').style.display='none'"
                style="background:none;border:none;font-size:22px;color:var(--muted);cursor:pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="viewContent" style="flex:1;overflow-y:auto"></div>
    </div>
</div>
<!-- ── MODAL THÊM MỚI (chỉ còn insert, không còn edit) ── -->
<div class="modal-bg" id="addModal">
    <div class="modal-box" style="max-width:560px;max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
            <h2 style="font-size:20px;font-weight:800;color:var(--text)">Thêm sản phẩm mới</h2>
            <button onclick="closeAddModal()"
                style="background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="display:grid;gap:14px">
            <div>
                <label class="modal-label">Tên sản phẩm *</label>
                <input id="newName" class="modal-input" type="text"
                    placeholder="Nhập tên sản phẩm..." oninput="autoSlug()">
            </div>
            <div>
                <label class="modal-label">Slug *</label>
                <input id="newSlug" class="modal-input" type="text" placeholder="ten-san-pham">
            </div>
            <div>
                <label class="modal-label">Mô tả</label>
                <textarea id="newDesc" class="modal-input" rows="3" placeholder="Mô tả sản phẩm..."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label class="modal-label">Giá gốc (VNĐ)</label>
                    <input id="newPrice" class="modal-input" type="number" placeholder="0" min="0" step="1000">
                </div>
                <div>
                    <label class="modal-label">Nổi bật</label>
                    <select id="newFeatured" class="modal-input" style="background:#fff">
                        <option value="0">Không</option>
                        <option value="1">Có</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="modal-label">Seller *</label>
                <select id="newSellerId" class="modal-input" style="background:#fff">
                    <option value="">-- Chọn seller --</option>
                </select>
            </div>
            <div>
                <label class="modal-label">Thương hiệu</label>
                <select id="newBrandId" class="modal-input" style="background:#fff">
                    <option value="">-- Không có --</option>
                </select>
            </div>
            <div>
                <label class="modal-label">Trạng thái</label>
                <select id="newStatus" class="modal-input" style="background:#fff">
                    <option value="draft">Bản nháp</option>
                    <option value="active">Hiển thị</option>
                    <option value="inactive">Ẩn</option>
                    <option value="banned">Bị khoá</option>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:22px;justify-content:flex-end">
            <button onclick="closeAddModal()"
                style="padding:10px 20px;border:1.5px solid var(--border);border-radius:10px;
                       background:#fff;font-family:inherit;font-size:14px;color:var(--muted);cursor:pointer">
                Hủy
            </button>
            <button onclick="insertProduct()"
                style="padding:10px 24px;border:none;border-radius:10px;
                       background:linear-gradient(135deg,var(--purple1),var(--purple2));
                       color:#fff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer">
                Lưu
            </button>
        </div>
    </div>
</div>
<script>
    // ═══════════════════════ DATA ═══════════════════════
    let products = <?= $productsJson ?>;
    const brands = <?= $brandsJson ?>;
    const sellers = <?= $sellersJson ?>;
    const brandMap = {};
    const sellerMap = {};
    brands.forEach(b => brandMap[b.id] = b.name);
    sellers.forEach(s => sellerMap[s.id] = s.name);
    // ── Đổ dữ liệu vào các dropdown lúc load ──
    (function initSelects() {
        const selBrand = document.getElementById('newBrandId');
        const filterBrand = document.getElementById('brandFilter');
        const selSeller = document.getElementById('newSellerId');
        brands.forEach(b => {
            // Thêm vào form
            const o1 = new Option(b.name, b.id);
            selBrand.appendChild(o1);
            // Thêm vào bộ lọc
            const o2 = new Option(b.name, b.id);
            filterBrand.appendChild(o2);
        });
        sellers.forEach(s => selSeller.appendChild(new Option(s.name, s.id)));
    })();
    // ═══════════════════════ HẰNG SỐ ═══════════════════════
    const STATUS_LABEL = {
        active: {
            text: 'Hiển thị',
            cls: 'badge-active'
        },
        inactive: {
            text: 'Đang ẩn',
            cls: 'badge-inactive'
        },
        draft: {
            text: 'Bản nháp',
            cls: 'badge-draft'
        },
        banned: {
            text: 'Bị khoá',
            cls: 'badge-banned'
        },
    };
    const PAGE_SIZE = 8;
    let currentPage = 1;
    // ═══════════════════════ UTILS ═══════════════════════
    const formatPrice = v => Number(v).toLocaleString('vi-VN') + 'đ';

    function autoSlug() {
        const slug = document.getElementById('newName').value.toLowerCase()
            .replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, 'a')
            .replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, 'e')
            .replace(/ì|í|ị|ỉ|ĩ/g, 'i')
            .replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, 'o')
            .replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, 'u')
            .replace(/ỳ|ý|ỵ|ỷ|ỹ/g, 'y')
            .replace(/đ/g, 'd')
            .replace(/[^a-z0-9\s-]/g, '')
            .trim().replace(/\s+/g, '-');
        document.getElementById('newSlug').value = slug;
    }
    // ═══════════════════════ LỌC & RENDER BẢNG ═══════════════════════
    function getFiltered() {
        const q = (document.getElementById('productSearch')?.value || '').toLowerCase();
        const st = document.getElementById('statusFilter')?.value || '';
        const brand = document.getElementById('brandFilter')?.value || '';
        return products.filter(p =>
            (!st || p.status == st) &&
            (!brand || p.brand_id == brand) &&
            (p.name.toLowerCase().includes(q) || String(p.id).includes(q))
        );
    }

    function renderProducts() {
        const filtered = getFiltered();
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        currentPage = Math.min(currentPage, pages);
        const slice = filtered.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
        document.getElementById('tableInfo').textContent =
            `${(currentPage - 1) * PAGE_SIZE + 1}–${Math.min(currentPage * PAGE_SIZE, total)} / ${total} sản phẩm`;
        document.getElementById('productTableBody').innerHTML = slice.map(p => {
            // Tìm vị trí gốc trong mảng để truyền vào các handler
            const gi = products.indexOf(p);
            const stl = STATUS_LABEL[p.status] || {
                text: p.status,
                cls: ''
            };
            return `<tr>
            <td><div class="checkbox-custom row-check" onclick="toggleRow(this)"></div></td>
            <td style="color:var(--muted);font-size:13px">#${p.id}</td>
            <td>
                <div class="prod-name">${p.name}</div>
                <div class="prod-sub">${p.slug}</div>
            </td>
            <td style="font-size:13px;color:var(--muted)">${sellerMap[p.seller_id] ?? '#' + p.seller_id}</td>
            <td style="font-size:13px;color:var(--muted)">${brandMap[p.brand_id]   ?? '—'}</td>
            <td style="font-weight:600">${formatPrice(p.base_price)}</td>
            <td>${Number(p.sold_count).toLocaleString()}</td>
            <td>${Number(p.view_count).toLocaleString()}</td>
            <td>${p.avg_rating > 0 ? '★ ' + Number(p.avg_rating).toFixed(1) : '—'}</td>
            <td><span class="badge ${stl.cls}">${stl.text}</span></td>
            <td>${p.is_featured ? '<i class="fas fa-star" style="color:#f59e0b"></i>' : '—'}</td>
            <td><div class="actions-cell">
                <!-- Nút Xem mở viewModal (tích hợp chỉnh sửa bên trong) -->
                <button class="btn-view" onclick="viewProduct(${gi})">
                    <i class="fas fa-eye"></i> Xem / Sửa
                </button>
                <button class="btn-del" onclick="deleteProduct(${gi})">
                    <i class="fas fa-trash"></i> Xóa
                </button>
            </div></td>
        </tr>`;
        }).join('');
        renderPagination('topPagination', pages);
        renderPagination('bottomPagination', pages);
    }

    function renderPagination(id, pages) {
        let html = '';
        for (let i = 1; i <= Math.min(pages, 5); i++)
            html += `<div class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</div>`;
        if (pages > 5)
            html += `<div class="page-btn" onclick="goPage(${Math.min(currentPage + 1, pages)})">
                    <i class="fas fa-chevron-right" style="font-size:10px"></i></div>`;
        document.getElementById(id).innerHTML = html;
    }

    function goPage(p) {
        currentPage = p;
        renderProducts();
    }
    // ═══════════════════════ XÓA ═══════════════════════
    function deleteProduct(i) {
        const p = products[i];
        if (!confirm(`Xóa sản phẩm "${p.name}"?`)) return;
        const params = new URLSearchParams({
            ajax: '1',
            action: 'delete',
            id: p.id
        });
        fetch('/MantaMarket/admin/index.php?page=products', {
                method: 'POST',
                body: params
            })
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') return alert('Xóa thất bại!');
                products.splice(i, 1);
                renderProducts();
            })
            .catch(() => alert('Lỗi mạng'));
    }

    function confirmBulkDelete() {
        const checked = [...document.querySelectorAll('.row-check.checked')];
        if (!checked.length) return alert('Vui lòng chọn sản phẩm cần xóa');
        if (!confirm(`Xóa ${checked.length} sản phẩm đã chọn?`)) return;
        const rows = [...document.querySelectorAll('#productTableBody tr')];
        const slice = getFiltered().slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
        const ids = [];
        checked.forEach(cb => {
            const idx = rows.indexOf(cb.closest('tr'));
            if (idx >= 0 && slice[idx]) ids.push(slice[idx].id);
        });
        const params = new URLSearchParams({
            ajax: '1',
            action: 'bulk_delete'
        });
        ids.forEach(id => params.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=products', {
                method: 'POST',
                body: params
            })
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') return alert('Xóa thất bại!');
                products = products.filter(p => !ids.includes(Number(p.id)));
                const checkAll = document.getElementById('checkAll');
                checkAll.classList.remove('checked');
                checkAll.innerHTML = '';
                renderProducts();
            })
            .catch(() => alert('Lỗi mạng'));
    }
    // ═══════════════════════ THÊM MỚI ═══════════════════════
    function openAddModal() {
        ['newName', 'newSlug', 'newDesc', 'newPrice'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('newSellerId').value = '';
        document.getElementById('newBrandId').value = '';
        document.getElementById('newStatus').value = 'draft';
        document.getElementById('newFeatured').value = '0';
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
    }

    function insertProduct() {
        const name = document.getElementById('newName').value.trim();
        const slug = document.getElementById('newSlug').value.trim();
        const seller_id = document.getElementById('newSellerId').value;
        if (!name) return alert('Vui lòng nhập tên sản phẩm');
        if (!slug) return alert('Vui lòng nhập slug');
        if (!seller_id) return alert('Vui lòng chọn seller');
        const params = new URLSearchParams({
            ajax: '1',
            action: 'insert',
            seller_id,
            name,
            slug,
            description: document.getElementById('newDesc').value,
            base_price: document.getElementById('newPrice').value || '0',
            brand_id: document.getElementById('newBrandId').value || '',
            status: document.getElementById('newStatus').value,
            is_featured: document.getElementById('newFeatured').value,
        });
        fetch('/MantaMarket/admin/index.php?page=products', {
                method: 'POST',
                body: params
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'error') return alert(res.message || 'Có lỗi xảy ra');
                // Chèn sản phẩm mới lên đầu mảng
                products.unshift({
                    id: res.new_id,
                    seller_id: Number(seller_id),
                    brand_id: document.getElementById('newBrandId').value || null,
                    name,
                    slug,
                    description: document.getElementById('newDesc').value,
                    base_price: Number(document.getElementById('newPrice').value || 0),
                    status: document.getElementById('newStatus').value,
                    is_featured: Number(document.getElementById('newFeatured').value),
                    view_count: 0,
                    sold_count: 0,
                    avg_rating: 0,
                    review_count: 0,
                });
                closeAddModal();
                currentPage = 1;
                renderProducts();
            })
            .catch(() => alert('Lỗi mạng'));
    }
    // ═══════════════════════ XEM / CHỈNH SỬA (viewProduct - giữ nguyên) ═══════════════════════
    function viewProduct(productArrIdx) {
        const p = products[productArrIdx];
        document.getElementById('viewContent').innerHTML = `
        <div style="text-align:center;padding:48px;color:#888">
            <i class="fas fa-spinner fa-spin" style="font-size:22px"></i>
            <div style="margin-top:10px;font-size:13px">Đang tải...</div>
        </div>`;
        document.getElementById('viewModal').style.display = 'flex';
        fetch(`/MantaMarket/admin/products/ajax_view.php?id=${p.id}`)
            .then(r => r.json())
            .then(data => {
                const variants = data.variants || [];
                const images = data.images || [];
                const attrs = data.attributes || [];
                const tags = data.tags || [];
                const fmtVND = n => Number(n).toLocaleString('vi-VN') + 'đ';

                function stockLabel(stock, alert) {
                    if (stock <= 0) return `<span style="color:#ee4d2d">Hết hàng</span>`;
                    if (stock <= alert) return `<span style="color:#e5a000">Còn ${stock} sp</span>`;
                    return `<span style="color:#27a100">${stock.toLocaleString('vi-VN')} có sẵn</span>`;
                }
                const colorImgMap = {};
                images.forEach(img => {
                    const c = img.variant_color || '';
                    if (c && !colorImgMap[c]) colorImgMap[c] = img.image_url;
                });
                const colorsList = [...new Set(variants.map(v => v.color).filter(Boolean))];
                const sizesList = [...new Set(variants.map(v => v.size).filter(Boolean))];
                const primaryImg = images.find(img => img.is_primary == 1) || images[0] || null;
                let mainImgUrl = primaryImg?.image_url || '';
                const defColor = colorsList[0] || '';
                const defSize = sizesList[0] || '';
                if (defColor && colorImgMap[defColor]) mainImgUrl = colorImgMap[defColor];
                const vMap = {};
                variants.forEach(v => {
                    vMap[(v.color || '') + '|' + (v.size || '')] = v;
                });
                const defV = vMap[defColor + '|' + defSize] ||
                    variants.find(v => v.is_active && v.stock_quantity > 0) ||
                    variants[0] || {};
                const defPrice = parseFloat(defV.price || p.base_price || 0);
                const defCompare = parseFloat(defV.compare_price || 0);
                const defStock = parseInt(defV.stock_quantity || 0);
                const defAlert = parseInt(defV.low_stock_alert || 5);
                const pct = defCompare > defPrice ? Math.round((1 - defPrice / defCompare) * 100) : 0;
                const BADGE = v => {
                    const st = parseInt(v.stock_quantity),
                        al = parseInt(v.low_stock_alert || 5);
                    if (st <= 0) return `<span style="padding:2px 8px;border-radius:3px;font-size:11px;background:#fff0ee;color:#ee4d2d;border:1px solid #ffd4cc">Hết hàng</span>`;
                    if (st <= al) return `<span style="padding:2px 8px;border-radius:3px;font-size:11px;background:#fffbe6;color:#e5a000;border:1px solid #ffe58f">Sắp hết</span>`;
                    if (!v.is_active) return `<span style="padding:2px 8px;border-radius:3px;font-size:11px;background:#f5f5f5;color:#aaa;border:1px solid #e0e0e0">Ngừng bán</span>`;
                    return `<span style="padding:2px 8px;border-radius:3px;font-size:11px;background:#f0fff4;color:#27a100;border:1px solid #b7ebc0">Đang bán</span>`;
                };
                const colorChipsHtml = colorsList.map((c, ci) => {
                    const thumb = colorImgMap[c] ?
                        `<img src="${colorImgMap[c]}" style="width:28px;height:28px;object-fit:contain;border-radius:2px;border:1px solid #f0f0f0;flex-shrink:0" onerror="this.style.display='none'">` :
                        `<div style="width:28px;height:28px;border-radius:2px;background:#eee;flex-shrink:0"></div>`;
                    const act = ci === 0;
                    return `<div class="vp-color-chip ${act ? 'active' : ''}" data-color="${c}"
                    onclick="vpSelectColor('${c.replace(/'/g, "\\'")}',this)"
                    style="display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;
                           border:${act ? '1.5px solid #ee4d2d' : '1px solid #e0e0e0'};border-radius:2px;
                           cursor:pointer;font-size:13px;color:${act ? '#ee4d2d' : '#333'};
                           background:#fff;position:relative;min-width:80px">
                    ${thumb}<span>${c}</span>
                    ${act ? `<span style="position:absolute;bottom:-1px;right:-1px;width:14px;height:14px;
                                  background:#ee4d2d;color:#fff;font-size:9px;display:flex;
                                  align-items:center;justify-content:center;border-radius:2px 0 2px 0">✓</span>` : ''}
                </div>`;
                }).join('');
                const sizeChipsHtml = sizesList.map((sz, si) => {
                    const ok = variants.some(v => v.size === sz && v.is_active && v.stock_quantity > 0);
                    const act = si === 0;
                    return `<div class="vp-size-chip ${act ? 'active' : ''} ${!ok ? 'out' : ''}" data-size="${sz}"
                    onclick="vpSelectSize('${sz.replace(/'/g, "\\'")}',this)"
                    style="padding:5px 14px;border-radius:2px;font-size:13px;
                           border:${act ? '1.5px solid #ee4d2d' : '1px solid #e0e0e0'};
                           color:${act ? '#ee4d2d' : '#333'};background:#fff;
                           cursor:${!ok ? 'not-allowed' : 'pointer'};
                           opacity:${!ok ? '.4' : '1'};
                           text-decoration:${!ok ? 'line-through' : 'none'}">${sz}</div>`;
                }).join('');
                // ── Shared style strings ──
                const ROW = `display:flex;align-items:baseline;gap:0;padding:9px 0;border-bottom:1px solid #f7f7f7`;
                const LBL = `min-width:130px;font-size:12px;font-weight:600;color:#999;flex-shrink:0`;
                const VAL = `flex:1;font-size:13px;color:#1a1a1a;word-break:break-all`;
                const EDIT = `margin-left:8px;flex-shrink:0;background:none;border:none;cursor:pointer;color:#c4b5fd;font-size:12px;padding:2px 4px;border-radius:4px`;
                const EHOV = `onmouseover="this.style.color='#7c3aed'" onmouseout="this.style.color='#c4b5fd'"`;

                function field(id, label, displayValue, editScript, optionsData, rawValue) {
                    const dv = rawValue !== undefined ? rawValue : displayValue;
                    return `
    <div style="${ROW}">
        <span style="${LBL}">${label}</span>
        <span id="${id}" style="${VAL}"
              data-value="${String(dv).replace(/"/g, '&quot;')}"
              data-opts="${optionsData || ''}">${displayValue}</span>
        <button style="${EDIT}" ${EHOV} onclick="${editScript}" title="Chỉnh sửa">
            <i class="fas fa-pencil-alt"></i>
        </button>
    </div>`;
                }
                const brandOpts = brands.map(b => `<option value="${b.id}" ${p.brand_id  == b.id  ? 'selected' : ''}>${b.name}</option>`).join('');
                const sellerOpts = sellers.map(s => `<option value="${s.id}" ${p.seller_id == s.id  ? 'selected' : ''}>${s.name}</option>`).join('');
                const statusOpts = [
                        ['draft', 'Bản nháp'],
                        ['active', 'Hiển thị'],
                        ['inactive', 'Ẩn'],
                        ['banned', 'Bị khoá']
                    ]
                    .map(([v, l]) => `<option value="${v}" ${p.status === v ? 'selected' : ''}>${l}</option>`).join('');
                const featuredOpts = `<option value="0" ${!p.is_featured ? 'selected':''}>Không</option>
                                  <option value="1" ${ p.is_featured ? 'selected':''}>Có</option>`;
                const brandOptsAll = `<option value="">-- Không có --</option>${brandOpts}`;
                const infoHtml = `
            <div id="info-section" style="padding:4px 0">
                ${field('info-name',     'Tên sản phẩm', p.name,                                          `vpEditField('info-name','text')`)}
                ${field('info-slug',     'Slug',          p.slug,                                          `vpEditField('info-slug','text')`)}
                ${field('info-price',    'Giá gốc (VNĐ)', Number(p.base_price).toLocaleString('vi-VN'),   `vpEditField('info-price','number')`)}
                ${field('info-status',   'Trạng thái',    p.status,                                        `vpEditSelect('info-status')`,   statusOpts.replace(/"/g,'&quot;'))}
                ${field('info-featured', 'Nổi bật',       p.is_featured ? 'Có' : 'Không',                 `vpEditSelect('info-featured')`, featuredOpts.replace(/"/g,'&quot;'))}
${field('info-seller', 'Người bán',   sellerMap[p.seller_id] ?? '#'+p.seller_id, `vpEditSelect('info-seller')`, sellerOpts.replace(/"/g,'&quot;'), p.seller_id)}
${field('info-brand',  'Thương hiệu', brandMap[p.brand_id]   ?? '—',             `vpEditSelect('info-brand')`,  brandOptsAll.replace(/"/g,'&quot;'), p.brand_id ?? '')}
                <div style="padding:9px 0 4px">
                    <span style="${LBL};padding-top:4px;display:inline-block">Mô tả</span>
                    <div style="margin-top:6px">
                        <div id="info-desc"
                             style="font-size:13px;color:#444;line-height:1.75;white-space:pre-wrap;
                                    background:#fafafa;border-radius:6px;padding:10px 12px;min-height:50px"
                             data-value="${(p.description || '').replace(/"/g,'&quot;')}">
                            ${p.description || '<span style="color:#ccc">Chưa có mô tả</span>'}
                        </div>
                        <button onclick="vpEditField('info-desc','textarea')"
                            style="margin-top:5px;padding:4px 12px;border:1px solid #e0e0e0;
                                   border-radius:6px;background:#fff;font-size:12px;color:#7c3aed;cursor:pointer">
                            <i class="fas fa-pencil-alt"></i> Sửa mô tả
                        </button>
                    </div>
                </div>
                <div style="padding:12px 0 4px;display:flex;align-items:center;gap:10px">
                    <button onclick="vpSaveBasic(${p.id},${productArrIdx})"
                        style="padding:8px 22px;border:none;border-radius:8px;
                               background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;
                               font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
                        <i class="fas fa-save" style="margin-right:5px"></i>Lưu thông tin
                    </button>
                    <span id="info-msg" style="font-size:12px"></span>
                </div>
            </div>`;
                const variantsEditHtml = `
${variants.length ? `
<div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:12px" id="ev-table">
        <thead><tr style="background:#f5f3ff">
            ${['SKU','Màu','Size','Chất liệu','Giá bán','Giá so sánh','Tồn kho','Cảnh báo SH','Hiển thị','Trạng thái']
                .map(h => `<th style="padding:8px 10px;text-align:left;font-weight:600;font-size:11px;color:#7c3aed;border:1px solid #ede9fe;white-space:nowrap">${h}</th>`).join('')}
        </tr></thead>
        <tbody>
        ${variants.map(v => `
            <tr data-vid="${v.id}" onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background=''">
                ${['sku','color','size','material'].map(f => `
                <td style="padding:6px 8px;border:1px solid #f0f0f0">
                    <input class="ev-${f}" value="${(v[f]||'').replace(/"/g,'&quot;')}"
                        style="width:${f==='material'?'110':'80'}px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;font-family:inherit"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
                </td>`).join('')}
                ${['price','compare_price'].map(f => `
                <td style="padding:6px 8px;border:1px solid #f0f0f0">
                    <input class="ev-${f==='price'?'price':'compare'}" type="number" min="0" step="1000" value="${v[f]||0}"
                        style="width:100px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;font-family:inherit"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
                </td>`).join('')}
                <td style="padding:6px 8px;border:1px solid #f0f0f0">
                    <input class="ev-stock" type="number" min="0" value="${v.stock_quantity||0}"
                        style="width:65px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;font-family:inherit"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
                </td>
                <td style="padding:6px 8px;border:1px solid #f0f0f0">
                    <input class="ev-alert" type="number" min="0" value="${v.low_stock_alert||5}"
                        style="width:55px;padding:5px 7px;border:1px solid #e0e0e0;border-radius:4px;font-size:12px;font-family:inherit"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
                </td>
                <td style="padding:6px 8px;border:1px solid #f0f0f0;text-align:center">
                    <select class="ev-active" style="padding:4px 6px;border:1px solid #e0e0e0;border-radius:4px;font-size:11px;background:#fff">
                        <option value="1" ${v.is_active  ? 'selected':''}>Bật</option>
                        <option value="0" ${!v.is_active ? 'selected':''}>Tắt</option>
                    </select>
                </td>
                <td style="padding:6px 8px;border:1px solid #f0f0f0">${BADGE(v)}</td>
            </tr>`).join('')}
        </tbody>
    </table>
</div>
<div style="display:flex;align-items:center;gap:10px;padding:12px 0 4px">
    <button onclick="vpSaveVariants(${p.id})"
        style="padding:8px 22px;border:none;border-radius:8px;
               background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;
               font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
        <i class="fas fa-save" style="margin-right:5px"></i>Lưu biến thể
    </button>
    <span id="ev-msg" style="font-size:12px"></span>
</div>
<hr style="border:none;border-top:1px solid #f0f0f0;margin:16px 0">` : `
<div style="padding:1rem 0 0.5rem;text-align:center;color:#bbb;font-size:13px">Chưa có biến thể nào.</div>`}
<!-- ── FORM THÊM BIẾN THỂ MỚI ── -->
<div style="background:#faf5ff;border:1.5px dashed #c4b5fd;border-radius:10px;padding:16px;margin-top:4px">
    <div style="font-size:13px;font-weight:700;color:#7c3aed;margin-bottom:12px">
        <i class="fas fa-plus-circle" style="margin-right:6px"></i>Thêm biến thể mới
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:10px">
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">SKU</div>
            <input id="nv-sku" placeholder="SKU-001"
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Màu sắc</div>
            <input id="nv-color" placeholder="Đen, Trắng..."
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Size</div>
            <input id="nv-size" placeholder="S, M, L, 256GB..."
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Chất liệu</div>
            <input id="nv-material" placeholder="Cotton, Titan..."
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Giá bán (VNĐ) *</div>
            <input id="nv-price" type="number" min="0" step="1000" placeholder="0"
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Giá so sánh</div>
            <input id="nv-compare" type="number" min="0" step="1000" placeholder="0"
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Tồn kho</div>
            <input id="nv-stock" type="number" min="0" placeholder="0"
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
        <div>
            <div style="font-size:11px;color:#999;margin-bottom:4px">Cảnh báo SH</div>
            <input id="nv-alert" type="number" min="0" placeholder="5"
                style="width:100%;padding:7px 9px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
        <button onclick="vpAddVariant(${p.id}, ${productArrIdx})"
            style="padding:9px 24px;border:none;border-radius:8px;
                   background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;
                   font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
            <i class="fas fa-plus" style="margin-right:5px"></i>Thêm biến thể
        </button>
        <span id="nv-msg" style="font-size:12px"></span>
    </div>
</div>`;

                function attrRow(name = '', value = '') {
                    return `
                <div class="ea-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center;margin-bottom:7px">
                    <input class="ea-name" placeholder="Tên thuộc tính" value="${name.replace(/"/g,'&quot;')}"
                        style="padding:7px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px;font-family:inherit"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
                    <input class="ea-value" placeholder="Giá trị" value="${value.replace(/"/g,'&quot;')}"
                        style="padding:7px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px;font-family:inherit"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
                    <button onclick="this.closest('.ea-row').remove()"
                        style="padding:7px 11px;border:1px solid #fdd;border-radius:6px;background:#fff0f0;color:#ee4d2d;cursor:pointer;font-size:13px">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
                }
                const attrsEditHtml = `
            <div id="ea-rows">${attrs.map(a => attrRow(a.attr_name, a.attr_value)).join('')}</div>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 0 4px;flex-wrap:wrap">
                <button onclick="vpAddAttrRow()"
                    style="padding:7px 16px;border:1.5px dashed #c4b5fd;border-radius:8px;
                           background:#faf5ff;color:#7c3aed;font-size:13px;cursor:pointer;font-family:inherit">
                    <i class="fas fa-plus" style="margin-right:4px"></i>Thêm thuộc tính
                </button>
                <button onclick="vpSaveAttrs(${p.id})"
                    style="padding:8px 22px;border:none;border-radius:8px;
                           background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;
                           font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
                    <i class="fas fa-save" style="margin-right:5px"></i>Lưu thông số
                </button>
                <span id="ea-msg" style="font-size:12px"></span>
            </div>`;
                const tagsEditHtml = `
            <div id="et-tags" style="display:flex;flex-wrap:wrap;align-content:flex-start;
                 min-height:50px;padding:8px;border:1px solid #f0f0f0;border-radius:8px;
                 background:#fafafa;margin-bottom:10px">
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input id="et-input" placeholder="Nhập tag → Enter"
                    style="flex:1;min-width:150px;padding:8px 11px;border:1px solid #e0e0e0;
                           border-radius:6px;font-size:13px;font-family:inherit"
                    onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'"
                    onkeydown="if(event.key==='Enter'){event.preventDefault();vpAddTag()}">
                <button onclick="vpAddTag()"
                    style="padding:8px 14px;border:1.5px dashed #fbb;border-radius:8px;
                           background:#fff5f5;color:#ee4d2d;font-size:13px;cursor:pointer;font-family:inherit">
                    <i class="fas fa-plus"></i>
                </button>
                <button onclick="vpSaveTags(${p.id})"
                    style="padding:8px 22px;border:none;border-radius:8px;
                           background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;
                           font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
                    <i class="fas fa-save" style="margin-right:5px"></i>Lưu tags
                </button>
                <span id="et-msg" style="font-size:12px"></span>
            </div>`;










                const imagesEditHtml = images.length ? `
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px" id="img-grid">
        ${images.map(img => `
        <div data-img-id="${img.id}"
             style="border:2px solid ${img.is_primary == 1 ? '#ee4d2d' : '#e0e0e0'};
                    border-radius:8px;overflow:hidden;background:#fff;
                    display:flex;flex-direction:column;transition:border-color .2s">
            <div style="aspect-ratio:1;overflow:hidden;background:#fafafa;display:flex;align-items:center;justify-content:center">
                <img src="${img.image_url}" alt="${img.alt_text||''}"
                     style="width:100%;height:100%;object-fit:contain"
                     onerror="this.parentElement.innerHTML='<span style=color:#ddd;font-size:11px>No img</span>'">
            </div>
            <div style="padding:5px 7px;font-size:11px;color:#999;flex:1;line-height:1.4">
                ${img.variant_color ? `<div style="color:#7c3aed">● ${img.variant_color}</div>` : ''}
                ${img.is_primary == 1 ? `<div style="color:#ee4d2d;font-weight:700" data-primary-label>★ Ảnh chính</div>` : ''}
            </div>
            <div style="padding:0 6px 7px;display:flex;gap:4px">
                ${img.is_primary != 1 ? `
                <button onclick="vpSetPrimary(${p.id},${img.id})" title="Đặt làm ảnh chính"
                    style="flex:1;padding:4px 0;border:1px solid #e0e0e0;border-radius:5px;
                           font-size:11px;cursor:pointer;background:#fff;color:#7c3aed"
                    onmouseover="this.style.background='#faf5ff'"
                    onmouseout="this.style.background='#fff'">
                    <i class="fas fa-star"></i>
                </button>` : '<div style="flex:1"></div>'}
                <button onclick="vpDeleteImage(${p.id},${img.id},this)" title="Xóa ảnh"
                    style="flex:1;padding:4px 0;border:1px solid #fdd;border-radius:5px;
                           font-size:11px;cursor:pointer;background:#fff;color:#ee4d2d"
                    onmouseover="this.style.background='#fff0f0'"
                    onmouseout="this.style.background='#fff'">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`).join('')}
    </div>` :
                    `<div id="img-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px"></div>
     <div style="padding:1.5rem;text-align:center;color:#bbb;font-size:13px" id="img-empty-msg">Chưa có hình ảnh.</div>`;

                // Nối thêm form upload (dùng ngoặc + để ghép chuỗi)
                const imagesEditHtmlFull = imagesEditHtml + `
    <!-- ── FORM UPLOAD ẢNH MỚI ── -->
    <div style="margin-top:14px;background:#f0f9ff;border:1.5px dashed #7dd3fc;
                border-radius:10px;padding:16px" id="img-upload-box">
        <div style="font-size:13px;font-weight:700;color:#0369a1;margin-bottom:10px">
            <i class="fas fa-cloud-upload-alt" style="margin-right:6px"></i>
            Thêm ảnh mới
            <span id="upload-color-label"
                  style="margin-left:8px;padding:2px 10px;background:#fff;
                         border:1px solid #7dd3fc;border-radius:20px;font-size:12px;
                         color:#0284c7;font-weight:400">
                ${defColor ? `● ${defColor}` : 'Không gắn màu'}
            </span>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
                <input type="file" id="img-file-input" accept="image/*" multiple
                    style="display:block;width:100%;padding:8px;border:1px solid #bae6fd;
                           border-radius:6px;font-size:13px;background:#fff;cursor:pointer;
                           box-sizing:border-box">
                <div style="font-size:11px;color:#94a3b8;margin-top:4px">
                    JPG, PNG, WEBP — tối đa 5MB/ảnh
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="checkbox" id="img-is-primary" style="width:14px;height:14px">
                    Đặt làm ảnh chính
                </label>
                <button onclick="vpUploadImages(${p.id}, ${productArrIdx})"
                    style="padding:9px 20px;border:none;border-radius:8px;
                           background:linear-gradient(135deg,#0284c7,#0ea5e9);
                           color:#fff;font-size:13px;font-weight:600;
                           cursor:pointer;font-family:inherit;white-space:nowrap">
                    <i class="fas fa-upload" style="margin-right:5px"></i>Upload
                </button>
            </div>
        </div>
        <div id="img-upload-progress" style="margin-top:8px;font-size:12px"></div>
    </div>`;
                document.getElementById('viewContent').innerHTML = `
            <style>
                .vp-tabs { display:flex;border-bottom:2px solid #f0f0f0;overflow-x:auto;gap:0;margin-bottom:0 }
                .vp-tab  { padding:10px 16px;font-size:13px;cursor:pointer;border-bottom:2px solid transparent;
                           margin-bottom:-2px;color:#666;white-space:nowrap;user-select:none;flex-shrink:0 }
                .vp-tab:hover  { color:#333 }
                .vp-tab.active { font-weight:600;color:#1a1a1a;border-bottom-color:#1a1a1a }
                .vp-panel        { display:none }
                .vp-panel.active { display:block }
            </style>
            <div style="display:grid;grid-template-columns:280px 1fr;gap:14px;margin-bottom:16px">
                <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;padding:12px">
                    <div id="vpMainImgWrap"
                         style="border-radius:6px;overflow:hidden;aspect-ratio:1;background:#fafafa;
                                display:flex;align-items:center;justify-content:center;border:1px solid #f0f0f0">
                        ${mainImgUrl
                            ? `<img id="vpMainImg" src="${mainImgUrl}" alt="${p.name}" style="width:100%;height:100%;object-fit:contain">`
                            : `<span style="color:#ccc;font-size:12px">Không có ảnh</span>`}
                    </div>
                </div>
                <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;padding:16px 18px">
                    <div style="font-size:16px;font-weight:600;color:#1a1a1a;line-height:1.4;margin-bottom:10px">${p.name}</div>
                    <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:12px;flex-wrap:wrap">
                        <span id="vpPriceMain" style="font-size:24px;color:#ee4d2d">${fmtVND(defPrice)}</span>
                        <span id="vpPriceCmp"  style="font-size:14px;color:#bbb;text-decoration:line-through;display:${defCompare > defPrice ? 'inline' : 'none'}">${defCompare > defPrice ? fmtVND(defCompare) : ''}</span>
                        <span id="vpPricePct"  style="font-size:12px;background:#ee4d2d;color:#fff;padding:2px 6px;border-radius:2px;display:${pct > 0 ? 'inline' : 'none'}">${pct > 0 ? '-' + pct + '%' : ''}</span>
                    </div>
                    ${colorsList.length ? `
                    <div style="margin-bottom:10px">
                        <div style="font-size:12px;color:#999;margin-bottom:6px">Màu sắc</div>
                        <div id="vpColorChips" style="display:flex;flex-wrap:wrap;gap:6px">${colorChipsHtml}</div>
                    </div>` : ''}
                    ${sizesList.length ? `
                    <div style="margin-bottom:10px">
                        <div style="font-size:12px;color:#999;margin-bottom:6px">Size / Dung lượng</div>
                        <div id="vpSizeChips" style="display:flex;flex-wrap:wrap;gap:6px">${sizeChipsHtml}</div>
                    </div>` : ''}
                    <div style="font-size:13px;color:#999;display:flex;flex-direction:column;gap:5px">
                        <div>Tồn kho: <span id="vpStockLabel">${stockLabel(defStock, defAlert)}</span></div>
                        ${brandMap[p.brand_id]   ? `<div>Thương hiệu: <strong style="color:#333">${brandMap[p.brand_id]}</strong></div>`   : ''}
                        ${sellerMap[p.seller_id] ? `<div>Người bán: <strong style="color:#333">${sellerMap[p.seller_id]}</strong></div>` : ''}
                        ${p.avg_rating > 0 ? `<div>Đánh giá: <strong style="color:#ee4d2d">★ ${Number(p.avg_rating).toFixed(1)}</strong> (${p.review_count})</div>` : ''}
                        <div>Đã bán: <strong style="color:#333">${Number(p.sold_count).toLocaleString()}</strong></div>
                    </div>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #f0f0f0;border-radius:8px;overflow:hidden">
                <div class="vp-tabs">
                    <div class="vp-tab active" onclick="vpSwitchTab('edit-info',this)">
                        <i class="fas fa-info-circle" style="margin-right:5px;color:#7c3aed"></i>Thông tin cơ bản
                    </div>
                    <div class="vp-tab" onclick="vpSwitchTab('edit-variants',this)">
                        <i class="fas fa-boxes" style="margin-right:5px;color:#7c3aed"></i>Biến thể (${variants.length})
                    </div>
                    <div class="vp-tab" onclick="vpSwitchTab('edit-attrs',this)">
                        <i class="fas fa-list" style="margin-right:5px;color:#7c3aed"></i>Thông số (${attrs.length})
                    </div>
                    <div class="vp-tab" onclick="vpSwitchTab('edit-tags',this)">
                        <i class="fas fa-tags" style="margin-right:5px;color:#ee4d2d"></i>Tags (${tags.length})
                    </div>
                    <div class="vp-tab" onclick="vpSwitchTab('edit-images',this)">
                        <i class="fas fa-images" style="margin-right:5px;color:#0ea5e9"></i>Hình ảnh (${images.length})
                    </div>
                </div>
                <div style="padding:18px">
                    <div class="vp-panel active" id="vp-edit-info">${infoHtml}</div>
                    <div class="vp-panel"        id="vp-edit-variants">${variantsEditHtml}</div>
                    <div class="vp-panel"        id="vp-edit-attrs">${attrsEditHtml}</div>
                    <div class="vp-panel"        id="vp-edit-tags">${tagsEditHtml}</div>
                    <div class="vp-panel"        id="vp-edit-images">${imagesEditHtmlFull}</div>
                </div>
            </div>`;
                // ĐÚNG CHỖ: sau innerHTML, trước window._vpVMap
                const etTagsContainer = document.getElementById('et-tags');
                if (etTagsContainer) {
                    tags.forEach(t => etTagsContainer.appendChild(tagChip(t.tag)));
                }
                window._vpVMap = vMap;
                window._vpColorImgMap = colorImgMap;
                window._vpActiveColor = defColor;
                window._vpActiveSize = defSize;
                window._vpFmtVND = fmtVND;
                window._vpStockLabel = stockLabel;
                window._vpProductIdx = productArrIdx;
            })
            .catch(() => {
                document.getElementById('viewContent').innerHTML =
                    `<div style="text-align:center;padding:48px;color:#ef4444">
                    <i class="fas fa-exclamation-triangle"></i> Lỗi khi tải dữ liệu
                </div>`;
            });
    }

    // ════════ FIX 1: tagChip — encode an toàn, nhất quán ════════
    function tagChip(text) {
        const span = document.createElement('span');
        span.className = 'et-tag';
        span.dataset.tag = text; // lưu raw

        // Encode chỉ để hiển thị text node — dùng createTextNode thay vì innerHTML
        const textNode = document.createTextNode(text);
        const closeBtn = document.createElement('span');
        closeBtn.style.cssText = 'cursor:pointer;font-size:15px;line-height:1;opacity:.65;margin-left:5px';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => span.remove()); // ← dùng addEventListener, không inline onclick

        span.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:5px 12px;' +
            'background:#fff0ee;border:1px solid #ffd4cc;border-radius:20px;' +
            'font-size:13px;color:#ee4d2d;margin:3px';

        span.appendChild(textNode);
        span.appendChild(closeBtn);
        return span;
    }
    // ═══════════════════════ TAB SWITCH ═══════════════════════
    function vpSwitchTab(key, el) {
        document.querySelectorAll('#viewContent .vp-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('#viewContent .vp-tab').forEach(t => t.classList.remove('active'));
        const panel = document.getElementById('vp-' + key);
        if (panel) panel.classList.add('active');
        if (el) el.classList.add('active');
    }
    // ═══════════════════════ COLOR / SIZE SELECTOR ═══════════════════════
    function vpSelectColor(name, el) {
        // ... code cũ giữ nguyên ...
        document.querySelectorAll('#vpColorChips .vp-color-chip').forEach(c => {
            c.classList.remove('active');
            c.style.border = '1px solid #e0e0e0';
            c.style.color = '#333';
            c.querySelectorAll('span').forEach(s => {
                if (s.style.position === 'absolute') s.remove();
            });
        });
        el.classList.add('active');
        el.style.border = '1.5px solid #ee4d2d';
        el.style.color = '#ee4d2d';
        const tick = document.createElement('span');
        tick.style.cssText = 'position:absolute;bottom:-1px;right:-1px;width:14px;height:14px;background:#ee4d2d;color:#fff;font-size:9px;display:flex;align-items:center;justify-content:center;border-radius:2px 0 2px 0';
        tick.textContent = '✓';
        el.appendChild(tick);
        window._vpActiveColor = name;
        const imgUrl = window._vpColorImgMap?.[name];
        if (imgUrl) {
            const img = document.getElementById('vpMainImg');
            if (img) img.src = imgUrl;
        }
        vpUpdatePrice();

        // ── MỚI: cập nhật label upload trong tab Hình ảnh ──
        vpUpdateUploadLabel(name);
    }

    function vpSelectSize(sz, el) {
        if (el.classList.contains('out')) return;
        document.querySelectorAll('#vpSizeChips .vp-size-chip').forEach(c => {
            c.classList.remove('active');
            c.style.border = '1px solid #e0e0e0';
            c.style.color = '#333';
        });
        el.classList.add('active');
        el.style.border = '1.5px solid #ee4d2d';
        el.style.color = '#ee4d2d';
        window._vpActiveSize = sz;
        vpUpdatePrice();
    }

    function vpUpdatePrice() {
        const key = (window._vpActiveColor || '') + '|' + (window._vpActiveSize || '');
        const v = window._vpVMap?.[key];
        if (!v) return;
        const price = parseFloat(v.price),
            cmp = parseFloat(v.compare_price || 0);
        document.getElementById('vpPriceMain').textContent = window._vpFmtVND(price);
        const cmpEl = document.getElementById('vpPriceCmp');
        const pctEl = document.getElementById('vpPricePct');
        if (cmp > price) {
            cmpEl.textContent = window._vpFmtVND(cmp);
            cmpEl.style.display = 'inline';
            pctEl.textContent = '-' + Math.round((1 - price / cmp) * 100) + '%';
            pctEl.style.display = 'inline';
        } else {
            cmpEl.style.display = pctEl.style.display = 'none';
        }
        const st = parseInt(v.stock_quantity),
            al = parseInt(v.low_stock_alert || 5);
        document.getElementById('vpStockLabel').innerHTML = window._vpStockLabel(st, al);
    }
    // ═══════════════════════ INLINE EDIT HELPERS ═══════════════════════
    function _vpMsg(id, ok, text) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.color = ok ? '#16a34a' : '#ee4d2d';
        el.innerHTML = (ok ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + text;
        if (ok) setTimeout(() => {
            el.textContent = '';
        }, 3000);
    }

    function _vpPost(params) {
        return fetch('/MantaMarket/admin/products/ajax_edit.php', {
            method: 'POST',
            body: new URLSearchParams(params)
        }).then(r => r.json());
    }

    function _vpReadField(id) {
        const el = document.getElementById(id);
        if (!el) return '';
        const inp = el.querySelector('[data-inline-input]');
        return inp ? inp.value : (el.dataset.value ?? el.textContent.trim());
    }

    function _vpToInput(el, type = 'text', extra = '') {
        const cur = el.dataset.value ?? el.textContent.trim();
        const style = `font-size:inherit;font-family:inherit;padding:3px 7px;border:1.5px solid #7c3aed;border-radius:5px;outline:none;background:#faf5ff;${extra}`;
        if (type === 'textarea') {
            el.innerHTML = `<textarea data-inline-input style="${style}width:100%;min-height:80px;resize:vertical">${cur}</textarea>`;
        } else if (type === 'select') {
            el.innerHTML = `<select data-inline-input style="${style}">${extra}</select>`;
            setTimeout(() => {
                const s = el.querySelector('[data-inline-input]');
                if (s) s.value = cur;
            }, 0);
            return el.querySelector('[data-inline-input]');
        } else {
            el.innerHTML = `<input data-inline-input type="${type}" value="${cur.replace(/"/g,'&quot;')}"
                          style="${style}width:100%" ${type==='number' ? 'min="0" step="1000"' : ''}>`;
        }
        const inp = el.querySelector('[data-inline-input]');
        inp?.focus();
        return inp;
    }

    function vpEditField(id, type) {
        const el = document.getElementById(id);
        if (!el || el.querySelector('[data-inline-input]')) return;
        _vpToInput(el, type, type === 'number' ? 'width:140px' : '');
    }

    function vpEditSelect(id) {
        const el = document.getElementById(id);
        if (!el || el.querySelector('[data-inline-input]')) return;
        _vpToInput(el, 'select', el.dataset.opts || '');
    }
    // ═══════════════════════ SAVE HANDLERS ═══════════════════════
    function vpSaveBasic(productId, productArrIdx) {
        const name = _vpReadField('info-name').trim();
        const slug = _vpReadField('info-slug').trim();
        const seller = _vpReadField('info-seller');
        const brand = _vpReadField('info-brand');
        const status = _vpReadField('info-status');
        const featured = _vpReadField('info-featured');
        const price = _vpReadField('info-price');
        const desc = _vpReadField('info-desc');
        if (!name) return _vpMsg('info-msg', false, 'Tên không được trống');
        if (!slug) return _vpMsg('info-msg', false, 'Slug không được trống');
        if (!seller) return _vpMsg('info-msg', false, 'Chưa chọn seller');
        _vpMsg('info-msg', true, 'Đang lưu...');
        _vpPost({
                section: 'basic',
                product_id: productId,
                name,
                slug,
                description: desc,
                base_price: String(price).replace(/[^0-9.]/g, ''),
                brand_id: brand,
                seller_id: seller,
                status,
                is_featured: featured
            })
            .then(res => {
                if (res.status !== 'success') return _vpMsg('info-msg', false, res.message || 'Lỗi');
                const p = products[productArrIdx];
                p.name = name;
                p.slug = slug;
                p.description = desc;
                p.base_price = parseFloat(String(price).replace(/[^0-9.]/g, '')) || 0;
                p.brand_id = brand || null;
                p.seller_id = parseInt(seller);
                p.status = status;
                p.is_featured = parseInt(featured);
                renderProducts();
                _vpMsg('info-msg', true, 'Đã lưu!');
            })
            .catch(() => _vpMsg('info-msg', false, 'Lỗi mạng'));
    }

    function vpSaveVariants(productId) {
        const rows = [...document.querySelectorAll('#ev-table tbody tr[data-vid]')];
        if (!rows.length) return _vpMsg('ev-msg', false, 'Không có biến thể');
        const variants = rows.map(row => ({
            id: row.dataset.vid,
            sku: row.querySelector('.ev-sku').value,
            color: row.querySelector('.ev-color').value,
            size: row.querySelector('.ev-size').value,
            material: row.querySelector('.ev-material').value,
            price: row.querySelector('.ev-price').value,
            compare_price: row.querySelector('.ev-compare').value,
            stock_quantity: row.querySelector('.ev-stock').value,
            low_stock_alert: row.querySelector('.ev-alert').value,
            is_active: row.querySelector('.ev-active').value,
        }));
        _vpMsg('ev-msg', true, 'Đang lưu...');
        _vpPost({
                section: 'variants',
                product_id: productId,
                variants_json: JSON.stringify(variants)
            })
            .then(res => {
                if (res.status !== 'success') return _vpMsg('ev-msg', false, res.message || 'Lỗi');
                _vpMsg('ev-msg', true, `Đã lưu ${variants.length} biến thể!`);
            })
            .catch(() => _vpMsg('ev-msg', false, 'Lỗi mạng'));
    }

    function vpSaveAttrs(productId) {
        const attrs = [...document.querySelectorAll('#ea-rows .ea-row')].map(r => ({
            attr_name: r.querySelector('.ea-name').value.trim(),
            attr_value: r.querySelector('.ea-value').value.trim(),
        })).filter(a => a.attr_name && a.attr_value);
        _vpMsg('ea-msg', true, 'Đang lưu...');
        _vpPost({
                section: 'attributes',
                product_id: productId,
                attributes_json: JSON.stringify(attrs)
            })
            .then(res => {
                if (res.status !== 'success') return _vpMsg('ea-msg', false, res.message || 'Lỗi');
                _vpMsg('ea-msg', true, `Đã lưu ${attrs.length} thuộc tính!`);
            })
            .catch(() => _vpMsg('ea-msg', false, 'Lỗi mạng'));
    }

    function vpAddAttrRow() {
        const div = document.createElement('div');
        div.className = 'ea-row';
        div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center;margin-bottom:7px';
        div.innerHTML = `
        <input class="ea-name" placeholder="Tên thuộc tính"
            style="padding:7px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px;font-family:inherit"
            onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        <input class="ea-value" placeholder="Giá trị"
            style="padding:7px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px;font-family:inherit"
            onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e0e0e0'">
        <button onclick="this.closest('.ea-row').remove()"
            style="padding:7px 11px;border:1px solid #fdd;border-radius:6px;background:#fff0f0;color:#ee4d2d;cursor:pointer;font-size:13px">
            <i class="fas fa-times"></i>
        </button>`;
        document.getElementById('ea-rows').appendChild(div);
        div.querySelector('.ea-name').focus();
    }

    // ════════ FIX 2: vpAddTag — trim và kiểm tra trùng đúng cách ════════
    function vpAddTag() {
        const inp = document.getElementById('et-input');
        if (!inp) return;
        const val = inp.value.trim();
        if (!val) return;
        const container = document.getElementById('et-tags');
        if (!container) return;

        // So sánh với dataset.tag (raw value), không phải textContent
        const exists = [...container.querySelectorAll('.et-tag')]
            .some(el => el.dataset.tag === val);
        if (exists) {
            inp.value = '';
            inp.focus();
            return;
        }

        container.appendChild(tagChip(val));
        inp.value = '';
        inp.focus();
    }

    function vpSaveTags(productId) {
        // ── Auto-flush: nếu input còn chữ thì add luôn trước khi lưu ──
        const inp = document.getElementById('et-input');
        if (inp && inp.value.trim() !== '') {
            vpAddTag(); // append chip vào DOM
        }

        const container = document.getElementById('et-tags');
        const tags = container ? [...new Set(
            [...container.querySelectorAll('.et-tag')]
            .map(el => (el.dataset.tag ?? '').trim())
            .filter(t => t !== '')
        )] : [];

        if (!productId) return _vpMsg('et-msg', false, 'Thiếu product_id');

        _vpMsg('et-msg', true, 'Đang lưu...');
        _vpPost({
                section: 'tags',
                product_id: productId,
                tags_json: JSON.stringify(tags)
            })
            .then(res => {
                if (res.status !== 'success')
                    return _vpMsg('et-msg', false, res.message || 'Lỗi khi lưu');
                _vpMsg('et-msg', true, `Đã lưu ${tags.length} tag!`);
            })
            .catch(() => _vpMsg('et-msg', false, 'Lỗi mạng'));
    }

    function vpSetPrimary(productId, imageId) {
        _vpPost({
                section: 'image_set_primary',
                product_id: productId,
                image_id: imageId
            })
            .then(res => {
                if (res.status !== 'success') return alert('Lỗi: ' + (res.message || ''));
                document.querySelectorAll('[data-img-id]').forEach(card => {
                    card.style.borderColor = '#e0e0e0';
                    card.querySelectorAll('[data-primary-label]').forEach(el => el.remove());
                });
                const card = document.querySelector(`[data-img-id="${imageId}"]`);
                if (!card) return;
                card.style.borderColor = '#ee4d2d';
                const infoDiv = card.querySelector('div:nth-child(2)');
                if (infoDiv) {
                    const lbl = document.createElement('div');
                    lbl.dataset.primaryLabel = '1';
                    lbl.style.cssText = 'color:#ee4d2d;font-weight:700;font-size:11px';
                    lbl.textContent = '★ Ảnh chính';
                    infoDiv.prepend(lbl);
                }
                const imgEl = card.querySelector('img');
                if (imgEl) {
                    const mainWrap = document.getElementById('vpMainImgWrap');
                    if (mainWrap) {
                        const main = mainWrap.querySelector('img');
                        if (main) main.src = imgEl.src;
                        else mainWrap.innerHTML = `<img id="vpMainImg" src="${imgEl.src}" style="width:100%;height:100%;object-fit:contain">`;
                    }
                }
            })
            .catch(() => alert('Lỗi mạng'));
    }

    function vpAddVariant(productId, productArrIdx) {
        const price = document.getElementById('nv-price').value;
        if (!price || Number(price) <= 0) {
            return _vpMsg('nv-msg', false, 'Vui lòng nhập giá bán');
        }
        const payload = {
            section: 'variant_add',
            product_id: productId,
            sku: document.getElementById('nv-sku').value.trim(),
            color: document.getElementById('nv-color').value.trim(),
            size: document.getElementById('nv-size').value.trim(),
            material: document.getElementById('nv-material').value.trim(),
            price: price,
            compare_price: document.getElementById('nv-compare').value || '0',
            stock_quantity: document.getElementById('nv-stock').value || '0',
            low_stock_alert: document.getElementById('nv-alert').value || '5',
            is_active: '1',
        };
        _vpMsg('nv-msg', true, 'Đang thêm...');
        _vpPost(payload)
            .then(res => {
                if (res.status !== 'success') return _vpMsg('nv-msg', false, res.message || 'Lỗi');
                _vpMsg('nv-msg', true, 'Đã thêm! Đang tải lại...');
                // Reload lại viewModal để cập nhật bảng biến thể
                setTimeout(() => viewProduct(productArrIdx), 800);
            })
            .catch(() => _vpMsg('nv-msg', false, 'Lỗi mạng'));
    }

    function vpDeleteImage(productId, imageId, btn) {
        if (!confirm('Xóa ảnh này?')) return;
        _vpPost({
                section: 'image_delete',
                product_id: productId,
                image_id: imageId
            })
            .then(res => {
                if (res.status !== 'success') return alert('Lỗi: ' + (res.message || ''));
                btn.closest('[data-img-id]').remove();
            })
            .catch(() => alert('Lỗi mạng'));
    }
    // ═══════════════════════ CHECKBOX ═══════════════════════
    function toggleAll(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        document.querySelectorAll('.row-check').forEach(c => {
            c.className = 'checkbox-custom row-check' + (el.classList.contains('checked') ? ' checked' : '');
            c.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        });
    }

    function toggleRow(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
    }
    // ═══════════════════════ EVENT LISTENERS ═══════════════════════
    document.getElementById('productSearch').addEventListener('input', () => {
        currentPage = 1;
        renderProducts();
    });
    document.getElementById('statusFilter').addEventListener('change', () => {
        currentPage = 1;
        renderProducts();
    });
    document.getElementById('brandFilter').addEventListener('change', () => {
        currentPage = 1;
        renderProducts();
    });
    document.getElementById('addModal').addEventListener('click', e => {
        if (e.target.id === 'addModal') closeAddModal();
    });
    document.getElementById('viewModal').addEventListener('click', e => {
        if (e.target.id === 'viewModal') e.target.style.display = 'none';
    });


    // ═══════════════════════ UPLOAD ẢNH ═══════════════════════
    function vpUpdateUploadLabel(colorName) {
        const lbl = document.getElementById('upload-color-label');
        if (!lbl) return;
        lbl.textContent = colorName ? `● ${colorName}` : 'Không gắn màu';
        lbl.style.color = colorName ? '#0284c7' : '#94a3b8';
        lbl.style.borderColor = colorName ? '#7dd3fc' : '#e2e8f0';
    }

    function vpUploadImages(productId, productArrIdx) {
        const input = document.getElementById('img-file-input');
        const files = input?.files;
        if (!files || files.length === 0) {
            return _vpMsg('img-upload-progress', false, 'Vui lòng chọn ít nhất 1 ảnh');
        }

        const color = window._vpActiveColor || '';
        const isPrimary = document.getElementById('img-is-primary')?.checked ? 1 : 0;
        const prog = document.getElementById('img-upload-progress');
        prog.style.color = '#0284c7';
        prog.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang upload...';

        // Upload tuần tự từng file
        const fileArr = [...files];
        let done = 0,
            failed = 0;

        const uploadOne = (file) => {
            // Validate size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                failed++;
                return Promise.resolve();
            }
            const fd = new FormData();
            fd.append('section', 'image_upload');
            fd.append('product_id', productId);
            fd.append('color', color);
            fd.append('is_primary', done === 0 && isPrimary ? 1 : 0); // chỉ ảnh đầu tiên làm primary
            fd.append('image', file);

            return fetch('/MantaMarket/admin/products/ajax_edit.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        done++;
                        // Thêm card ảnh mới vào grid ngay lập tức (không cần reload)
                        vpAppendImageCard(productId, res.image, productArrIdx);
                        // Cập nhật colorImgMap nếu có màu
                        if (color && !window._vpColorImgMap?.[color]) {
                            window._vpColorImgMap[color] = res.image.image_url;
                            // Cập nhật ảnh chính nếu đang chọn màu này
                            if (window._vpActiveColor === color) {
                                const mainImg = document.getElementById('vpMainImg');
                                if (mainImg) mainImg.src = res.image.image_url;
                            }
                        }
                    } else {
                        failed++;
                    }
                })
                .catch(() => {
                    failed++;
                });
        };

        // Chạy tuần tự
        fileArr.reduce((chain, file) => chain.then(() => uploadOne(file)), Promise.resolve())
            .then(() => {
                prog.style.color = done > 0 ? '#16a34a' : '#ee4d2d';
                prog.innerHTML = done > 0 ?
                    `<i class="fas fa-check-circle"></i> Upload ${done} ảnh thành công${failed > 0 ? `, ${failed} thất bại` : ''}!` :
                    `<i class="fas fa-times-circle"></i> Upload thất bại`;
                if (done > 0) {
                    input.value = '';
                    document.getElementById('img-is-primary').checked = false;
                    // Ẩn thông báo "Chưa có hình ảnh" nếu có
                    const emptyMsg = document.getElementById('img-empty-msg');
                    if (emptyMsg) emptyMsg.remove();
                }
                setTimeout(() => {
                    if (prog) prog.innerHTML = '';
                }, 4000);
            });
    }

    function vpAppendImageCard(productId, img, productArrIdx) {
        const grid = document.getElementById('img-grid');
        if (!grid) return;

        const card = document.createElement('div');
        card.dataset.imgId = img.id;
        card.style.cssText = `border:2px solid ${img.is_primary ? '#ee4d2d' : '#e0e0e0'};
        border-radius:8px;overflow:hidden;background:#fff;
        display:flex;flex-direction:column;transition:border-color .2s`;

        card.innerHTML = `
        <div style="aspect-ratio:1;overflow:hidden;background:#fafafa;
                    display:flex;align-items:center;justify-content:center">
            <img src="${img.image_url}" alt=""
                 style="width:100%;height:100%;object-fit:contain"
                 onerror="this.parentElement.innerHTML='<span style=color:#ddd;font-size:11px>No img</span>'">
        </div>
        <div style="padding:5px 7px;font-size:11px;color:#999;flex:1;line-height:1.4">
            ${img.variant_color ? `<div style="color:#7c3aed">● ${img.variant_color}</div>` : ''}
            ${img.is_primary    ? `<div style="color:#ee4d2d;font-weight:700" data-primary-label>★ Ảnh chính</div>` : ''}
        </div>
        <div style="padding:0 6px 7px;display:flex;gap:4px">
            ${!img.is_primary ? `
            <button title="Đặt làm ảnh chính"
                style="flex:1;padding:4px 0;border:1px solid #e0e0e0;border-radius:5px;
                       font-size:11px;cursor:pointer;background:#fff;color:#7c3aed"
                onmouseover="this.style.background='#faf5ff'"
                onmouseout="this.style.background='#fff'">
                <i class="fas fa-star"></i>
            </button>` : '<div style="flex:1"></div>'}
            <button title="Xóa ảnh"
                style="flex:1;padding:4px 0;border:1px solid #fdd;border-radius:5px;
                       font-size:11px;cursor:pointer;background:#fff;color:#ee4d2d"
                onmouseover="this.style.background='#fff0f0'"
                onmouseout="this.style.background='#fff'">
                <i class="fas fa-trash"></i>
            </button>
        </div>`;

        // Gắn event bằng JS thuần — tránh inline onclick với biến động
        const [starBtn, trashBtn] = card.querySelectorAll('button');
        if (starBtn) starBtn.addEventListener('click', () => vpSetPrimary(productId, img.id));
        if (trashBtn) trashBtn.addEventListener('click', () => vpDeleteImage(productId, img.id, trashBtn));

        grid.appendChild(card);
    }
    // ── Khởi chạy ──
    renderProducts();
</script>