<?php
$user    = new app_Libs_UserIdentity();
$router  = new app_Libs_Router();
$nguoiDung = new app_Models_Users();
$db      = new app_Libs_DbConnection();
$action  = $router->getPOST("action");
$id      = intval($router->getPOST("id") ?? $router->getGET("id"));

// ── XÓA 1 NGƯỜI DÙNG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $nguoiDung->buildQueryParams([
        "where"  => "id = :id",
        "params" => [":id" => $id]
    ])->delete();
    header("Location: /MantaMarket/admin/index.php?page=users");
    exit();
}
// ── XÓA NHIỀU NGƯỜI DÙNG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    foreach ($ids as $uid) {
        $uid = intval($uid);
        if ($uid) {
            $nguoiDung->buildQueryParams([
                "where"  => "id = :id",
                "params" => [":id" => $uid]
            ])->delete();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=users");
    exit();
}
// ── ẨN/HIỆN NHIỀU NGƯỜI DÙNG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_toggle') {
    $ids    = $_POST['ids'] ?? [];
    $status = intval($_POST['is_active'] ?? 1);
    foreach ($ids as $uid) {
        $uid = intval($uid);
        if ($uid) {
            $nguoiDung->buildQueryParams([
                "value"  => "is_active = :is_active",
                "where"  => "id = :id",
                "params" => [":is_active" => $status, ":id" => $uid]
            ])->update();
        }
    }
    header("Location: /MantaMarket/admin/index.php?page=users");
    exit();
}
// ── THÊM NGƯỜI DÙNG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'insert') {
    $username  = trim($router->getPOST("username"));
    $email     = trim($router->getPOST("email"));
    $phone     = trim($router->getPOST("phone"));
    $full_name = trim($router->getPOST("full_name"));
    $avatar    = trim($router->getPOST("avatar"));
    $role      = trim($router->getPOST("role") ?? 'user');
    $is_active = intval($router->getPOST("is_active") ?? 1);
    $password  = trim($router->getPOST("password"));
    $provider    = trim($router->getPOST("provider") ?? 'local');
    $provider_id = trim($router->getPOST("provider_id") ?? '');

    // Kiểm tra email trùng
    $checkEmail = $nguoiDung->buildQueryParams([
        "where"  => "email = :email",
        "params" => [":email" => $email]
    ])->selectOne();
    if ($checkEmail) {
        header("Location: /MantaMarket/admin/index.php?page=users&error=email");
        exit();
    }
    $nguoiDung->buildQueryParams([
        "field" => "(username, email, phone, full_name, avatar, password, provider, provider_id, role, is_active) VALUES (:username, :email, :phone, :full_name, :avatar, :password, :provider, :provider_id, :role, :is_active)",
        "value" => [
            ":username"    => $username,
            ":email"       => $email,
            ":phone"       => $phone,
            ":full_name"   => $full_name,
            ":avatar"      => $avatar,
            ":password"    => $password,
            ":provider"    => $provider,
            ":provider_id" => $provider_id,
            ":role"        => $role,
            ":is_active"   => $is_active
        ]
    ])->insert();
    header("Location: /MantaMarket/admin/index.php?page=users");
    exit();
}
// ── CẬP NHẬT NGƯỜI DÙNG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $id) {
    $username  = trim($router->getPOST("username"));
    $email     = trim($router->getPOST("email"));
    $phone     = trim($router->getPOST("phone"));
    $full_name = trim($router->getPOST("full_name"));
    $avatar    = trim($router->getPOST("avatar"));
    $role      = trim($router->getPOST("role") ?? 'user');
    $is_active = intval($router->getPOST("is_active") ?? 1);
    $newPass   = trim($router->getPOST("password"));
    $provider    = trim($router->getPOST("provider") ?? 'local');
    $provider_id = trim($router->getPOST("provider_id") ?? '');

    // Kiểm tra email trùng (trừ chính user đó)
    $checkEmail = $nguoiDung->buildQueryParams([
        "where"  => "email = :email AND id != :id",
        "params" => [":email" => $email, ":id" => $id]
    ])->selectOne();
    if ($checkEmail) {
        header("Location: /MantaMarket/admin/index.php?page=users&error=email");
        exit();
    }

    $params = [
        ":username"    => $username,
        ":email"       => $email,
        ":phone"       => $phone,
        ":full_name"   => $full_name,
        ":avatar"      => $avatar,
        ":provider"    => $provider,
        ":provider_id" => $provider_id,
        ":role"        => $role,
        ":is_active"   => $is_active,
        ":id"          => $id
    ];
    $valueStr = "username = :username, email = :email, phone = :phone, full_name = :full_name, avatar = :avatar, provider = :provider, provider_id = :provider_id, role = :role, is_active = :is_active";

    // Chỉ cập nhật mật khẩu nếu nhập mới
    if ($newPass !== '') {
        $valueStr .= ", password = :password";
        $params[":password"] = $newPass;
    }

    $nguoiDung->buildQueryParams([
        "value"  => $valueStr,
        "where"  => "id = :id",
        "params" => $params
    ])->update();
    header("Location: /MantaMarket/admin/index.php?page=users");
    exit();
}
// ── LẤY DỮ LIỆU ──
$nguoiDungList = $nguoiDung->buildQueryParams([])->select();
$totalAll      = count($nguoiDungList);
$totalActive   = count(array_filter($nguoiDungList, fn($r) => $r['is_active'] == 1));
$totalInactive = $totalAll - $totalActive;
$totalAdmin    = count(array_filter($nguoiDungList, fn($r) => $r['role'] === 'admin'));
?>
<div class="page" id="page-users">
    <!-- HEADER -->
    <div class="page-header">
        <h1 class="page-title">Quản lý người dùng</h1>
        <!-- STAT CARDS -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div class="stat-card">
                <div class="stat-label">Tổng người dùng</div>
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
                <div class="stat-label">Admin</div>
                <div class="stat-value"><?= $totalAdmin ?></div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'email'): ?>
        <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 18px;margin-bottom:14px;color:#dc2626;font-size:14px;font-weight:600;">
            <i class="fas fa-exclamation-circle"></i> Email đã tồn tại, vui lòng dùng email khác!
        </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="order-filter-bar" style="flex-wrap:wrap;gap:8px">
        <!-- Chọn tất cả -->
        <div class="order-filter-select" style="gap:6px">
            <div class="checkbox-custom" id="userCheckAll" onclick="toggleUserAll(this)" style="width:14px;height:14px"></div>
            <span style="font-size:13px;color:var(--muted)">Chọn tất cả</span>
        </div>
        <!-- Lọc trạng thái -->
        <div class="order-filter-select2">
            <select id="userStatusFilter" onchange="userPage=1;renderUser()">
                <option value="">Tất cả trạng thái</option>
                <option value="1">Hoạt động</option>
                <option value="0">Đã khóa</option>
            </select>
        </div>
        <!-- Lọc role -->
        <div class="order-filter-select2">
            <select id="userRoleFilter" onchange="userPage=1;renderUser()">
                <option value="">Tất cả vai trò</option>
                <option value="admin">Admin</option>
                <option value="nhanvien">Nhân viên</option>
                <option value="user">User</option>
            </select>
        </div>
        <!-- Tìm kiếm -->
        <div class="order-search-wrap" style="flex:1;min-width:180px">
            <i class="fas fa-search"></i>
            <input class="order-search-input" type="text" id="userSearch"
                placeholder="Tìm theo tên, email, SĐT..." oninput="userPage=1;renderUser()">
        </div>
        <!-- Thêm người dùng -->
        <div class="page-actions">
            <button class="btn-primary" onclick="openUserModal()">
                <i class="fas fa-plus"></i> Thêm người dùng
            </button>
        </div>
        <!-- Nút xóa nhiều -->
        <button class="btn-action" onclick="userBulkDelete()" title="Xóa đã chọn">
            <i class="fas fa-trash"></i> Xóa
        </button>
        <!-- Nút ẩn/hiện nhiều -->
        <button class="btn-action" onclick="userBulkToggle(1)" title="Mở khóa đã chọn">
            <i class="fas fa-unlock"></i>
        </button>
        <button class="btn-action" onclick="userBulkToggle(0)" title="Khóa đã chọn">
            <i class="fas fa-lock"></i>
        </button>
    </div>

    <!-- TABLE -->
    <div class="order-table-card">
        <div class="order-table-head">
            <span style="font-size:13px;color:var(--muted)" id="userSelLabel">Thao tác</span>
            <div class="pagination" id="userTopPag"></div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th onclick="setUserSort('id')" style="cursor:pointer">
                            ID <i class="fas fa-sort" id="sort-uid" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Avatar</th>
                        <th onclick="setUserSort('full_name')" style="cursor:pointer">
                            Họ tên <i class="fas fa-sort" id="sort-full_name" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setUserSort('username')" style="cursor:pointer">
                            Username <i class="fas fa-sort" id="sort-username" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th onclick="setUserSort('email')" style="cursor:pointer">
                            Email <i class="fas fa-sort" id="sort-email" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Điện thoại</th>
                        <th onclick="setUserSort('provider')" style="cursor:pointer">
                            Provider <i class="fas fa-sort" id="sort-provider" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Provider ID</th>
                        <th onclick="setUserSort('role')" style="cursor:pointer">
                            Vai trò <i class="fas fa-sort" id="sort-role" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Trạng thái</th>
                        <th onclick="setUserSort('created_at')" style="cursor:pointer">
                            Ngày tạo <i class="fas fa-sort" id="sort-created_at" style="font-size:10px;color:var(--muted)"></i>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="userTableBody"></tbody>
            </table>
        </div>
        <div class="order-table-footer">
            <div style="font-size:13px;color:var(--muted)" id="userTableInfo"></div>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="pagination" id="userBotPag"></div>
                <span style="font-size:13px;color:var(--muted)" id="userPageLabel"></span>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL THÊM / SỬA ── -->
