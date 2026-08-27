<?php
$user       = new app_Libs_UserIdentity();
$router     = new app_Libs_Router();
$brands     = new app_Models_Brands();
$db         = new app_Libs_DbConnection();
$action     = $router->getPOST("action");
$id         = intval($router->getPOST("id") ?? $router->getGET("id"));

// ── HELPER: lưu brand_categories ──
function saveBrandCategories($db, $brandId, $categoryIds) {
    // Xóa cũ
    $db->query(
        "DELETE FROM brand_categories WHERE brand_id = :bid",
        [":bid" => $brandId]
    );
    // Chèn mới
    if (!empty($categoryIds)) {
        foreach ($categoryIds as $cid) {
            $cid = intval($cid);
            if ($cid) {
                $db->query(
                    "INSERT IGNORE INTO brand_categories (brand_id, category_id) VALUES (:bid, :cid)",
                    [":bid" => $brandId, ":cid" => $cid]
                );
            }
        }
    }
}

// ── XÓA 1 THƯƠNG HIỆU ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $brands->buildQueryParams([
        "where"  => "id = :id",
        "params" => [":id" => $id]
    ])->delete();
    header("Location: /MantaMarket/admin/index.php?page=brands");
    exit();
}

// ── XÓA NHIỀU THƯƠNG HIỆU ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $brands->buildQueryParams([
                "where"  => "id = :id",
                "params" => [":id" => $did]
            ])->delete();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=brands");
    exit();
}

// ── ẨN/HIỆN NHIỀU THƯƠNG HIỆU ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_toggle') {
    $ids    = $_POST['ids'] ?? [];
    $status = intval($_POST['is_active'] ?? 1);
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $brands->buildQueryParams([
                "value"  => "is_active = :is_active",
                "where"  => "id = :id",
                "params" => [":is_active" => $status, ":id" => $did]
            ])->update();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=brands");
    exit();
}

// ── THÊM THƯƠNG HIỆU ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'insert') {
    $name        = trim($router->getPOST("name"));
    $slug        = trim($router->getPOST("slug"));
    $logo_url    = trim($router->getPOST("logo_url"));
    $description = trim($router->getPOST("description"));
    $country     = trim($router->getPOST("country"));
    $is_active   = intval($router->getPOST("is_active") ?? 1);
    $catIds      = $_POST['category_ids'] ?? [];

    $checkSlug = $brands->buildQueryParams([
        "where"  => "slug = :slug",
        "params" => [":slug" => $slug]
    ])->selectOne();
    if ($checkSlug) {
        header("Location: /MantaMarket/admin/index.php?page=brands&error=slug");
        exit();
    }

    $brands->buildQueryParams([
        "field" => "(name, slug, logo_url, description, country, is_active) VALUES (:name, :slug, :logo_url, :description, :country, :is_active)",
        "value" => [
            ":name"        => $name,
            ":slug"        => $slug,
            ":logo_url"    => $logo_url ?: null,
            ":description" => $description ?: null,
            ":country"     => $country ?: null,
            ":is_active"   => $is_active
        ]
    ])->insert();

    // Lấy ID vừa insert
    $newBrand = $brands->buildQueryParams([
        "where"  => "slug = :slug",
        "params" => [":slug" => $slug]
    ])->selectOne();
    if ($newBrand && !empty($catIds)) {
        saveBrandCategories($db, $newBrand['id'], $catIds);
    }

    header("Location: /MantaMarket/admin/index.php?page=brands");
    exit();
}

// ── CẬP NHẬT THƯƠNG HIỆU ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $id) {
    $name        = trim($router->getPOST("name"));
    $slug        = trim($router->getPOST("slug"));
    $logo_url    = trim($router->getPOST("logo_url"));
    $description = trim($router->getPOST("description"));
    $country     = trim($router->getPOST("country"));
    $is_active   = intval($router->getPOST("is_active") ?? 1);
    $catIds      = $_POST['category_ids'] ?? [];

    $checkSlug = $brands->buildQueryParams([
        "where"  => "slug = :slug AND id != :id",
        "params" => [":slug" => $slug, ":id" => $id]
    ])->selectOne();
    if ($checkSlug) {
        header("Location: /MantaMarket/admin/index.php?page=brands&error=slug");
        exit();
    }

    $brands->buildQueryParams([
        "value"  => "name = :name, slug = :slug, logo_url = :logo_url, description = :description, country = :country, is_active = :is_active",
        "where"  => "id = :id",
        "params" => [
            ":name"        => $name,
            ":slug"        => $slug,
            ":logo_url"    => $logo_url ?: null,
            ":description" => $description ?: null,
            ":country"     => $country ?: null,
            ":is_active"   => $is_active,
            ":id"          => $id
        ]
    ])->update();

    saveBrandCategories($db, $id, $catIds);

    header("Location: /MantaMarket/admin/index.php?page=brands");
    exit();
}

