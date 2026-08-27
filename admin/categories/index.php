<?php
$user       = new app_Libs_UserIdentity();
$router     = new app_Libs_Router();
$categories = new app_Models_Categories();
$db         = new app_Libs_DbConnection();
$action     = $router->getPOST("action");
$id         = intval($router->getPOST("id") ?? $router->getGET("id"));

// ── XÓA 1 DANH MỤC ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $categories->buildQueryParams([
        "where"  => "id = :id",
        "params" => [":id" => $id]
    ])->delete();
    header("Location: /MantaMarket/admin/index.php?page=categories");
    exit();
}

// ── XÓA NHIỀU DANH MỤC ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $categories->buildQueryParams([
                "where"  => "id = :id",
                "params" => [":id" => $did]
            ])->delete();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=categories");
    exit();
}

// ── ẨN/HIỆN NHIỀU DANH MỤC ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_toggle') {
    $ids    = $_POST['ids'] ?? [];
    $status = intval($_POST['is_active'] ?? 1);
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $categories->buildQueryParams([
                "value"  => "is_active = :is_active",
                "where"  => "id = :id",
                "params" => [":is_active" => $status, ":id" => $did]
            ])->update();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=categories");
    exit();
}

// ── THÊM DANH MỤC ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'insert') {
    $name       = trim($router->getPOST("name"));
    $image_url  = trim($router->getPOST("image_url"));
    $slug       = trim($router->getPOST("slug"));
    $sort_order = intval($router->getPOST("sort_order"));
    $is_active  = intval($router->getPOST("is_active") ?? 1);

    $checkSlug = $categories->buildQueryParams([
        "where"  => "slug = :slug",
        "params" => [":slug" => $slug]
    ])->selectOne();
    if ($checkSlug) {
        header("Location: /MantaMarket/admin/index.php?page=categories&error=slug");
        exit();
    }

    $categories->buildQueryParams([
        "field" => "(name, image_url, slug, sort_order, is_active) VALUES (:name, :image_url, :slug, :sort_order, :is_active)",
        "value" => [
            ":name"       => $name,
            ":image_url"  => $image_url,
            ":slug"       => $slug,
            ":sort_order" => $sort_order,
            ":is_active"  => $is_active
        ]
    ])->insert();
    header("Location: /MantaMarket/admin/index.php?page=categories");
    exit();
}

// ── CẬP NHẬT DANH MỤC ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $id) {
    $name       = trim($router->getPOST("name"));
    $image_url  = trim($router->getPOST("image_url"));
    $slug       = trim($router->getPOST("slug"));
    $sort_order = intval($router->getPOST("sort_order"));
    $is_active  = intval($router->getPOST("is_active") ?? 1);

    $checkSlug = $categories->buildQueryParams([
        "where"  => "slug = :slug AND id != :id",
        "params" => [":slug" => $slug, ":id" => $id]
    ])->selectOne();
    if ($checkSlug) {
        header("Location: /MantaMarket/admin/index.php?page=categories&error=slug");
        exit();
    }

    $categories->buildQueryParams([
        "value"  => "name = :name, image_url = :image_url, slug = :slug, sort_order = :sort_order, is_active = :is_active",
        "where"  => "id = :id",
        "params" => [
            ":name"       => $name,
            ":image_url"  => $image_url,
            ":slug"       => $slug,
            ":sort_order" => $sort_order,
            ":is_active"  => $is_active,
            ":id"         => $id
        ]
    ])->update();
    header("Location: /MantaMarket/admin/index.php?page=categories");
    exit();
}

// ── LẤY DỮ LIỆU ──
$categoriesx = $categories->buildQueryParams([])->select();
$totalAll    = count($categoriesx);
$totalShow   = count(array_filter($categoriesx, fn($r) => $r['is_active'] == 1));
$totalHide   = $totalAll - $totalShow;

// Lấy brands theo từng category qua bảng brand_categories
$bcRows = $db->query(
    "SELECT bc.category_id, b.id AS brand_id, b.name AS brand_name, b.logo_url
     FROM brand_categories bc
     JOIN brands b ON b.id = bc.brand_id
     ORDER BY b.name"
)->fetchAll();