<div class="modal-bg" id="userModal" style="display:none">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
            <h2 style="font-size:20px;font-weight:800;color:var(--text)" id="userModalTitle">Thêm người dùng</h2>
            <button onclick="closeUserModal()" style="background:none;border:none;font-size:20px;color:var(--muted);cursor:pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="userForm" method="POST" action="">
            <input type="hidden" name="action" id="userAction" value="insert">
            <input type="hidden" name="id" id="userId" value="">
            <div style="display:grid;gap:14px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="modal-label">Họ và tên</label>
                        <input class="modal-input" type="text" name="full_name" id="userFullName"
                            placeholder="Nguyễn Văn A">
                    </div>
                    <div>
                        <label class="modal-label">Username</label>
                        <input class="modal-input" type="text" name="username" id="userUsername"
                            placeholder="nguyenvana">
                    </div>
                </div>
                <div>
                    <label class="modal-label">Email <span style="color:red">*</span></label>
                    <input class="modal-input" type="email" name="email" id="userEmail"
                        placeholder="example@email.com" required>
                </div>
                <div>
                    <label class="modal-label">Số điện thoại</label>
                    <input class="modal-input" type="text" name="phone" id="userPhone"
                        placeholder="0912345678">
                </div>
                <div>
                    <label class="modal-label">Link avatar</label>
                    <input class="modal-input" type="text" name="avatar" id="userAvatar"
                        placeholder="img/avatar/user.jpg">
                </div>
                <div>
                    <label class="modal-label" id="userPassLabel">Mật khẩu <span style="color:red">*</span></label>
                    <input class="modal-input" type="password" name="password" id="userPassword"
                        placeholder="Nhập mật khẩu">
                    <span id="userPassHint" style="font-size:11px;color:var(--muted);display:none">
                        Để trống nếu không muốn thay đổi mật khẩu
                    </span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="modal-label">Provider</label>
                        <select class="modal-input" name="provider" id="userProvider">
                            <option value="local">Local</option>
                            <option value="facebook">Facebook</option>
                            <option value="google">Google</option>
                        </select>
                    </div>
                    <div>
                        <label class="modal-label">Provider ID</label>
                        <input class="modal-input" type="text" name="provider_id" id="userProviderId"
                            placeholder="(OAuth ID)">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label class="modal-label">Vai trò</label>
                        <select class="modal-input" name="role" id="userRole">
                            <option value="user">User</option>
                            <option value="nhanvien">Nhân viên</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="modal-label">Trạng thái</label>
                        <select class="modal-input" name="is_active" id="userIsActive">
                            <option value="1">Hoạt động</option>
                            <option value="0">Khóa</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px">
                <button type="button" onclick="closeUserModal()"
                    style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                    Hủy
                </button>
                <button type="button" onclick="submitUserForm()" class="btn-primary" style="flex:1;justify-content:center">
                    <i class="fas fa-check"></i> Lưu người dùng
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL XÁC NHẬN XÓA ── -->
<div class="modal-bg" id="userConfirmModal" style="display:none">
    <div class="modal-box" style="max-width:400px">
        <div style="text-align:center;padding:10px 0">
            <div style="font-size:48px;margin-bottom:12px">🗑️</div>
            <h2 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:8px">
                Xác nhận xóa
            </h2>
            <p style="font-size:14px;color:var(--muted)" id="userConfirmMsg">
                Bạn có chắc muốn xóa người dùng này?
            </p>
        </div>
        <input type="hidden" id="userDeleteId" value="">
        <div style="display:flex;gap:10px;margin-top:20px">
            <button onclick="closeUserConfirm()"
                style="flex:1;background:#f4f4fb;border:1.5px solid var(--border);border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:var(--muted);cursor:pointer;font-family:inherit">
                Hủy
            </button>
            <button onclick="submitDeleteUser()"
                style="flex:1;background:#ef4444;border:none;border-radius:10px;padding:10px;font-size:14px;font-weight:600;color:#fff;cursor:pointer;font-family:inherit">
                <i class="fas fa-trash"></i> Xóa
            </button>
        </div>
    </div>
