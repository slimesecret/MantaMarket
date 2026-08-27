// Đầu file — giữ let như cũ
let walletAddress = null;
let bnbPriceUSD   = 600;

/* ── MỞ / ĐÓNG MODAL CHỌN VÍ ──
   Nếu đã kết nối: toggle panel; chưa kết nối: mở modal */
function openWallet() {
    if (walletAddress) { toggleWalletPanel(); return; }
    connectMetaMask(); // ✅ Gọi đúng hàm
}

function closeWallet() {
    document.getElementById('walletModal').style.display = 'none';
}

/* Đóng modal / panel khi click ngoài vùng */
window.onclick = e => {
    const modal = document.getElementById('walletModal');
    const panel = document.getElementById('walletPanel');
    if (e.target === modal) closeWallet();
    if (!panel.contains(e.target) && !document.getElementById('walletTrigger').contains(e.target)) {
        panel.style.display = 'none';
    }
};


/* ── KẾT NỐI METAMASK ──
   Yêu cầu quyền truy cập tài khoản, sau đó cập nhật UI */
async function connectMetaMask() {
    if (!window.ethereum) { alert('Vui lòng cài MetaMask'); return; }
    try {
        const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
        walletAddress  = accounts[0];
        localStorage.setItem('walletAddress', walletAddress);
        await fetchBNBPrice();
        showWallet();
        await getBalance();
        closeWallet();
    } catch (err) { console.error(err); }
}


/* ── HIỂN THỊ UI SAU KHI KẾT NỐI ──
   Cập nhật địa chỉ rút gọn ở trigger + panel, ẩn nút "Kết nối ví" */
function showWallet() {
    const short = walletAddress.slice(0, 6) + '…' + walletAddress.slice(-4);
    document.getElementById('walletBtnText').textContent  = short;
    document.getElementById('triggerAddr').textContent    = short;
    document.getElementById('panelAddress').textContent   = short;
    document.getElementById('fullAddress').textContent    = walletAddress;
    document.getElementById('walletTrigger').classList.remove('hidden');
    document.querySelector('.stake-btn').style.display    = 'none';
}


/* ── LẤY SỐ DƯ BNB ──
   Gọi eth_getBalance, quy đổi sang USD và cập nhật UI */
async function getBalance() {
    try {
        const raw = await ethereum.request({
            method: 'eth_getBalance',
            params: [walletAddress, 'latest']
        });

        const bnb = Number(BigInt(raw)) / 1e18;
        const usd = (bnb * bnbPriceUSD).toFixed(2);
const vnd = Math.round(bnb * bnbPriceUSD * 25000).toLocaleString('vi-VN');
        document.getElementById('walletBalance').textContent    = bnb.toFixed(4) + ' BNB';
        document.getElementById('walletBalanceUSD').textContent = '≈ $' + usd + ' USD';
        document.getElementById('walletBalanceVND').textContent = '≈ ' + vnd + 'đ';

        document.getElementById('triggerBalance').textContent   = bnb.toFixed(4) + ' BNB';
    } catch (e) { console.error('getBalance error', e); }
}

/* ── LẤY GIÁ BNB TỪ COINGECKO ──
   Cập nhật bnbPriceUSD để tính quy đổi chính xác hơn */
async function fetchBNBPrice() {
    try {
        const r = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=binancecoin&vs_currencies=usd');
        const d = await r.json();
        bnbPriceUSD = d.binancecoin?.usd || 600;
    } catch (_) { /* giữ fallback */ }
}





/* ── XÁC NHẬN NGẮT KẾT NỐI (UI) ──
   Hiện / ẩn lớp confirm bên trong wallet-panel */
function showConfirm() { document.getElementById('confirmOverlay').classList.add('show'); }
function hideConfirm() { document.getElementById('confirmOverlay').classList.remove('show'); }


/* ── NGẮT KẾT NỐI ──
   Thu hồi quyền MetaMask, xóa trạng thái, reset UI */
async function confirmDisconnect() {
    try {
        if (window.ethereum) {
            await ethereum.request({ method: 'wallet_revokePermissions', params: [{ eth_accounts: {} }] });
        }
    } catch (e) { console.warn('revokePermissions:', e); }

    walletAddress = null;
    localStorage.removeItem('walletAddress');

    document.getElementById('walletBtnText').textContent    = 'Kết nối ví';
    document.getElementById('panelAddress').textContent     = '—';
    document.getElementById('fullAddress').textContent      = '—';
    document.getElementById('walletBalance').textContent    = '0.0000 BNB';
    document.getElementById('walletBalanceUSD').textContent = '≈ $0.00 USD';

    document.getElementById('triggerBalance').textContent   = '';
    document.getElementById('triggerAddr').textContent      = '—';

    document.querySelector('.stake-btn').style.display = 'inline-flex';
    document.getElementById('walletTrigger').classList.add('hidden');
    document.getElementById('walletPanel').style.display = 'none';
    hideConfirm();

    showToast('🔌 Đã hủy kết nối ví');
}