$catBrandMap = [];  // category_id => [{brand_id, brand_name, logo_url}, ...]
foreach ($bcRows as $row) {
    $catBrandMap[$row['category_id']][] = [
        'id'       => $row['brand_id'],
        'name'     => $row['brand_name'],
        'logo_url' => $row['logo_url'],
    ];
}

// Gắn brands vào từng danh mục
foreach ($categoriesx as &$cat) {
    $cat['brands'] = $catBrandMap[$cat['id']] ?? [];
}
unset($cat);
?>
<div class="page" id="page-categories">
    <!-- HEADER -->
    <div class="page-header">
        <h1 class="page-title">Quản lý danh mục</h1>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div class="stat-card">
                <div class="stat-label">Tổng danh mục</div>
                <div class="stat-value"><?= $totalAll ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Đang hiển thị</div>
                <div class="stat-value"><?= $totalShow ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Đang ẩn</div>
                <div class="stat-value"><?= $totalHide ?></div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="order-filter-bar" style="flex-wrap:wrap;gap:8px">
        <div class="order-filter-select" style="gap:6px">
            <div class="checkbox-custom" id="catCheckAll" onclick="toggleCatAll(this)" style="width:14px;height:14px"></div>
            <span style="font-size:13px;color:var(--muted)">Chọn tất cả</span>
        </div>
        <div class="order-filter-select2">
            <select id="catStatusFilter" onchange="catPage=1;renderCat()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Hiển thị</option>
                <option value="0">Ẩn</option>
            </select>
        </div>
        <div class="order-search-wrap" style="flex:1;min-width:180px">
            <i class="fas fa-search"></i>
            <input class="order-search-input" type="text" id="catSearch"
                placeholder="Tìm theo tên, slug..." oninput="catPage=1;renderCat()">
        </div>
        <div class="page-actions">
            <button class="btn-primary" onclick="openCatModal()">
                <i class="fas fa-plus"></i> Thêm danh mục
            </button>
        </div>
        <button class="btn-action" onclick="catBulkDelete()" title="Xóa đã chọn">
            <i class="fas fa-trash"></i> Xóa
        </button>
        <button class="btn-action" onclick="catBulkToggle(1)" title="Hiện đã chọn">
            <i class="fas fa-eye"></i>
        </button>
        <button class="btn-action" onclick="catBulkToggle(0)" title="Ẩn đã chọn">
            <i class="fas fa-eye-slash"></i>
        </button>
    </div>

    <!-- TABLE -->
    <div class="order-table-card">
        <div class="order-table-head">
            <span style="font-size:13px;color:var(--muted)" id="catSelLabel">Thao tác</span>
            <div class="pagination" id="catTopPag"></div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th onclick="setCatSort('id')" style="cursor:pointer">
                            ID <i class="fas fa-sort" id="sort-id" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setCatSort('name')" style="cursor:pointer">
                            Tên danh mục <i class="fas fa-sort" id="sort-name" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Ảnh</th>
                        <th>Slug</th>
                        <th onclick="setCatSort('sort_order')" style="cursor:pointer">
                            Thứ tự <i class="fas fa-sort" id="sort-sort_order" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <!-- CỘT MỚI: Thương hiệu -->
                        <th>Thương hiệu</th>
                        <th>Trạng thái</th>
                        <th onclick="setCatSort('created_at')" style="cursor:pointer">
                            Ngày tạo <i class="fas fa-sort" id="sort-created_at" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setCatSort('updated_at')" style="cursor:pointer">
                            Cập nhật <i class="fas fa-sort" id="sort-updated_at" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="catTableBody"></tbody>
            </table>
        </div>
        <div class="order-table-footer">
            <div style="font-size:13px;color:var(--muted)" id="catTableInfo"></div>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="pagination" id="catBotPag"></div>
                <span style="font-size:13px;color:var(--muted)" id="catPageLabel"></span>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL THÊM / SỬA ── -->
