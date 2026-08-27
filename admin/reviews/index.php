<?php
$user    = new app_Libs_UserIdentity();
$router  = new app_Libs_Router();
$reviews = new app_Models_Reviews();
$db      = new app_Libs_DbConnection();
$action  = $router->getPOST("action");
$id      = intval($router->getPOST("id") ?? $router->getGET("id"));
// ── GỬI / CẬP NHẬT PHẢN HỒI SELLER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reply' && $id) {
    $reply = trim($router->getPOST("reply"));
    if ($reply !== '') {
        $reviews->buildQueryParams([
            "value"  => "reply = :reply, replied_at = NOW(), is_approved = 1",
            "where"  => "id = :id",
            "params" => [":reply" => $reply, ":id" => $id]
        ])->update();
    }
    // Nếu AJAX thì trả JSON, không redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
    header("Location: /MantaMarket/admin/index.php#reviews");
    exit();
}
// ── XÓA 1 REVIEW ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    $reviews->buildQueryParams([
        "where"  => "id = :id",
        "params" => [":id" => $id]
    ])->delete();
    header("Location: /MantaMarket/admin/index.php#reviews");
    exit();
}

// ── XÓA NHIỀU REVIEW ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $reviews->buildQueryParams([
                "where"  => "id = :id",
                "params" => [":id" => $did]
            ])->delete();
        }
    }
    header("Location: /MantaMarket/admin/index.php#reviews");
    exit();
}

// ── DUYỆT/ẨN NHIỀU REVIEW ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_toggle') {
    $ids    = $_POST['ids'] ?? [];
    $status = intval($_POST['is_approved'] ?? 1);
    foreach ($ids as $did) {
        $did = intval($did);
        if ($did) {
            $reviews->buildQueryParams([
                "value"  => "is_approved = :is_approved",
                "where"  => "id = :id",
                "params" => [":is_approved" => $status, ":id" => $did]
            ])->update();
        }
    }
    header("Location: /MantaMarket/admin/index.php#reviews");
    exit();
}

// ── GỬI / CẬP NHẬT PHẢN HỒI SELLER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reply' && $id) {
    $reply = trim($router->getPOST("reply"));
    if ($reply !== '') {
        $reviews->buildQueryParams([
            "value"  => "reply = :reply, replied_at = NOW(), is_approved = 1",
            "where"  => "id = :id",
            "params" => [":reply" => $reply, ":id" => $id]
        ])->update();
    }
    header("Location: /MantaMarket/admin/index.php#reviews");
    exit();
}

// ── LẤY DỮ LIỆU TỪ DATABASE ──
try {
$reviewRows = $db->query(
    "SELECT r.*,
            u.full_name  AS user_name,
            u.avatar     AS user_avatar,
            p.name       AS product_name
     FROM   reviews r
     LEFT JOIN users    u ON u.id = r.user_id
     LEFT JOIN products p ON p.id = r.product_id
     ORDER  BY r.created_at DESC"
)->fetchAll();
} catch (Exception $e) {
    $reviewRows = [];
}

$totalAll     = count($reviewRows);
$totalPending = count(array_filter($reviewRows, fn($r) => $r['reply'] === null || $r['reply'] === ''));
$totalReplied = $totalAll - $totalPending;
$avgRating    = $totalAll
    ? round(array_sum(array_column($reviewRows, 'rating')) / $totalAll, 1)
    : 0;

