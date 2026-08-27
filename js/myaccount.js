function openMyAccountPanel(tab = 'profile') {
    const container = document.getElementById('spPanelContainer');
    if (container.style.display === 'none' || container.style.display === '') {
        savedScrollY = window.scrollY;
    }

    history.pushState({ accountPanel: true, tab: tab }, '', '?page=myaccount&tab=' + tab);
    document.querySelectorAll('.page-section').forEach(el => el.style.display = 'none');
    container.style.display = 'block';

    const content = document.getElementById('spPanelContent');
    content.innerHTML = '<div style="text-align:center;padding:60px;font-size:16px;color:#888">Đang tải...</div>';

    fetch('/MantaMarket/public/myaccount.php')
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                const href = link.getAttribute('href');
                const absHref = href.startsWith('http') ? href
                    : '/MantaMarket/' + href.replace(/^\.\.\//, '');
                if (!document.querySelector(`link[href="${absHref}"]`)) {
                    const newLink = document.createElement('link');
                    newLink.rel  = 'stylesheet';
                    newLink.href = absHref;
                    document.head.appendChild(newLink);
                }
            });

            const bodyHtml = doc.querySelector('body').innerHTML
                .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');

            ['/MantaMarket/js/myaccount.js', '/MantaMarket/js/myaccount1.js'].forEach(src => {
                document.querySelector(`script[src="${src}"]`)?.remove();
            });

            const scripts = [
                '/MantaMarket/js/myaccount.js',
                '/MantaMarket/js/myaccount1.js'
            ];

            scripts.reduce((promise, src) => {
                return promise.then(() => new Promise((resolve) => {
                    const s = document.createElement('script');
                    s.src    = src;
                    s.onload  = resolve;
                    s.onerror = resolve;
                    document.body.appendChild(s);
                }));
            }, Promise.resolve()).then(() => {
                content.innerHTML = bodyHtml;
                // ✅ Truyền tab vào sessionStorage trước khi gọi init()
                if (tab) sessionStorage.setItem('myaccount_page', tab);
                if (typeof init === 'function') init();
                window.scrollTo({ top: 0, behavior: 'instant' });
            });
        })
        .catch(() => {
            content.innerHTML = '<div style="text-align:center;padding:60px;color:red">Lỗi tải trang tài khoản.</div>';
        });
}
function openCheckoutPanel(cartItemIds) {
    const container = document.getElementById('spPanelContainer');
    history.pushState({ checkoutPanel: true }, '', '?page=checkout');
    document.querySelectorAll('.page-section').forEach(el => el.style.display = 'none');
    container.style.display = 'block';

    const content = document.getElementById('spPanelContent');
    content.innerHTML = '<div style="text-align:center;padding:60px;font-size:16px;color:#888">Đang tải thanh toán...</div>';

    // Gửi POST bằng fetch với cart_item_ids
    const formData = new FormData();
    cartItemIds.forEach(id => formData.append('cart_item_ids[]', id));

    fetch('/MantaMarket/public/thanhtoan.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Load CSS
            doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                const href = link.getAttribute('href');
                const absHref = href.startsWith('http') ? href
                    : '/MantaMarket/' + href.replace(/^\.\.\//, '');
                if (!document.querySelector(`link[href="${absHref}"]`)) {
                    const newLink = document.createElement('link');
                    newLink.rel = 'stylesheet';
                    newLink.href = absHref;
                    document.head.appendChild(newLink);
                }
            });

            // Lấy body HTML, bỏ script tags
            const bodyHtml = doc.querySelector('body').innerHTML
                .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');

            // Load JS của thanh toán nếu có
            const checkoutScripts = [];
            doc.querySelectorAll('script[src]').forEach(s => {
                const src = s.getAttribute('src');
                const absSrc = src.startsWith('http') ? src
                    : '/MantaMarket/' + src.replace(/^\.\.\//, '');
                checkoutScripts.push(absSrc);
            });

            // Xóa script cũ
            checkoutScripts.forEach(src => {
                document.querySelector(`script[src="${src}"]`)?.remove();
            });

            // Inject HTML trước để DOM sẵn sàng trước khi chạy inline scripts
            content.innerHTML = bodyHtml;

            checkoutScripts.reduce((promise, src) => {
                return promise.then(() => new Promise(resolve => {
                    const s = document.createElement('script');
                    s.src = src;
                    s.onload = resolve;
                    s.onerror = resolve;
                    document.body.appendChild(s);
                }));
            }, Promise.resolve()).then(() => {
                // Xử lý inline scripts (VD: khởi tạo trang thanh toán)
                // Dùng window.* thay vì const để tránh "already been declared"
                doc.querySelectorAll('script:not([src])').forEach(orig => {
                    const sanitized = orig.textContent
                        .replace(/\bconst\s+(AVAILABLE_COUPONS|IS_LOGGED_IN|RECEIVER_WALLET|CONTRACT|SUBTOTAL_VND|SHIPPING_VND|TOTAL_VND|CART_ITEM_IDS|CART_ITEMS_DATA)\b/g,
                            (m, name) => `window.${name} = window.${name} !== undefined ? window.${name} : (void 0, window.${name}`
                        );
                    const s = document.createElement('script');
                    s.textContent = orig.textContent
                        .replace(/^\s*const\s+(AVAILABLE_COUPONS|IS_LOGGED_IN|RECEIVER_WALLET|CONTRACT|SUBTOTAL_VND|SHIPPING_VND|TOTAL_VND|CART_ITEM_IDS|CART_ITEMS_DATA)\s*=/gm,
                            (m, name) => `window.${name} =`);
                    document.body.appendChild(s);
                });

                // Gọi thủ công các hàm init của checkout vì DOMContentLoaded không fire trong SPA
                if (typeof renderVouchers === 'function') renderVouchers();
                if (typeof fetchBNBPrice === 'function') fetchBNBPrice();

                window.scrollTo({ top: 0, behavior: 'instant' });
            });
        })
        .catch(() => {
            content.innerHTML = '<div style="text-align:center;padding:60px;color:red">Lỗi tải trang thanh toán.</div>';
        });
}

// Popstate
window.addEventListener('popstate', function (e) {
    if (e.state && e.state.productId) {
        openSpPanel(e.state.productId);
    } else if (e.state && e.state.categorySlug) {
        openCategoryPanel(e.state.categorySlug);
    } else if (e.state && e.state.accountPanel) {
        // ✅ Nếu có tab thì chuyển đến tab đó, không cần reload lại toàn bộ
        if (e.state.tab && typeof showPage === 'function') {
            showPage(e.state.tab, null);
        } else {
            openMyAccountPanel();
        }
    } else if (e.state && e.state.checkoutPanel) {
        openMyAccountPanel();
    } else {
        closeSpPanel();
    }
});

window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    const slug = params.get('slug');
    const page = params.get('page');
    if (id) {
        openSpPanel(id);
    } else if (slug) {
        openCategoryPanel(slug);
    } else if (page === 'myaccount') {
        openMyAccountPanel();
    } else if (page === 'checkout') {
        // Nếu refresh trang checkout → về myaccount
        openMyAccountPanel();
    }
});