<div class="modal-bg" id="catModal" style="display:none">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
            <h2 style="font-size:20px;font-weight:800;color:var(--text)" id="catModalTitle">Thêm danh mục</h2>
            <button onclick="closeCatModal()" style="background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="catForm" method="POST" action="">
            <input type="hidden" name="action" id="catAction" value="insert">
            <input type="hidden" name="id" id="catId" value="">
            <div style="display:grid;gap:14px">
                <div>
                    <label class="modal-label">Tên danh mục <span style="color:red">*</span></label>
                    <input class="modal-input" type="text" name="name" id="catName"
                        placeholder="VD: Điện thoại" oninput="autoSlug()" required>
                </div>
                <div>
                    <label class="modal-label">Link ảnh</label>
                    <input class="modal-input" type="text" name="image_url" id="catImage"
                        placeholder="img/categories/dienthoai.jpg">
                </div>
                <div>
                    <label class="modal-label">Slug <span style="color:red">*</span></label>
                    <input class="modal-input" type="text" name="slug" id="catSlug"
                        placeholder="dien-thoai" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="modal-label">Thứ tự hiển thị</label>
                        <input class="modal-input" type="number" name="sort_order" id="catSortOrder"
                            value="0" min="0">
                    </div>
                    <div>
                        <label class="modal-label">Trạng thái</label>
                        <select class="modal-input" name="is_active" id="catIsActive">
                            <option value="1">Hiển thị</option>
                            <option value="0">Ẩn</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px">
                <button type="button" onclick="closeCatModal()"
                    style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                    Hủy
                </button>
                <button type="button" onclick="submitCatForm()" class="btn-primary" style="flex:1;justify-content:center">
                    <i class="fas fa-check"></i> Lưu danh mục
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL XÁC NHẬN XÓA ── -->
<div class="modal-bg" id="catConfirmModal" style="display:none">
    <div class="modal-box" style="max-width:400px">
        <div style="text-align:center;padding:10px 0">
            <div style="font-size:48px;margin-bottom:12px">🗑️</div>
            <h2 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:8px">Xác nhận xóa</h2>
            <p style="font-size:14px;color:var(--muted)" id="catConfirmMsg">Bạn có chắc muốn xóa danh mục này?</p>
        </div>
        <input type="hidden" id="catDeleteId" value="">
        <div style="display:flex;gap:10px;margin-top:20px">
            <button onclick="closeCatConfirm()"
                style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                Hủy
            </button>
            <button onclick="submitDeleteCat()"
                style="flex:1;background:#ef4444;border:none;border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:#fff;cursor:pointer;font-family:inherit">
                <i class="fas fa-trash"></i> Xóa
            </button>
        </div>
    </div>
</div>