/* ── SAO CHÉP ĐỊA CHỈ ── */
function copyAddress() {
    if (!walletAddress) return;
    navigator.clipboard.writeText(walletAddress).then(() => showToast('Đã sao chép địa chỉ'));
}

// ── KẾT NỐI METAMASK ──
async function connectMetaMask() {
    if (!window.ethereum) { alert('Vui lòng cài MetaMask'); return; }
    try {
        const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
        walletAddress  = accounts[0];
        localStorage.setItem('walletAddress', walletAddress);

        // ✅ Lưu ví vào DB ngay khi kết nối
        await saveWalletToDB(walletAddress);

        await fetchBNBPrice();
        showWallet();
        await getBalance();
        closeWallet();
    } catch (err) { console.error(err); }
}

// ── LƯU VÍ VÀO DB ──
async function saveWalletToDB(wallet, orderId = null) {
    try {
        const data = new FormData();
        data.append('wallet', wallet);
        if (orderId) data.append('order_id', orderId);

        const res  = await fetch('/MantaMarket/public/api/save_wallet.php', {
            method: 'POST',
            body: data
        });
        const json = await res.json();
        console.log(json.success
            ? `✅ Đã lưu ví: ${wallet}`
            : `⚠️ Lưu ví thất bại: ${json.message}`
        );
        return json.success;
    } catch (e) {
        console.warn('saveWalletToDB error:', e);
        return false;
    }
}
// ── LẮNG NGHE THAY ĐỔI TÀI KHOẢN ──
if (window.ethereum) {
    ethereum.on('accountsChanged', accounts => {
        if (accounts.length) {
            walletAddress = accounts[0];
            localStorage.setItem('walletAddress', walletAddress);
            saveWalletToDB(walletAddress); // ✅ cập nhật DB khi đổi ví
            showWallet();
            getBalance();
        } else {
            confirmDisconnect();
        }
    });
}
/* ── TOAST THÔNG BÁO ── */
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 5000);
}


/* ── TỰ ĐỘNG RECONNECT KHI TẢI TRANG ── */
window.onload = async () => {
    const isLoggedIn = document.body.dataset.loggedIn === '1';
    if (!isLoggedIn) {
        localStorage.removeItem('walletAddress');
        return;
    }

    const saved = localStorage.getItem('walletAddress');
    if (saved && window.ethereum) {
        const accounts = await ethereum.request({ method: 'eth_accounts' });
        if (accounts.length) {
            walletAddress = accounts[0];
            await fetchBNBPrice();
            showWallet();
            await getBalance();
        }
    }
};

/* ── LẮNG NGHE THAY ĐỔI TÀI KHOẢN ── */
if (window.ethereum) {
    ethereum.on('accountsChanged', accounts => {
        if (accounts.length) {
            walletAddress = accounts[0];
            localStorage.setItem('walletAddress', walletAddress);
            showWallet();
            getBalance();
        } else {
            confirmDisconnect();
        }
    });
}

/* ── EXPOSE RA WINDOW ──
   Dùng object _wallet để các script khác (thanhtoan.php) đọc được
   walletAddress và bnbPriceUSD mà không cần Object.defineProperty */
window._wallet = {
    get addr()     { return walletAddress; },
    set addr(v)    { walletAddress = v; },
    get bnbPrice() { return bnbPriceUSD; }
};

/* ═══════════════════════════════════════════════════════════════════════
   LỊCH SỬ GIAO DỊCH — thêm vào cuối wallet.js
   (hoặc import riêng trước </body>)
   ═══════════════════════════════════════════════════════════════════════ */