// ── LẤY DỮ LIỆU ──
$brandsx   = $brands->buildQueryParams([])->select();
$totalAll  = count($brandsx);
$totalShow = count(array_filter($brandsx, fn($r) => $r['is_active'] == 1));
$totalHide = $totalAll - $totalShow;

// Lấy tất cả danh mục để hiển thị checkbox
$categoriesAll = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();

// Map brand_id => [category_ids] để biết brand đang thuộc danh mục nào
$bcRows = $db->query("SELECT brand_id, category_id FROM brand_categories")->fetchAll();
$brandCatMap = [];
foreach ($bcRows as $row) {
    $brandCatMap[$row['brand_id']][] = $row['category_id'];
}

// Gắn category_ids vào từng brand
foreach ($brandsx as &$b) {
    $b['category_ids'] = $brandCatMap[$b['id']] ?? [];
}
unset($b);
?>
<div class="page" id="page-brands">
    <!-- HEADER -->
    <div class="page-header">
        <h1 class="page-title">Quản lý thương hiệu</h1>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div class="stat-card">
                <div class="stat-label">Tổng thương hiệu</div>
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
            <div class="checkbox-custom" id="braCheckAll" onclick="toggleBraAll(this)" style="width:14px;height:14px"></div>
            <span style="font-size:13px;color:var(--muted)">Chọn tất cả</span>
        </div>
        <div class="order-filter-select2">
            <select id="braStatusFilter" onchange="braPage=1;renderBrands()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Hiển thị</option>
                <option value="0">Ẩn</option>
            </select>
        </div>
        <!-- Lọc theo danh mục -->
        <div class="order-filter-select2">
            <select id="braCatFilter" onchange="braPage=1;renderBrands()">
                <option value="">Tất cả danh mục</option>
                <?php foreach ($categoriesAll as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="order-search-wrap" style="flex:1;min-width:180px">
            <i class="fas fa-search"></i>
            <input class="order-search-input" type="text" id="braSearch"
                placeholder="Tìm theo tên, slug, quốc gia..." oninput="braPage=1;renderBrands()">
        </div>
        <div class="page-actions">
            <button class="btn-primary" onclick="openBraModal()">
                <i class="fas fa-plus"></i> Thêm thương hiệu
            </button>
        </div>
        <button class="btn-action" onclick="braBulkDelete()" title="Xóa đã chọn">
            <i class="fas fa-trash"></i> Xóa
        </button>
        <button class="btn-action" onclick="braBulkToggle(1)" title="Hiện đã chọn">
            <i class="fas fa-eye"></i>
        </button>
        <button class="btn-action" onclick="braBulkToggle(0)" title="Ẩn đã chọn">
            <i class="fas fa-eye-slash"></i>
        </button>
    </div>

    <!-- TABLE -->
    <div class="order-table-card">
        <div class="order-table-head">
            <span style="font-size:13px;color:var(--muted)" id="braSelLabel">Thao tác</span>
            <div class="pagination" id="braTopPag"></div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th onclick="setBraSort('id')" style="cursor:pointer">
                            ID <i class="fas fa-sort" id="brasort-id" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setBraSort('name')" style="cursor:pointer">
                            Tên thương hiệu <i class="fas fa-sort" id="brasort-name" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Logo</th>
                        <th>Slug</th>
                        <th onclick="setBraSort('country')" style="cursor:pointer">
                            Quốc gia <i class="fas fa-sort" id="brasort-country" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Danh mục</th>
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                        <th onclick="setBraSort('created_at')" style="cursor:pointer">
                            Ngày tạo <i class="fas fa-sort" id="brasort-created_at" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setBraSort('updated_at')" style="cursor:pointer">
                            Cập nhật <i class="fas fa-sort" id="brasort-updated_at" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="braTableBody"></tbody>
            </table>
        </div>
        <div class="order-table-footer">
            <div style="font-size:13px;color:var(--muted)" id="braTableInfo"></div>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="pagination" id="braBotPag"></div>
                <span style="font-size:13px;color:var(--muted)" id="braPageLabel"></span>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL THÊM / SỬA ── -->
<div class="modal-bg" id="braModal" style="display:none">
    <div class="modal-box" style="max-width:1200px;width:95vw;max-height:90vh;display:flex;flex-direction:column;overflow:hidden">
        <!-- Header cố định -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-shrink:0">
            <h2 style="font-size:20px;font-weight:800;color:var(--text)" id="braModalTitle">Thêm thương hiệu</h2>
            <button onclick="closeBraModal()" style="background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="braForm" method="POST" action="" style="display:flex;flex-direction:column;overflow:hidden;flex:1">
            <input type="hidden" name="action" id="braAction" value="insert">
            <input type="hidden" name="id"     id="braId"     value="">
            <!-- Vùng scroll chứa các field -->
            <div style="overflow-y:auto;flex:1;padding-right:4px;margin-right:-4px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="modal-label">Tên thương hiệu <span style="color:red">*</span></label>
                    <input class="modal-input" type="text" name="name" id="braName"
                        placeholder="VD: Samsung" oninput="autoSlugBra()" required>
                </div>
                <div>
                    <label class="modal-label">Slug <span style="color:red">*</span></label>
                    <input class="modal-input" type="text" name="slug" id="braSlug"
                        placeholder="samsung" required>
                </div>
                <div>
                    <label class="modal-label">URL Logo</label>
                    <input class="modal-input" type="text" name="logo_url" id="braLogoUrl"
                        placeholder="img/brands/samsung.jpg">
                </div>
                <div>
                    <label class="modal-label">Quốc gia</label>
                    <input class="modal-input" type="text" name="country" id="braCountry"
                        placeholder="VD: Hàn Quốc">
                </div>
                <div style="grid-column:1/-1">
                    <label class="modal-label">Mô tả</label>
                    <textarea class="modal-input" name="description" id="braDescription"
                        placeholder="Mô tả ngắn về thương hiệu..." rows="3"
                        style="resize:vertical"></textarea>
                </div>

                <!-- ── DANH MỤC (Many-to-Many) ── -->
                <div style="grid-column:1/-1">
                    <label class="modal-label">Danh mục áp dụng</label>
                    <div id="braCatCheckboxes"
                         style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;max-height:180px;overflow-y:auto;padding:10px;background:var(--bg,#f8f8ff);border:1.5px solid var(--border,#e5e7eb);border-radius:10px">
                        <?php foreach ($categoriesAll as $cat): ?>
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;color:var(--text)">
                            <input type="checkbox" name="category_ids[]"
                                   value="<?= $cat['id'] ?>"
                                   id="bracat_<?= $cat['id'] ?>"
                                   style="width:15px;height:15px;accent-color:var(--primary,#7c3aed);cursor:pointer">
                            <?= htmlspecialchars($cat['name']) ?>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($categoriesAll)): ?>
                        <span style="font-size:12px;color:var(--muted);grid-column:1/-1">Chưa có danh mục nào</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="grid-column:1/-1">
                    <label class="modal-label">Trạng thái</label>
                    <select class="modal-input" name="is_active" id="braIsActive">
                        <option value="1">Hiển thị</option>
                        <option value="0">Ẩn</option>
                    </select>
                </div>
            </div>
            </div><!-- end scroll wrapper -->
            <!-- Buttons cố định ở dưới -->
            <div style="display:flex;gap:10px;margin-top:16px;flex-shrink:0;padding-top:12px;border-top:1px solid var(--border,#e5e7eb)">
                <button type="button" onclick="closeBraModal()"
                    style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                    Hủy
                </button>
                <button type="button" onclick="submitBraForm()" class="btn-primary" style="flex:1;justify-content:center">
                    <i class="fas fa-check"></i> Lưu thương hiệu
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL XÁC NHẬN XÓA ── -->
<div class="modal-bg" id="braConfirmModal" style="display:none">
    <div class="modal-box" style="max-width:400px">
        <div style="text-align:center;padding:10px 0">
            <div style="font-size:48px;margin-bottom:12px">🗑️</div>
            <h2 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:8px">Xác nhận xóa</h2>
            <p style="font-size:14px;color:var(--muted)" id="braConfirmMsg">Bạn có chắc muốn xóa thương hiệu này?</p>
        </div>
        <input type="hidden" id="braDeleteId" value="">
        <div style="display:flex;gap:10px;margin-top:20px">
            <button onclick="closeBraConfirm()"
                style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                Hủy
            </button>
            <button onclick="submitDeleteBra()"
                style="flex:1;background:#ef4444;border:none;border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:#fff;cursor:pointer;font-family:inherit">
                <i class="fas fa-trash"></i> Xóa
            </button>
        </div>
    </div>
</div>

<script>
    // ── DỮ LIỆU TỪ PHP ──
    const BRA_DATA     = <?= json_encode(array_values($brandsx)) ?>;
    const BRA_CATS_ALL = <?= json_encode(array_values($categoriesAll)) ?>;  // [{id, name}, ...]

    // ── STATE ──
    var braPage    = 1;
    const BRA_PER  = 10;
    var braSortKey = 'id';
    var braSortAsc = true;

    // ── AUTO SLUG ──
    function autoSlugBra() {
        if (document.getElementById('braAction').value !== 'insert') return;
        const name = document.getElementById('braName').value;
        const slug = name.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd').replace(/[^a-z0-9\s-]/g, '')
            .trim().replace(/\s+/g, '-');
        document.getElementById('braSlug').value = slug;
    }

    // ── SORT ──
    function setBraSort(key) {
        if (braSortKey === key) braSortAsc = !braSortAsc;
        else { braSortKey = key; braSortAsc = true; }
        document.querySelectorAll('[id^="brasort-"]').forEach(el => {
            el.className = 'fas fa-sort';
            el.style.color = 'var(--muted)';
        });
        const ic = document.getElementById('brasort-' + key);
        if (ic) {
            ic.className = braSortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
            ic.style.color = 'var(--primary, #7c3aed)';
        }
        braPage = 1;
        renderBrands();
    }

    // ── FILTER ──
    function getFilteredBra() {
        const search    = document.getElementById('braSearch').value.toLowerCase();
        const status    = document.getElementById('braStatusFilter').value;
        const catFilter = document.getElementById('braCatFilter').value;

        return BRA_DATA
            .filter(c => {
                const matchSearch = !search ||
                    (c.name    && c.name.toLowerCase().includes(search))    ||
                    (c.slug    && c.slug.toLowerCase().includes(search))    ||
                    (c.country && c.country.toLowerCase().includes(search));
                const matchStatus = status === '' || String(c.is_active) === status;
                // Lọc theo danh mục: brand phải có category_id trong mảng category_ids
                const matchCat = catFilter === '' ||
                    (c.category_ids && c.category_ids.map(String).includes(catFilter));
                return matchSearch && matchStatus && matchCat;
            })
            .sort((a, b) => {
                let va = a[braSortKey] ?? '';
                let vb = b[braSortKey] ?? '';
                if (braSortKey === 'id') { va = Number(va); vb = Number(vb); }
                else if (typeof va === 'string') { va = va.toLowerCase(); vb = vb.toLowerCase(); }
                if (va < vb) return braSortAsc ? -1 : 1;
                if (va > vb) return braSortAsc ?  1 : -1;
                return 0;
            });
    }

    // ── Helper: tên danh mục theo id ──
    function getCatName(id) {
        const c = BRA_CATS_ALL.find(x => String(x.id) === String(id));
        return c ? c.name : '';
    }

    // ── RENDER ──
    function renderBrands() {
        const filtered   = getFilteredBra();
        const totalPages = Math.max(1, Math.ceil(filtered.length / BRA_PER));
        if (braPage > totalPages) braPage = totalPages;
        const start = (braPage - 1) * BRA_PER;
        const page  = filtered.slice(start, start + BRA_PER);

        document.getElementById('braTableBody').innerHTML = page.map(c => {
            const isActive    = c.is_active == 1;
            const statusBg    = isActive ? '#d1fae5' : '#fef3c7';
            const statusColor = isActive ? '#059669' : '#d97706';
            const statusLabel = isActive ? 'Hiển thị' : 'Ẩn';

const imgHtml = c.logo_url
    ? `<img src="${c.logo_url}" style="width:44px;height:44px;object-fit:contain;border-radius:8px;background:#f5f5f5;padding:4px">`
    : `<span style="color:var(--muted);font-size:12px">—</span>`;

            const descHtml = c.description
                ? `<span title="${c.description.replace(/"/g,'&quot;')}" style="font-size:12px;color:var(--muted);max-width:140px;display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${c.description}</span>`
                : `<span style="color:var(--muted);font-size:12px">—</span>`;

            // Hiển thị danh mục dạng tag
            const catTags = (c.category_ids && c.category_ids.length)
                ? c.category_ids.map(cid =>
                    `<span style="display:inline-block;background:#ede9fe;color:#7c3aed;border-radius:6px;padding:2px 7px;font-size:11px;font-weight:600;margin:2px">${getCatName(cid)}</span>`
                  ).join('')
                : `<span style="color:var(--muted);font-size:12px">—</span>`;

            const createdAt = c.created_at ? c.created_at.substring(0, 10) : '—';
            const updatedAt = c.updated_at ? c.updated_at.substring(0, 10) : '—';

            return `<tr>
                <td><div class="checkbox-custom row-check bra-check" data-id="${c.id}" onclick="toggleBraRow(this)"></div></td>
                <td style="font-size:13px;color:var(--muted);font-weight:600">${c.id}</td>
                <td style="font-weight:600;font-size:14px">${c.name}</td>
                <td>${imgHtml}</td>
                <td style="font-size:12px;color:var(--muted)">${c.slug || '—'}</td>
                <td style="font-size:13px">${c.country || '—'}</td>
                <td style="max-width:180px">${catTags}</td>
                <td>${descHtml}</td>
                <td><span class="badge" style="background:${statusBg};color:${statusColor}">${statusLabel}</span></td>
                <td style="font-size:12px">${createdAt}</td>
                <td style="font-size:12px">${updatedAt}</td>
                <td class="actions-cell">
                    <button class="btn-edit" onclick='openEditBra(${JSON.stringify(c)})'><i class="fas fa-edit"></i> Sửa</button>
                    <button class="btn-del"  onclick="confirmDeleteBra(${c.id},'${c.name.replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i> Xóa</button>
                </td>
            </tr>`;
        }).join('');

        const pagHtml = () => {
            let h = '';
            if (braPage > 1)
                h += `<button class="page-btn" onclick="goToBraPage(${braPage-1})"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
            for (let i = 1; i <= totalPages; i++)
                h += `<button class="page-btn${i===braPage?' active':''}" onclick="goToBraPage(${i})">${i}</button>`;
            if (braPage < totalPages)
                h += `<button class="page-btn" onclick="goToBraPage(${braPage+1})"><i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
            return h;
        };

        document.getElementById('braTopPag').innerHTML  = pagHtml();
        document.getElementById('braBotPag').innerHTML  = pagHtml();
        document.getElementById('braTableInfo').textContent =
            `${start+1} – ${Math.min(start+BRA_PER, filtered.length)} trên ${filtered.length} thương hiệu`;
        document.getElementById('braPageLabel').textContent = `${braPage} / ${totalPages}`;
        updateBraSelLabel();
    }

    // ── MODAL THÊM ──
    function openBraModal() {
        document.getElementById('braModalTitle').textContent = 'Thêm thương hiệu mới';
        document.getElementById('braAction').value      = 'insert';
        document.getElementById('braId').value          = '';
        document.getElementById('braName').value        = '';
        document.getElementById('braSlug').value        = '';
        document.getElementById('braLogoUrl').value     = '';
        document.getElementById('braCountry').value     = '';
        document.getElementById('braDescription').value = '';
        document.getElementById('braIsActive').value    = '1';
        // Bỏ check tất cả checkbox danh mục
        document.querySelectorAll('#braCatCheckboxes input[type="checkbox"]')
            .forEach(cb => cb.checked = false);
        document.getElementById('braModal').style.display = 'flex';
    }

    // ── MODAL SỬA ──
    function openEditBra(c) {
        document.getElementById('braModalTitle').textContent = 'Chỉnh sửa thương hiệu';
        document.getElementById('braAction').value      = 'update';
        document.getElementById('braId').value          = c.id;
        document.getElementById('braName').value        = c.name;
        document.getElementById('braSlug').value        = c.slug        || '';
        document.getElementById('braLogoUrl').value     = c.logo_url    || '';
        document.getElementById('braCountry').value     = c.country     || '';
        document.getElementById('braDescription').value = c.description || '';
        document.getElementById('braIsActive').value    = c.is_active;
        // Tick đúng danh mục đang gán
        const assigned = (c.category_ids || []).map(String);
        document.querySelectorAll('#braCatCheckboxes input[type="checkbox"]').forEach(cb => {
            cb.checked = assigned.includes(String(cb.value));
        });
        document.getElementById('braModal').style.display = 'flex';
    }

    function submitBraForm() {
        const form = document.getElementById('braForm');
        const data = new FormData(form);
        fetch('/MantaMarket/admin/index.php?page=brands', { method: 'POST', body: data })
            .then(() => { closeBraModal(); location.hash = 'brands'; location.reload(); });
    }

    function closeBraModal() {
        document.getElementById('braModal').style.display = 'none';
    }

    // ── XÁC NHẬN XÓA ──
    function confirmDeleteBra(id, name) {
        document.getElementById('braConfirmMsg').textContent = `Bạn có chắc muốn xóa thương hiệu "${name}"?`;
        document.getElementById('braDeleteId').value         = id;
        document.getElementById('braConfirmModal').style.display = 'flex';
    }

    function closeBraConfirm() {
        document.getElementById('braConfirmModal').style.display = 'none';
    }

    function submitDeleteBra() {
        const id   = document.getElementById('braDeleteId').value;
        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);
        fetch('/MantaMarket/admin/index.php?page=brands', { method: 'POST', body: data })
            .then(() => { closeBraConfirm(); location.hash = 'brands'; location.reload(); });
    }

    // ── CHECKBOX ROWS ──
    function toggleBraAll(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        const checked = el.classList.contains('checked');
        document.querySelectorAll('#braTableBody .bra-check').forEach(c => {
            c.className = 'checkbox-custom row-check bra-check' + (checked ? ' checked' : '');
            c.innerHTML = checked ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        });
        updateBraSelLabel();
    }

    function toggleBraRow(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        updateBraSelLabel();
    }

    function updateBraSelLabel() {
        const count = document.querySelectorAll('#braTableBody .bra-check.checked').length;
        const lbl   = document.getElementById('braSelLabel');
        if (lbl) lbl.textContent = count ? `Chọn ${count} thương hiệu` : 'Chọn 0 thương hiệu';
    }

    function getCheckedBraIds() {
        return [...document.querySelectorAll('#braTableBody .bra-check.checked')].map(el => el.dataset.id);
    }

    // ── XÓA NHIỀU ──
    function braBulkDelete() {
        const ids = getCheckedBraIds();
        if (!ids.length) { alert('Vui lòng chọn thương hiệu cần xóa'); return; }
        if (!confirm(`Bạn có chắc muốn xóa ${ids.length} thương hiệu đã chọn?`)) return;
        const data = new FormData();
        data.append('action', 'bulk_delete');
        ids.forEach(id => data.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=brands', { method: 'POST', body: data })
            .then(() => { location.hash = 'brands'; location.reload(); });
    }

    // ── ẨN/HIỆN NHIỀU ──
    function braBulkToggle(status) {
        const ids = getCheckedBraIds();
        if (!ids.length) { alert('Vui lòng chọn thương hiệu'); return; }
        if (!confirm(`${status==1?'Hiện':'Ẩn'} ${ids.length} thương hiệu đã chọn?`)) return;
        const data = new FormData();
        data.append('action', 'bulk_toggle');
        data.append('is_active', status);
        ids.forEach(id => data.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=brands', { method: 'POST', body: data })
            .then(() => { location.hash = 'brands'; location.reload(); });
    }

    // ── ĐÓNG MODAL KHI CLICK NGOÀI ──
    ['braModal', 'braConfirmModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    });

    function goToBraPage(p) { braPage = p; renderBrands(); }

    document.addEventListener('DOMContentLoaded', function() {
        renderBrands();
    });
</script>