<script>
    // ── DỮ LIỆU TỪ PHP ──
    const CAT_DATA = <?= json_encode(array_values($categoriesx)) ?>;

    // ── STATE ──
    var catPage    = 1;
    const CAT_PER  = 10;
    var catSortKey = 'sort_order';
    var catSortAsc = true;

    // ── AUTO SLUG ──
    function autoSlug() {
        const name = document.getElementById('catName').value;
        if (document.getElementById('catAction').value !== 'insert') return;
        const slug = name.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd').replace(/[^a-z0-9\s-]/g, '')
            .trim().replace(/\s+/g, '-');
        document.getElementById('catSlug').value = slug;
    }

    // ── SORT ──
    function setCatSort(key) {
        if (catSortKey === key) catSortAsc = !catSortAsc;
        else { catSortKey = key; catSortAsc = true; }
        document.querySelectorAll('[id^="sort-"]').forEach(el => {
            el.className = 'fas fa-sort';
            el.style.color = 'var(--muted)';
        });
        const ic = document.getElementById('sort-' + key);
        if (ic) {
            ic.className = catSortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
            ic.style.color = 'var(--primary, #7c3aed)';
        }
        catPage = 1;
        renderCat();
    }

    // ── FILTER ──
    function getFilteredCats() {
        const search = document.getElementById('catSearch').value.toLowerCase();
        const status = document.getElementById('catStatusFilter').value;
        return CAT_DATA
            .filter(c => {
                const matchSearch = !search ||
                    (c.name && c.name.toLowerCase().includes(search)) ||
                    (c.slug && c.slug.toLowerCase().includes(search));
                const matchStatus = status === '' || String(c.is_active) === status;
                return matchSearch && matchStatus;
            })
            .sort((a, b) => {
                let va = a[catSortKey] ?? '';
                let vb = b[catSortKey] ?? '';
                if (catSortKey === 'sort_order' || catSortKey === 'id') { va = Number(va); vb = Number(vb); }
                else if (typeof va === 'string') { va = va.toLowerCase(); vb = vb.toLowerCase(); }
                if (va < vb) return catSortAsc ? -1 : 1;
                if (va > vb) return catSortAsc ?  1 : -1;
                return 0;
            });
    }

    // ── RENDER ──
    function renderCat() {
        const filtered   = getFilteredCats();
        const totalPages = Math.max(1, Math.ceil(filtered.length / CAT_PER));
        if (catPage > totalPages) catPage = totalPages;
        const start = (catPage - 1) * CAT_PER;
        const page  = filtered.slice(start, start + CAT_PER);

        document.getElementById('catTableBody').innerHTML = page.map(c => {
            const isActive    = c.is_active == 1;
            const statusBg    = isActive ? '#d1fae5' : '#fef3c7';
            const statusColor = isActive ? '#059669' : '#d97706';
            const statusLabel = isActive ? 'Hiển thị' : 'Ẩn';

            const imgHtml = c.image_url
                ? `<img src="/MantaMarket/${c.image_url}" style="width:44px;height:44px;object-fit:cover;border-radius:8px">`
                : `<span style="color:var(--muted);font-size:12px">—</span>`;

            // Hiển thị brands gắn với danh mục này
            const brandTags = (c.brands && c.brands.length)
                ? c.brands.map(b =>
                    `<span style="display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:20px;padding:2px 8px 2px 4px;font-size:11px;font-weight:600;color:#374151;margin:2px">
                        ${b.logo_url
                            ? `<img src="/MantaMarket/${b.logo_url}" style="width:16px;height:16px;border-radius:50%;object-fit:cover">`
                            : `<span style="width:16px;height:16px;border-radius:50%;background:#ddd;display:inline-block"></span>`
                        }
                        ${b.name}
                     </span>`
                  ).join('')
                : `<span style="color:var(--muted);font-size:12px">—</span>`;

            const createdAt = c.created_at ? c.created_at.substring(0, 10) : '—';
            const updatedAt = c.updated_at ? c.updated_at.substring(0, 10) : '—';

            return `<tr>
                <td><div class="checkbox-custom row-check cat-check" data-id="${c.id}" onclick="toggleCatRow(this)"></div></td>
                <td style="font-size:13px;color:var(--muted);font-weight:600">${c.id}</td>
                <td style="font-weight:600;font-size:14px">${c.name}</td>
                <td>${imgHtml}</td>
                <td style="font-size:12px;color:var(--muted)">${c.slug || '—'}</td>
                <td style="text-align:center">${c.sort_order}</td>
                <td style="max-width:200px;white-space:normal">${brandTags}</td>
                <td><span class="badge" style="background:${statusBg};color:${statusColor}">${statusLabel}</span></td>
                <td style="font-size:12px">${createdAt}</td>
                <td style="font-size:12px">${updatedAt}</td>
                <td class="actions-cell">
                    <button class="btn-edit" onclick='openEditCat(${JSON.stringify(c)})'><i class="fas fa-edit"></i> Sửa</button>
                    <button class="btn-del"  onclick="confirmDeleteCat(${c.id},'${c.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i> Xóa</button>
                </td>
            </tr>`;
        }).join('');

        const pagHtml = () => {
            let h = '';
            if (catPage > 1)
                h += `<button class="page-btn" onclick="goToCatPage(${catPage-1})"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
            for (let i = 1; i <= totalPages; i++)
                h += `<button class="page-btn${i===catPage?' active':''}" onclick="goToCatPage(${i})">${i}</button>`;
            if (catPage < totalPages)
                h += `<button class="page-btn" onclick="goToCatPage(${catPage+1})"><i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
            return h;
        };

        document.getElementById('catTopPag').innerHTML  = pagHtml();
        document.getElementById('catBotPag').innerHTML  = pagHtml();
        document.getElementById('catTableInfo').textContent =
            `${start+1} – ${Math.min(start+CAT_PER, filtered.length)} trên ${filtered.length} danh mục`;
        document.getElementById('catPageLabel').textContent = `${catPage} / ${totalPages}`;
        updateCatSelLabel();
    }

    // ── MODAL THÊM/SỬA ──
    function openCatModal() {
        document.getElementById('catModalTitle').textContent = 'Thêm danh mục mới';
        document.getElementById('catAction').value    = 'insert';
        document.getElementById('catId').value        = '';
        document.getElementById('catName').value      = '';
        document.getElementById('catImage').value     = '';
        document.getElementById('catSlug').value      = '';
        document.getElementById('catSortOrder').value = '0';
        document.getElementById('catIsActive').value  = '1';
        document.getElementById('catModal').style.display = 'flex';
    }

    function openEditCat(c) {
        document.getElementById('catModalTitle').textContent = 'Chỉnh sửa danh mục';
        document.getElementById('catAction').value    = 'update';
        document.getElementById('catId').value        = c.id;
        document.getElementById('catName').value      = c.name;
        document.getElementById('catImage').value     = c.image_url   || '';
        document.getElementById('catSlug').value      = c.slug        || '';
        document.getElementById('catSortOrder').value = c.sort_order  || 0;
        document.getElementById('catIsActive').value  = c.is_active;
        document.getElementById('catModal').style.display = 'flex';
    }

    function submitCatForm() {
        const form = document.getElementById('catForm');
        const data = new FormData(form);
        fetch('/MantaMarket/admin/index.php?page=categories', { method: 'POST', body: data })
            .then(() => { closeCatModal(); location.hash = 'categories'; location.reload(); });
    }

    function closeCatModal() {
        document.getElementById('catModal').style.display = 'none';
    }

    // ── XÁC NHẬN XÓA ──
    function confirmDeleteCat(id, name) {
        document.getElementById('catConfirmMsg').textContent = `Bạn có chắc muốn xóa danh mục "${name}"?`;
        document.getElementById('catDeleteId').value         = id;
        document.getElementById('catConfirmModal').style.display = 'flex';
    }

    function closeCatConfirm() {
        document.getElementById('catConfirmModal').style.display = 'none';
    }

    function submitDeleteCat() {
        const id   = document.getElementById('catDeleteId').value;
        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);
        fetch('/MantaMarket/admin/index.php?page=categories', { method: 'POST', body: data })
            .then(() => { closeCatConfirm(); location.hash = 'categories'; location.reload(); });
    }

    // ── CHECKBOX ──
    function toggleCatAll(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        const checked = el.classList.contains('checked');
        document.querySelectorAll('#catTableBody .cat-check').forEach(c => {
            c.className = 'checkbox-custom row-check cat-check' + (checked ? ' checked' : '');
            c.innerHTML = checked ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        });
        updateCatSelLabel();
    }

    function toggleCatRow(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        updateCatSelLabel();
    }

    function updateCatSelLabel() {
        const count = document.querySelectorAll('#catTableBody .cat-check.checked').length;
        const lbl   = document.getElementById('catSelLabel');
        if (lbl) lbl.textContent = count ? `Chọn ${count} danh mục` : 'Chọn 0 danh mục';
    }

    function getCheckedCatIds() {
        return [...document.querySelectorAll('#catTableBody .cat-check.checked')].map(el => el.dataset.id);
    }

    // ── XÓA NHIỀU ──
    function catBulkDelete() {
        const ids = getCheckedCatIds();
        if (!ids.length) { alert('Vui lòng chọn danh mục cần xóa'); return; }
        if (!confirm(`Bạn có chắc muốn xóa ${ids.length} danh mục đã chọn?`)) return;
        const data = new FormData();
        data.append('action', 'bulk_delete');
        ids.forEach(id => data.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=categories', { method: 'POST', body: data })
            .then(() => { location.hash = 'categories'; location.reload(); });
    }

    // ── ẨN/HIỆN NHIỀU ──
    function catBulkToggle(status) {
        const ids = getCheckedCatIds();
        if (!ids.length) { alert('Vui lòng chọn danh mục'); return; }
        if (!confirm(`${status==1?'Hiện':'Ẩn'} ${ids.length} danh mục đã chọn?`)) return;
        const data = new FormData();
        data.append('action', 'bulk_toggle');
        data.append('is_active', status);
        ids.forEach(id => data.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=categories', { method: 'POST', body: data })
            .then(() => { location.hash = 'categories'; location.reload(); });
    }

    // ── ĐÓNG MODAL KHI CLICK NGOÀI ──
    ['catModal', 'catConfirmModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    });

    function goToCatPage(p) { catPage = p; renderCat(); }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof renderCat === 'function') renderCat();
    });
</script>