// Thêm hàm decode ở đầu loadTxHistory hoặc ngoài hàm:
function decodeTxMemo(hexData) {
    if (!hexData || hexData === '0x' || hexData === '0x0') return null;
    try {
        const hex = hexData.startsWith('0x') ? hexData.slice(2) : hexData;
        const bytes = new Uint8Array(hex.match(/.{1,2}/g).map(b => parseInt(b, 16)));
        const text = new TextDecoder().decode(bytes);
        // Lấy phần sau "Thanh toan: "
        if (text.startsWith('Thanh toan: ')) return text.slice(12);
        return null;
    } catch { return null; }
}
/* ── RENDER LỊCH SỬ VÀO .wallet-actions ─────────────────────────────── */
async function loadTxHistory() {
    
    if (!walletAddress) return;

    const container = document.querySelector('.wallet-actions');
    if (!container) return;

    // Hiển thị skeleton loading
    container.innerHTML = `
        <div class="tx-section-title">Lịch sử giao dịch</div>
        <div class="tx-skeleton">
            <div class="tx-skel-row"></div>
            <div class="tx-skel-row"></div>
            <div class="tx-skel-row"></div>
        </div>`;

    try {
        const res  = await fetch(`/MantaMarket/public/api/get_tx_history.php?wallet=${encodeURIComponent(walletAddress)}&limit=40`);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div class="tx-error">⚠️ ${data.message}</div>`;
            return;
        }

        if (!data.txs || data.txs.length === 0) {
            container.innerHTML = `
                <div class="tx-section-title">Lịch sử giao dịch</div>
                <div class="tx-empty">
                    <span class="tx-empty-icon">📭</span>
                    <span>${data.note || 'Chưa có giao dịch nào'}</span>
                </div>`;
            return;
        }

        const rows = data.txs
    .filter(tx => parseFloat(tx.value_bnb) > 0)  // ✅ ẩn giao dịch 0 BNB
    .map(tx => {
            const date   = new Date(tx.timestamp * 1000);
            const timeStr = date.toLocaleString('vi-VN', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
            const isIn    = tx.direction === 'in';
            const isFail  = tx.status === 'failed';
            const sign    = isIn ? '+' : '−';
            const dirClass = isFail ? 'tx-failed' : (isIn ? 'tx-in' : 'tx-out');
            const MANTA_SHOP = '0x2e160cc5136143d859ef59adde676d726ec1492f';
const memo = isIn ? null : decodeTxMemo(tx.input);
const dirLabel = isFail ? 'Thất bại'
    : (isIn
        ? (tx.from.toLowerCase() === MANTA_SHOP ? 'MantaShop hoàn tiền' : 'Nhận')
        : (memo ? memo : 'Gửi'));
            const dirIcon  = isFail ? '✕' : (isIn ? '↓' : '↑');
            const bnbVal   = parseFloat(tx.value_bnb).toFixed(4);
const vndVal   = Math.round(parseFloat(tx.value_bnb) * bnbPriceUSD * 25000);
const vndStr   = vndVal.toLocaleString('vi-VN') + 'đ';
            const scanLink = `https://testnet.bscscan.com/tx/${tx.hash}`;

            return `
            <div class="tx-row ${dirClass}">
                <div class="tx-icon">${dirIcon}</div>
                <div class="tx-info">
                    <div class="tx-label">${dirLabel}
                        <span class="tx-hash">
                            <a href="${scanLink}" target="_blank" rel="noopener"
                               title="${tx.hash}">${tx.hash_short}</a>
                        </span>
                    </div>
                    <div class="tx-time">${timeStr}</div>
                </div>
<div class="tx-amount ${dirClass}">
    ${sign}${bnbVal} BNB
    <div class="tx-vnd">≈ ${sign}${vndStr}</div>
</div>
            </div>`;
        }).join('');

        container.innerHTML = `
            <div class="tx-section-title">
                Lịch sử giao dịch
                <button class="tx-refresh-btn" onclick="loadTxHistory()" title="Làm mới">↻</button>
            </div>
            <div class="tx-list">${rows}</div>
            <a class="tx-view-all"
               href="https://testnet.bscscan.com/address/${walletAddress}"
               target="_blank" rel="noopener">
               Xem tất cả trên BscScan ↗
            </a>`;

    } catch (e) {
        console.error('loadTxHistory error:', e);
        container.innerHTML = `<div class="tx-error">⚠️ Không thể tải lịch sử giao dịch</div>`;
    }
}


/* ── GỌI loadTxHistory KHI MỞ WALLET PANEL ─────────────────────────── */
// Thay hàm toggleWalletPanel() cũ trong wallet.js bằng hàm này:
function toggleWalletPanel() {
    const p = document.getElementById('walletPanel');
    const isHidden = p.style.display !== 'block';
    p.style.display = isHidden ? 'block' : 'none';
    if (isHidden) loadTxHistory(); // tải lịch sử mỗi lần mở panel
}