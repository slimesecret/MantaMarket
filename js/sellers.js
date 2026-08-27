function openSellersPanel(shopSlug) {
    const container = document.getElementById('spPanelContainer');
    if (container.style.display === 'none' || container.style.display === '') {
        savedScrollY = window.scrollY;
    }

    history.pushState({ sellerSlug: shopSlug }, '', '?seller=' + shopSlug);
    document.querySelectorAll('.page-section').forEach(el => el.style.display = 'none');
    container.style.display = 'block';

    const content = document.getElementById('spPanelContent');
    content.innerHTML = '<div style="text-align:center;padding:60px;font-size:16px;color:#888">Đang tải...</div>';

    // ✅ Fetch đúng sellers.php thay vì categories.php
    fetch('sellers.php?slug=' + shopSlug)
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                const href = link.getAttribute('href');
                if (!document.querySelector(`link[href="${href}"]`)) {
                    const newLink = document.createElement('link');
                    newLink.rel = 'stylesheet';
                    newLink.href = href;
                    document.head.appendChild(newLink);
                }
            });

            content.innerHTML = doc.querySelector('body').innerHTML;

            content.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            window.scrollTo({ top: 0, behavior: 'instant' });
        })
        .catch(() => {
            content.innerHTML = '<div style="text-align:center;padding:60px;color:red">Lỗi tải trang shop.</div>';
        });
}

// ✅ popstate xử lý sellerSlug riêng biệt
window.addEventListener('popstate', function (e) {
    if (e.state && e.state.productId) {
        openSpPanel(e.state.productId);
    } else if (e.state && e.state.categorySlug) {
        openCategoryPanel(e.state.categorySlug);
    } else if (e.state && e.state.sellerSlug) {
        openSellersPanel(e.state.sellerSlug);
    } else {
        closeSpPanel();
    }
});

// ✅ DOMContentLoaded xử lý query param ?seller=
window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const id     = params.get('id');
    const slug   = params.get('slug');
    const seller = params.get('seller');

    if (id)     openSpPanel(id);
    else if (slug)   openCategoryPanel(slug);
    else if (seller) openSellersPanel(seller);
});