</div>

<script>
    // ── DỮ LIỆU TỪ PHP ──
    const USER_DATA = <?= json_encode(array_values($nguoiDungList)) ?>;
    // ── STATE ──
    var userPage = 1;
    const USER_PER = 10;
    var userSortKey = 'id';
    var userSortAsc = true;

    // ── SORT ──
    function setUserSort(key) {
        const keyMap = {
            'id': 'uid',
            'full_name': 'full_name',
            'username': 'username',
            'email': 'email',
            'role': 'role',
            'created_at': 'created_at'
        };
        if (userSortKey === key) userSortAsc = !userSortAsc;
        else {
            userSortKey = key;
            userSortAsc = true;
        }
        document.querySelectorAll('[id^="sort-"]').forEach(el => {
            el.className = 'fas fa-sort';
            el.style.color = 'var(--muted)';
        });
        const ic = document.getElementById('sort-' + (keyMap[key] || key));
        if (ic) {
            ic.className = userSortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
            ic.style.color = 'var(--primary, #7c3aed)';
        }
        userPage = 1;
        renderUser();
    }

    // ── FILTER ──
    function getFilteredUsers() {
        const search = document.getElementById('userSearch').value.toLowerCase();
        const status = document.getElementById('userStatusFilter').value;
        const role = document.getElementById('userRoleFilter').value;
        return USER_DATA
            .filter(u => {
                const matchSearch = !search ||
                    (u.full_name && u.full_name.toLowerCase().includes(search)) ||
                    (u.username && u.username.toLowerCase().includes(search)) ||
                    (u.email && u.email.toLowerCase().includes(search)) ||
                    (u.phone && u.phone.includes(search));
                const matchStatus = status === '' || String(u.is_active) === status;
                const matchRole = role === '' || u.role === role;
                return matchSearch && matchStatus && matchRole;
            })
            .sort((a, b) => {
                let va = a[userSortKey] ?? '';
                let vb = b[userSortKey] ?? '';
                if (userSortKey === 'id') {
                    va = Number(va);
                    vb = Number(vb);
                } else if (typeof va === 'string') {
                    va = va.toLowerCase();
                    vb = vb.toLowerCase();
                }
                if (va < vb) return userSortAsc ? -1 : 1;
                if (va > vb) return userSortAsc ? 1 : -1;
                return 0;
            });
    }

    // ── RENDER ──
    function renderUser() {
        const filtered = getFilteredUsers();
        const totalPages = Math.max(1, Math.ceil(filtered.length / USER_PER));
        if (userPage > totalPages) userPage = totalPages;
        const start = (userPage - 1) * USER_PER;
        const page = filtered.slice(start, start + USER_PER);

        document.getElementById('userTableBody').innerHTML = page.map(u => {
            // Trạng thái
            const isActive = u.is_active == 1;
            const statusBg = isActive ? '#d1fae5' : '#fee2e2';
            const statusColor = isActive ? '#059669' : '#dc2626';
            const statusLabel = isActive ? 'Hoạt động' : 'Đã khóa';

            // Role badge
            const roleCfg = {
                admin: {
                    bg: '#ede9fe',
                    color: '#7c3aed',
                    label: 'Admin'
                },
                nhanvien: {
                    bg: '#dbeafe',
                    color: '#2563eb',
                    label: 'Nhân viên'
                },
                user: {
                    bg: '#f3f4f6',
                    color: '#6b7280',
                    label: 'User'
                }
            };
            const rc = roleCfg[u.role] || roleCfg['user'];

            // Avatar
            const avatarHtml = u.avatar ?
                `<img src="${u.avatar}" style="width:38px;height:38px;object-fit:cover;border-radius:50%;border:2px solid var(--border)">` :
                `<div style="width:38px;height:38px;border-radius:50%;background:var(--primary,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;font-weight:700">
                    ${(u.full_name || u.username || '?')[0].toUpperCase()}
                   </div>`;

            const createdAt = u.created_at ? u.created_at.substring(0, 10) : '—';

            // Ẩn password trước khi truyền vào JS
            const safeU = Object.assign({}, u, {
                password: ''
            });

            return `<tr>
                <td><div class="checkbox-custom row-check user-check" data-id="${u.id}" onclick="toggleUserRow(this)"></div></td>
                <td style="font-size:13px;color:var(--muted);font-weight:600">${u.id}</td>
                <td>${avatarHtml}</td>
                <td style="font-weight:600;font-size:14px">${u.full_name || '—'}</td>
                <td style="font-size:13px;color:var(--muted)">${u.username || '—'}</td>
                <td style="font-size:13px">${u.email || '—'}</td>
                <td style="font-size:13px">${u.phone || '—'}</td>
                <td>
                    ${u.provider === 'google'   ? `<span class="badge" style="background:#fef9c3;color:#ca8a04"><i class="fab fa-google" style="margin-right:4px"></i>Google</span>` :
                      u.provider === 'facebook' ? `<span class="badge" style="background:#dbeafe;color:#1d4ed8"><i class="fab fa-facebook" style="margin-right:4px"></i>Facebook</span>` :
                      `<span class="badge" style="background:#f3f4f6;color:#374151"><i class="fas fa-user" style="margin-right:4px"></i>Local</span>`}
                </td>
                <td style="font-size:11px;color:var(--muted);max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${u.provider_id || ''}">${u.provider_id ? u.provider_id.substring(0,14)+'…' : '—'}</td>
                <td><span class="badge" style="background:${rc.bg};color:${rc.color}">${rc.label}</span></td>
                <td><span class="badge" style="background:${statusBg};color:${statusColor}">${statusLabel}</span></td>
                <td style="font-size:12px">${createdAt}</td>
                <td class="actions-cell">
                    <button class="btn-edit" onclick='openEditUser(${JSON.stringify(safeU)})'><i class="fas fa-edit"></i> Sửa</button>
                    <button class="btn-del"  onclick="confirmDeleteUser(${u.id},'${(u.full_name||u.email||'').replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i> Xóa</button>
                </td>
            </tr>`;
        }).join('');

        // Pagination
        const pagHtml = () => {
            let h = '';
            if (userPage > 1) h += `<button class="page-btn" onclick="goToUserPage(${userPage-1})"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
            for (let i = 1; i <= totalPages; i++)
                h += `<button class="page-btn${i===userPage?' active':''}" onclick="goToUserPage(${i})">${i}</button>`;
            if (userPage < totalPages) h += `<button class="page-btn" onclick="goToUserPage(${userPage+1})"><i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
            return h;
        };
        document.getElementById('userTopPag').innerHTML = pagHtml();
        document.getElementById('userBotPag').innerHTML = pagHtml();
        document.getElementById('userTableInfo').textContent =
            `${start+1} – ${Math.min(start+USER_PER, filtered.length)} trên ${filtered.length} người dùng`;
        document.getElementById('userPageLabel').textContent = `${userPage} / ${totalPages}`;
        updateUserSelLabel();
    }

    // ── MODAL THÊM/SỬA ──
    function openUserModal() {
        document.getElementById('userModalTitle').textContent = 'Thêm người dùng mới';
        document.getElementById('userAction').value = 'insert';
        document.getElementById('userId').value = '';
        document.getElementById('userFullName').value = '';
        document.getElementById('userUsername').value = '';
        document.getElementById('userEmail').value = '';
        document.getElementById('userPhone').value = '';
        document.getElementById('userAvatar').value = '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userRole').value = 'user';
        document.getElementById('userIsActive').value = '1';
        document.getElementById('userProvider').value = 'local';
        document.getElementById('userProviderId').value = '';
        document.getElementById('userPassLabel').innerHTML = 'Mật khẩu <span style="color:red">*</span>';
        document.getElementById('userPassHint').style.display = 'none';
        document.getElementById('userPassword').required = true;
        document.getElementById('userModal').style.display = 'flex';
    }

    function openEditUser(u) {
        document.getElementById('userModalTitle').textContent = 'Chỉnh sửa người dùng';
        document.getElementById('userAction').value = 'update';
        document.getElementById('userId').value = u.id;
        document.getElementById('userFullName').value = u.full_name || '';
        document.getElementById('userUsername').value = u.username || '';
        document.getElementById('userEmail').value = u.email || '';
        document.getElementById('userPhone').value = u.phone || '';
        document.getElementById('userAvatar').value = u.avatar || '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userRole').value = u.role || 'user';
        document.getElementById('userIsActive').value = u.is_active;
        document.getElementById('userProvider').value = u.provider || 'local';
        document.getElementById('userProviderId').value = u.provider_id || '';
        document.getElementById('userPassLabel').innerHTML = 'Mật khẩu mới';
        document.getElementById('userPassHint').style.display = 'block';
        document.getElementById('userPassword').required = false;
        document.getElementById('userModal').style.display = 'flex';
    }

    function submitUserForm() {
        const form = document.getElementById('userForm');
        const data = new FormData(form);
        fetch('/MantaMarket/admin/index.php?page=users', {
                method: 'POST',
                body: data,
                redirect: 'follow'
            })
            .then(res => {
                // Kiểm tra nếu PHP redirect về trang lỗi
                if (res.url && res.url.includes('error=email')) {
                    alert('Email đã tồn tại, vui lòng dùng email khác!');
                    return;
                }
                closeUserModal();
                location.hash = 'users';
                location.reload();
            })
            .catch(err => {
                alert('Có lỗi xảy ra, vui lòng thử lại!');
                console.error(err);
            });
    }

    function closeUserModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    // ── XÁC NHẬN XÓA ──
    function confirmDeleteUser(id, name) {
        document.getElementById('userConfirmMsg').textContent = `Bạn có chắc muốn xóa người dùng "${name}"?`;
        document.getElementById('userDeleteId').value = id;
        document.getElementById('userConfirmModal').style.display = 'flex';
    }

    function closeUserConfirm() {
        document.getElementById('userConfirmModal').style.display = 'none';
    }

    function submitDeleteUser() {
        const id = document.getElementById('userDeleteId').value;
        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);
        fetch('/MantaMarket/admin/index.php?page=users', {
                method: 'POST',
                body: data
            })
            .then(() => {
                closeUserConfirm();
                location.hash = 'users';
                location.reload();
            });
    }

    // ── CHECKBOX ──
    function toggleUserAll(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        const checked = el.classList.contains('checked');
        document.querySelectorAll('#userTableBody .user-check').forEach(c => {
            c.className = 'checkbox-custom row-check user-check' + (checked ? ' checked' : '');
            c.innerHTML = checked ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        });
        updateUserSelLabel();
    }

    function toggleUserRow(el) {
        el.classList.toggle('checked');
        el.innerHTML = el.classList.contains('checked') ? '<i class="fas fa-check" style="font-size:9px"></i>' : '';
        updateUserSelLabel();
    }

    function updateUserSelLabel() {
        const count = document.querySelectorAll('#userTableBody .user-check.checked').length;
        const lbl = document.getElementById('userSelLabel');
        if (lbl) lbl.textContent = count ? `Chọn ${count} người dùng` : 'Chọn 0 người dùng';
    }

    function getCheckedUserIds() {
        return [...document.querySelectorAll('#userTableBody .user-check.checked')]
            .map(el => el.dataset.id);
    }

    // ── XÓA NHIỀU ──
    function userBulkDelete() {
        const ids = getCheckedUserIds();
        if (!ids.length) {
            alert('Vui lòng chọn người dùng cần xóa');
            return;
        }
        if (!confirm(`Bạn có chắc muốn xóa ${ids.length} người dùng đã chọn?`)) return;
        const data = new FormData();
        data.append('action', 'bulk_delete');
        ids.forEach(id => data.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=users', {
                method: 'POST',
                body: data
            })
            .then(() => {
                location.hash = 'users';
                location.reload();
            });
    }

    // ── ẨN/HIỆN NHIỀU ──
    function userBulkToggle(status) {
        const ids = getCheckedUserIds();
        if (!ids.length) {
            alert('Vui lòng chọn người dùng');
            return;
        }
        if (!confirm(`${status==1?'Mở khóa':'Khóa'} ${ids.length} người dùng đã chọn?`)) return;
        const data = new FormData();
        data.append('action', 'bulk_toggle');
        data.append('is_active', status);
        ids.forEach(id => data.append('ids[]', id));
        fetch('/MantaMarket/admin/index.php?page=users', {
                method: 'POST',
                body: data
            })
            .then(() => {
                location.hash = 'users';
                location.reload();
            });
    }

    // ── ĐÓNG MODAL KHI CLICK NGOÀI ──
    ['userModal', 'userConfirmModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    });

    function goToUserPage(p) {
        userPage = p;
        renderUser();
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof renderUser === 'function') renderUser();
    });
</script>