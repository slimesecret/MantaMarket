/* ══════════════════════════════════════════════
   taikhoan.js — Modal hồ sơ tài khoản
   ══════════════════════════════════════════════ */

const overlay    = document.getElementById('overlay');
const triggerBtn = document.getElementById('triggerBtn');
const closeBtn   = document.getElementById('closeBtn');

// ── Chỉ chạy nếu các element tồn tại ──
if (overlay && triggerBtn && closeBtn) {
    triggerBtn.addEventListener('click',   () => overlay.classList.add('show'));
    closeBtn.addEventListener('click',     () => overlay.classList.remove('show'));
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('show'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') overlay.classList.remove('show'); });
}

// ── ĐIỀN OPTION NGÀY / THÁNG / NĂM ──
const selDay   = document.getElementById('selDay');
const selMonth = document.getElementById('selMonth');
const selYear  = document.getElementById('selYear');

if (selDay && selMonth && selYear) {
    for (let d = 1; d <= 31; d++) {
        const o = document.createElement('option');
        o.value = d; o.textContent = d;
        selDay.appendChild(o);
    }

    const months = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6',
                     'Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];
    months.forEach((m, i) => {
        const o = document.createElement('option');
        o.value = i + 1; o.textContent = m;
        selMonth.appendChild(o);
    });

    const curYear = new Date().getFullYear();
    for (let y = curYear; y >= 1924; y--) {
        const o = document.createElement('option');
        o.value = y; o.textContent = y;
        if (y === 2005) o.selected = true;
        selYear.appendChild(o);
    }
}