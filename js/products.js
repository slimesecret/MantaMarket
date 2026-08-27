let savedScrollY = 0;

function openSpPanel(id) {
    const container = document.getElementById('spPanelContainer');
    if (container.style.display === 'none' || container.style.display === '') {
        savedScrollY = window.scrollY;
    }

    history.pushState({ productId: id }, '', '?id=' + id);
    document.querySelectorAll('.page-section').forEach(el => el.style.display = 'none');
    container.style.display = 'block';

    const content = document.getElementById('spPanelContent');
    content.innerHTML = '<div style="text-align:center;padding:60px;font-size:16px;color:#888">Đang tải...</div>';

    fetch('products.php?id=' + id)
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

            // Sau khi inject xong, fix crumb dựa theo header thật
            fixCrumbAfterInject();

            window.scrollTo({ top: 0, behavior: 'instant' });
        })
        .catch(() => {
            content.innerHTML = '<div style="text-align:center;padding:60px;color:red">Lỗi tải sản phẩm.</div>';
        });
}

// Hàm fix crumb chạy từ index.php, sau khi panel được inject
function fixCrumbAfterInject() {
    const header = document.getElementById('header');
    const crumb = document.querySelector('#spPanelContent .crumb');
    if (header && crumb) {
        crumb.style.top = header.offsetHeight + 'px';
    }
}

function closeSpPanel() {
    sessionStorage.removeItem('myaccount_page'); // reset tab khi đóng panel
    history.pushState({}, '', 'index.php');
    document.getElementById('spPanelContainer').style.display = 'none';
    document.querySelectorAll('.page-section').forEach(el => el.style.display = '');
    window.scrollTo({ top: savedScrollY, behavior: 'instant' });
}

window.addEventListener('popstate', function (e) {
    if (e.state && e.state.productId) {
        openSpPanel(e.state.productId);
    } else {
        closeSpPanel();
    }
});

window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (id) {
        openSpPanel(id);
    }
});

window.addEventListener('resize', function () {
    fixCrumbAfterInject();
});