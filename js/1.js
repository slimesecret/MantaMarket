/* ══════════════════════════════════════════════
   1.js — Logic giao diện header
   ══════════════════════════════════════════════ */

/* ── ĐẾM NGƯỢC FLASH SALE ──
   Chỉ chạy nếu trang có .countdown-block */
function updateCountdown() {
    const blocks = document.querySelectorAll('.countdown-block');
    if (!blocks || blocks.length < 3) return; // ← GUARD: không có element thì bỏ qua

    let h = parseInt(blocks[0].textContent);
    let m = parseInt(blocks[1].textContent);
    let s = parseInt(blocks[2].textContent);

    s--;
    if (s < 0) { s = 59; m--; }
    if (m < 0) { m = 59; h--; }
    if (h < 0) { h = 1; m = 23; s = 59; }

    blocks[0].textContent = String(h).padStart(2, '0');
    blocks[1].textContent = String(m).padStart(2, '0');
    blocks[2].textContent = String(s).padStart(2, '0');
}

// Chỉ bật interval nếu trang có countdown
if (document.querySelector('.countdown-block')) {
    setInterval(updateCountdown, 1000);
}


/* ── XOAY PLACEHOLDER THANH TÌM KIẾM ──
   Chỉ chạy nếu trang có .search-input */
const placeholders = [
    'Shopee bao ship 0Đ - Đăng ký ngay!',
    'Đồ Ngủ Ở Nhà',
    'Ốp Lưng Đẹp',
    'Áo Khoác Hot',
    'Máy Ảnh Chụp Ảnh Lấy Liền'
];
let pIdx = 0;
const searchInput = document.querySelector('.search-input');

// Chỉ bật interval nếu tìm thấy ô tìm kiếm
if (searchInput) {
    setInterval(() => {
        pIdx = (pIdx + 1) % placeholders.length;
        searchInput.setAttribute('placeholder', placeholders[pIdx]);
    }, 3000);
}


/* ── SCROLL HEADER ──
   Thêm class "scrolled" vào #header khi cuộn > 50px */
window.addEventListener("scroll", function () {
    const header = document.getElementById("header");
    if (!header) return; // ← GUARD
    if (window.scrollY > 50) {
        header.classList.add("scrolled");
    } else {
        header.classList.remove("scrolled");
    }
});


/* ── LOGIN PROMPT ── */
function requireLogin() {
    showLoginPrompt();
}

function showLoginPrompt() {
    const old = document.getElementById('loginPromptOverlay');
    if (old) old.remove();

    const overlay = document.createElement('div');
    overlay.id = 'loginPromptOverlay';
    overlay.style.cssText = `
        position:fixed; inset:0; background:rgba(0,0,0,.5);
        display:flex; align-items:center; justify-content:center;
        z-index:9999;
    `;
    overlay.innerHTML = `
        <div style="background:#fff; border-radius:12px; padding:32px; 
                    max-width:360px; width:90%; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,.2)">
            <div style="font-size:48px; margin-bottom:16px">🔐</div>
            <h3 style="margin:0 0 8px; font-size:18px; color:#333">Vui lòng đăng nhập</h3>
            <p style="margin:0 0 24px; color:#888; font-size:14px">
                Bạn cần đăng nhập để thực hiện thao tác này
            </p>
            <div style="display:flex; gap:12px; justify-content:center">
                <button onclick="document.getElementById('loginPromptOverlay').remove()"
                    style="padding:10px 20px; border:1px solid #ddd; border-radius:6px; 
                           background:#fff; cursor:pointer; font-size:14px">
                    Huỷ
                </button>
                <a href="/MantaMarket/public/index.php?r=login"
                    style="padding:10px 20px; background:#ee4d2d; color:#fff; border-radius:6px; 
                           text-decoration:none; font-size:14px; display:inline-block">
                    Đăng nhập
                </a>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}