// ── CHUẨN BỊ JSON RIÊNG (tránh PHP parse lỗi trong <script>) ──
$feedbacksJson = json_encode(
    array_values(array_map(function ($r) {
        $name    = $r['user_name'] ?? 'Ẩn danh';
        $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        return [
            'id'          => (int)$r['id'],
            'name'        => $name,
            'initials'    => $initial,
            'avatar_url'  => $r['user_avatar'] ?? null,
            'product'     => $r['product_name'] ?? '',
            'rating'      => (int)$r['rating'],
            'comment'     => $r['content'] ?? '',
            'title'       => $r['title'] ?? '',
            'date'        => date('d/m/Y', strtotime($r['created_at'])),
            'status'      => ($r['reply'] !== null && $r['reply'] !== '') ? 'replied' : 'pending',
            'reply'       => $r['reply'] ?? '',
            'is_approved' => (int)$r['is_approved'],
            'is_verified' => (int)$r['is_verified_purchase'],
            'replied_at'  => $r['replied_at'] ?? null,
'created_at'  => $r['created_at'] ?? null, 
        ];
    }, $reviewRows)),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>

<div class="page" id="page-reviews">
    <div class="page-header">
        <h1 class="page-title">Quản lý phản hồi khách hàng</h1>
        <div class="page-actions">
            <button class="btn-action" onclick="fbBulkDelete()"><i class="fas fa-trash"></i> Xóa</button>
            <button class="btn-action" onclick="renderFeedback()"><i class="fas fa-sync"></i></button>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid" style="margin-bottom:20px">
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-comment-alt"></i></div>
            <div class="stat-label">Tổng phản hồi</div>
            <div class="stat-value"><?= $totalAll ?></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label">Chưa phản hồi</div>
            <div class="stat-value"><?= $totalPending ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-label">Đánh giá TB</div>
            <div class="stat-value"><?= $avgRating ?></div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Đã phản hồi</div>
            <div class="stat-value"><?= $totalReplied ?></div>
        </div>
    </div>

    <!-- FILTER TABS + SEARCH -->
    <div class="fb-filter-bar">

        <div class="fb-tab-group">
            <div class="fb-sort-group" style="display:flex;gap:6px;align-items:center">
    <span style="font-size:13px;color:var(--muted)">Sắp xếp:</span>
    <button class="fb-tab active" id="sort-review" 
            onclick="fbSetSort(this,'newest_review')">
        <i class="fas fa-clock"></i> Đánh giá mới nhất
    </button>
    <button class="fb-tab" id="sort-reply" 
            onclick="fbSetSort(this,'newest_reply')">
        <i class="fas fa-reply"></i> Phản hồi mới nhất
    </button>
</div>
            <button class="fb-tab active" onclick="fbSetTab(this,'all')">
                Tất cả <span class="fb-tab-count" id="fb-count-all"></span>
            </button>
            <button class="fb-tab" onclick="fbSetTab(this,'pending')">
                Chưa phản hồi <span class="fb-tab-count pending" id="fb-count-pending"></span>
            </button>
            <button class="fb-tab" onclick="fbSetTab(this,'replied')">
                Đã phản hồi <span class="fb-tab-count replied" id="fb-count-replied"></span>
            </button>
        </div>
        <div class="fb-search-wrap">
            <i class="fas fa-search"></i>
            <input class="fb-search-input" type="text" id="fbSearch"
                   placeholder="Tìm kiếm theo tên, sản phẩm..."
                   oninput="fbCurrentPage=1;renderFeedback()">
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="fb-table-card">
        <div class="fb-table-head">
            <div class="select-all-row">
                <div class="checkbox-custom" id="fbCheckAll" onclick="fbToggleAll(this)"></div>
                <span style="font-size:13px;color:var(--muted)">Chọn tất cả</span>
                <span style="font-size:13px;color:var(--muted);margin-left:4px" id="fbSelLabel"></span>
            </div>
            <div class="pagination" id="fbTopPag"></div>
        </div>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Khách hàng</th>
                        <th>Sản phẩm</th>
                        <th>Đánh giá</th>
                        <th>Ngày gửi</th>
                        <th>Ngày phản hồi</th>
                        <th>Trạng thái</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody id="fbTableBody"></tbody>
            </table>
        </div>
        <div class="fb-table-footer">
            <div class="table-info" id="fbTableInfo"></div>
            <div style="display:flex;align-items:center;gap:10px">
                <div class="pagination" id="fbBotPag"></div>
                <span style="font-size:13px;color:var(--muted)" id="fbPageLabel"></span>
            </div>
        </div>
    </div>
</div>

<!-- REPLY MODAL -->
<div class="fb-modal-bg" id="fbModalBg" style="display:none">
    <div class="fb-modal-box">
        <div class="fb-modal-header">
            <div class="fb-modal-title" id="fbModalTitle">Phản hồi khách hàng</div>
            <button class="fb-modal-close" onclick="closeFbModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="fb-modal-body">
            <div class="fb-review-bubble" id="fbReviewBubble"></div>
            <div class="fb-reply-section">
                <label><i class="fas fa-bolt" style="color:var(--purple1);margin-right:6px"></i>Phản hồi nhanh</label>
                <div class="fb-quick-replies">
                    <button class="fb-quick-btn" onclick="setQuickReply('Cảm ơn bạn đã phản hồi! Chúng tôi rất trân trọng ý kiến của bạn.')">Cảm ơn</button>
                    <button class="fb-quick-btn" onclick="setQuickReply('Xin lỗi vì sự bất tiện này! Chúng tôi sẽ xử lý và liên hệ lại sớm nhất.')">Xin lỗi</button>
                    <button class="fb-quick-btn" onclick="setQuickReply('Chúng tôi đã ghi nhận phản hồi và sẽ cải thiện chất lượng dịch vụ.')">Ghi nhận</button>
                    <button class="fb-quick-btn" onclick="setQuickReply('Cảm ơn bạn đã tin tưởng và ủng hộ shop. Hẹn gặp lại bạn lần sau!')">Hẹn gặp lại</button>
                </div>
                <label style="margin-top:4px"><i class="fas fa-pen" style="color:var(--purple1);margin-right:6px"></i>Nội dung phản hồi</label>
                <textarea class="fb-reply-textarea" id="fbReplyText" placeholder="Nhập nội dung phản hồi..."></textarea>
            </div>
        </div>
        <div class="fb-modal-footer">
            <button class="fb-btn-cancel" onclick="closeFbModal()">Hủy</button>
            <button class="fb-btn-submit" onclick="submitFbReply()">
                <i class="fas fa-paper-plane"></i> Gửi phản hồi
            </button>
        </div>
    </div>
</div>

<script>
const feedbacks = <?= $feedbacksJson ?>;

const fbAvatarColors = [
    'linear-gradient(135deg,#7c3aed,#a855f7)',
    'linear-gradient(135deg,#ec4899,#f472b6)',
    'linear-gradient(135deg,#0891b2,#06b6d4)',
    'linear-gradient(135deg,#f59e0b,#fb923c)',
    'linear-gradient(135deg,#10b981,#34d399)',
    'linear-gradient(135deg,#6d28d9,#ec4899)',
];

let fbCurrentPage   = 1;
const FB_PER_PAGE   = 7;
let fbSelectedIds   = new Set();
let fbCurrentFilter = 'all';

function setQuickReply(text) {
    document.getElementById('fbReplyText').value = text;
}

// Thêm biến sort
let fbCurrentSort = 'newest_review'; // mặc định

function fbGetFiltered() {
    const q = (document.getElementById('fbSearch')?.value || '').toLowerCase();
    let result = feedbacks.filter(f => {
        const matchQ = !q
            || f.name.toLowerCase().includes(q)
            || f.comment.toLowerCase().includes(q)
            || f.product.toLowerCase().includes(q);
        if (fbCurrentFilter === 'pending') return f.status === 'pending' && matchQ;
        if (fbCurrentFilter === 'replied') return f.status === 'replied' && matchQ;
        return matchQ;
    });

    // Sắp xếp
    result.sort((a, b) => {
        if (fbCurrentSort === 'newest_reply') {
            // Đã phản hồi lên trên, rồi sort theo replied_at mới nhất
            if (a.replied_at && !b.replied_at) return -1;
            if (!a.replied_at && b.replied_at) return 1;
            if (a.replied_at && b.replied_at)
                return new Date(b.replied_at) - new Date(a.replied_at);
            return new Date(b.created_at) - new Date(a.created_at);
        }
        // newest_review — mặc định
        return new Date(b.created_at) - new Date(a.created_at);
    });

    return result;
}
function fbSetSort(el, sort) {
    document.querySelectorAll('.fb-sort-group .fb-tab')
            .forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    fbCurrentSort = sort;
    fbCurrentPage = 1;
    renderFeedback();
}
function fbStars(rating) {
    let h = '';
    for (let i = 1; i <= 5; i++)
        h += `<i class="fas fa-star${i > rating ? ' empty' : ''}"></i>`;
    return `<div class="fb-stars">${h}</div>`;
}

function fbAvatar(f) {
    if (f.avatar_url)
        return `<img src="${f.avatar_url}" class="fb-cust-avatar" style="object-fit:cover">`;
    const color = fbAvatarColors[f.id % fbAvatarColors.length];
    return `<div class="fb-cust-avatar" style="background:${color}">${f.initials}</div>`;
}

function renderFeedback() {
    const filtered   = fbGetFiltered();
    const total      = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / FB_PER_PAGE));
    if (fbCurrentPage > totalPages) fbCurrentPage = totalPages;
    const start = (fbCurrentPage - 1) * FB_PER_PAGE;
    const slice = filtered.slice(start, start + FB_PER_PAGE);

    document.getElementById('fb-count-all').textContent     = feedbacks.length;
    document.getElementById('fb-count-pending').textContent = feedbacks.filter(f => f.status === 'pending').length;
    document.getElementById('fb-count-replied').textContent = feedbacks.filter(f => f.status === 'replied').length;

    const body = document.getElementById('fbTableBody');
    if (!body) return;

    body.innerHTML = slice.map(f => {
        const checked  = fbSelectedIds.has(f.id);
        const badge    = f.status === 'replied'
            ? `<span class="fb-badge-replied"><i class="fas fa-check-circle"></i> Đã phản hồi</span>`
            : `<span class="fb-badge-pending"><i class="fas fa-comment-dots"></i> Chưa phản hồi</span>`;
        const verified = f.is_verified
            ? `<span style="font-size:11px;color:#10b981;margin-left:4px"><i class="fas fa-shield-alt"></i> Đã mua hàng</span>`
            : '';
        return `<tr>
            <td><div class="checkbox-custom${checked ? ' checked' : ''}" onclick="fbToggleRow(this,${f.id})">${checked ? '<i class="fas fa-check" style="font-size:10px"></i>' : ''}</div></td>
            <td>
                <div class="fb-cust-cell">
                    ${fbAvatar(f)}
                    <div>
                        <div class="fb-cust-name">${f.name}${verified}</div>
                        <div class="fb-cust-sub">${f.comment}</div>
                    </div>
                </div>
            </td>
            <td style="font-size:13px;color:var(--muted);max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${f.product}</td>
            <td>${fbStars(f.rating)}</td>
            <td style="font-size:13px;color:var(--muted);white-space:nowrap">${f.date}</td>
            <td style="font-size:13px;color:var(--muted);white-space:nowrap">
    ${f.replied_at ? new Date(f.replied_at).toLocaleDateString('vi-VN') : '—'}
</td>
            <td>${badge}</td>
            <td>
                <button class="fb-btn-reply" onclick="openFbReply(${f.id})" title="Phản hồi">
                    <i class="fas fa-pen"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    const pag = () => {
        let h = '';
        if (fbCurrentPage > 1)
            h += `<button class="page-btn" onclick="fbCurrentPage=${fbCurrentPage - 1};renderFeedback()"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>`;
        for (let i = 1; i <= totalPages; i++)
            h += `<button class="page-btn${i === fbCurrentPage ? ' active' : ''}" onclick="fbCurrentPage=${i};renderFeedback()">${i}</button>`;
        if (fbCurrentPage < totalPages)
            h += `<button class="page-btn" onclick="fbCurrentPage=${fbCurrentPage + 1};renderFeedback()"><i class="fas fa-chevron-right" style="font-size:10px"></i></button>`;
        return h;
    };
    document.getElementById('fbTopPag').innerHTML = pag();
    document.getElementById('fbBotPag').innerHTML = pag();
    document.getElementById('fbTableInfo').textContent =
        `Hiển thị ${total === 0 ? 0 : start + 1}–${Math.min(start + FB_PER_PAGE, total)} / ${total} phản hồi`;
    document.getElementById('fbPageLabel').textContent = `${fbCurrentPage} / ${totalPages}`;
    updateFbSelLabel();
}

function fbSetTab(el, filter) {
    document.querySelectorAll('.fb-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    fbCurrentFilter = filter;
    fbCurrentPage   = 1;
    renderFeedback();
}

function fbToggleRow(el, id) {
    if (fbSelectedIds.has(id)) {
        fbSelectedIds.delete(id);
        el.classList.remove('checked');
        el.innerHTML = '';
    } else {
        fbSelectedIds.add(id);
        el.classList.add('checked');
        el.innerHTML = '<i class="fas fa-check" style="font-size:10px"></i>';
    }
    updateFbSelLabel();
}

function fbToggleAll(el) {
    const filtered    = fbGetFiltered();
    const allSelected = filtered.every(f => fbSelectedIds.has(f.id));
    if (allSelected) {
        filtered.forEach(f => fbSelectedIds.delete(f.id));
        el.classList.remove('checked');
        el.innerHTML = '';
    } else {
        filtered.forEach(f => fbSelectedIds.add(f.id));
        el.classList.add('checked');
        el.innerHTML = '<i class="fas fa-check" style="font-size:10px"></i>';
    }
    renderFeedback();
}

function updateFbSelLabel() {
    const lbl = document.getElementById('fbSelLabel');
    if (lbl) lbl.textContent = fbSelectedIds.size > 0 ? `(Đã chọn ${fbSelectedIds.size})` : '';
}

function fbBulkDelete() {
    if (fbSelectedIds.size === 0) { alert('Vui lòng chọn phản hồi cần xóa'); return; }
    if (!confirm(`Xóa ${fbSelectedIds.size} phản hồi đã chọn?`)) return;

    const ids = [...fbSelectedIds];
    const params = new URLSearchParams({ action: 'bulk_delete' });
    ids.forEach(id => params.append('ids[]', id));

    fetch('/MantaMarket/admin/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => {
        if (!res.ok) throw new Error();
        // Xóa khỏi local array
        ids.forEach(id => {
            const idx = feedbacks.findIndex(f => f.id === id);
            if (idx !== -1) feedbacks.splice(idx, 1);
        });
        fbSelectedIds.clear();
        renderFeedback();
        showFbToast(`Đã xóa ${ids.length} phản hồi.`);
    })
    .catch(() => alert('Có lỗi xảy ra khi xóa.'));
}

function openFbReply(id) {
    const f = feedbacks.find(fb => fb.id === id);
    if (!f) return;
    document.getElementById('fbModalTitle').textContent = `Phản hồi — ${f.name}`;
    document.getElementById('fbReviewBubble').innerHTML = `
        <div class="fb-review-meta">
            ${fbAvatar(f)}
            <div>
                <div class="fb-review-name">${f.name}</div>
                ${fbStars(f.rating)}
            </div>
            <span class="fb-review-date">${f.date}</span>
        </div>
        ${f.title ? `<div style="font-weight:600;margin-bottom:4px">${f.title}</div>` : ''}
        <div class="fb-review-text">${f.comment || '(Không có nội dung)'}</div>
        ${f.reply ? `<div class="fb-existing-reply"><i class="fas fa-reply" style="margin-right:6px;color:var(--purple1)"></i><em>Đã phản hồi: ${f.reply}</em></div>` : ''}`;
    document.getElementById('fbReplyText').value    = f.reply || '';
    document.getElementById('fbModalBg')._editId    = id;
    document.getElementById('fbModalBg').style.display = 'flex';
}

function closeFbModal() {
    document.getElementById('fbModalBg').style.display = 'none';
}

function submitFbReply() {
    const id   = document.getElementById('fbModalBg')._editId;
    const text = document.getElementById('fbReplyText').value.trim();
    if (!text) { alert('Vui lòng nhập nội dung phản hồi'); return; }

    const btn = document.querySelector('.fb-btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';

    fetch('/MantaMarket/admin/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'reply', id: id, reply: text })
    })
    .then(res => {
        if (!res.ok) throw new Error('Lỗi server');

        // Cập nhật local data không cần reload
        const f = feedbacks.find(fb => fb.id === id);
        if (f) {
            f.reply      = text;
            f.status     = 'replied';
            f.replied_at = new Date().toISOString();
        }

        closeFbModal();
        renderFeedback();

        // Toast thông báo thành công
        showFbToast('Phản hồi đã được gửi thành công!');
    })
    .catch(() => {
        alert('Có lỗi xảy ra, vui lòng thử lại.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi phản hồi';
    });
}
function showFbToast(msg) {
    const t = document.createElement('div');
    t.style.cssText = `
        position:fixed;bottom:30px;right:30px;z-index:9999;
        background:#10b981;color:#fff;padding:12px 20px;
        border-radius:10px;font-size:14px;font-weight:500;
        box-shadow:0 4px 20px rgba(0,0,0,0.15);
        animation:slideInUp .3s ease;
    `;
    t.innerHTML = `<i class="fas fa-check-circle" style="margin-right:8px"></i>${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
// Đóng modal khi click ngoài
document.getElementById('fbModalBg').addEventListener('click', function(e) {
    if (e.target === this) closeFbModal();
});

renderFeedback();
</script>