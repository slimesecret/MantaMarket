function openCategoryPanel(slug) {
    const container = document.getElementById('spPanelContainer');
    if (container.style.display === 'none' || container.style.display === '') {
        savedScrollY = window.scrollY;
    }

    history.pushState({ categorySlug: slug }, '', '?slug=' + slug);
    document.querySelectorAll('.page-section').forEach(el => el.style.display = 'none');
    container.style.display = 'block';

    const content = document.getElementById('spPanelContent');
    content.innerHTML = '<div style="text-align:center;padding:60px;font-size:16px;color:#888">Đang tải...</div>';

    fetch('categories.php?slug=' + slug)
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

            fixCrumbAfterInject();
            window.scrollTo({ top: 0, behavior: 'instant' });
        })
        .catch(() => {
            content.innerHTML = '<div style="text-align:center;padding:60px;color:red">Lỗi tải danh mục.</div>';
        });
}

// Cập nhật popstate để xử lý cả category
window.addEventListener('popstate', function (e) {
    if (e.state && e.state.productId) {
        openSpPanel(e.state.productId);
    } else if (e.state && e.state.categorySlug) {
        openCategoryPanel(e.state.categorySlug);
    } else {
        closeSpPanel();
    }
});

// Cập nhật DOMContentLoaded để xử lý cả slug
window.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    const slug = params.get('slug');
    if (id) {
        openSpPanel(id);
    } else if (slug) {
        openCategoryPanel(slug);
    }
});

let currentSort   = 'sold';
        let activeRating  = 0;

        // ── Sort ──
        function setSort(sort, el) {
            currentSort = sort;
            document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            applyFilters();
        }

        // ── Rating toggle ──
        function toggleRating(star, el) {
            if (activeRating === star) {
                activeRating = 0;
                el.classList.remove('active');
            } else {
                activeRating = star;
                document.querySelectorAll('.rating-opt').forEach(o => o.classList.remove('active'));
                el.classList.add('active');
            }
            applyFilters();
        }

        // ── Áp dụng tất cả bộ lọc ──
        function applyFilters() {

    const checkedBrands  = [...document.querySelectorAll('.filter-brand:checked')].map(i => parseInt(i.value));
    const checkedSellers = [...document.querySelectorAll('.filter-seller:checked')].map(i => parseInt(i.value));
    
    // Nhân 1000 vì input nhập theo đơn vị k
    const priceMin = (parseFloat(document.getElementById('priceMin').value) || 0) * 1000;
    const priceMax = (parseFloat(document.getElementById('priceMax').value) || Infinity) * 1000;


            let cards = [...document.querySelectorAll('.cat-product-card')];

            // Lọc
            cards.forEach(card => {
                const brand   = parseInt(card.dataset.brand);
                const seller  = parseInt(card.dataset.seller);
                const price   = parseFloat(card.dataset.price);
                const rating  = parseFloat(card.dataset.rating);

                let visible = true;
                if (checkedBrands.length  && !checkedBrands.includes(brand))   visible = false;
                if (checkedSellers.length && !checkedSellers.includes(seller))  visible = false;
                if (priceMin > 0 && price < priceMin) visible = false;
                if (priceMax < Infinity && price > priceMax) visible = false;
                if (activeRating > 0 && rating < activeRating) visible = false;

                card.style.display = visible ? '' : 'none';
            });

            // Sort các card đang hiển thị
            const grid = document.getElementById('productGrid');
            const visible = cards.filter(c => c.style.display !== 'none');
            const hidden  = cards.filter(c => c.style.display === 'none');

            visible.sort((a, b) => {
                if (currentSort === 'sold')       return parseInt(b.dataset.sold)    - parseInt(a.dataset.sold);
                if (currentSort === 'newest')     return parseInt(b.dataset.created) - parseInt(a.dataset.created);
                if (currentSort === 'price_asc')  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                if (currentSort === 'price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                return 0;
            });

            // Re-append theo thứ tự: visible trước, hidden sau
            [...visible, ...hidden].forEach(c => grid.appendChild(c));

            // Cập nhật số kết quả
            const count = visible.length;
            document.getElementById('resultCount').textContent = count + ' kết quả';
            document.getElementById('catCount').textContent = count + ' sản phẩm';

            // Hiện/ẩn no-results
            let noRes = document.getElementById('noResults');
            if (count === 0) {
                if (!noRes) {
                    noRes = document.createElement('div');
                    noRes.id = 'noResults';
                    noRes.className = 'no-results';
                    noRes.textContent = 'Không tìm thấy sản phẩm phù hợp.';
                    grid.appendChild(noRes);
                }
            } else if (noRes) {
                noRes.remove();
            }
        }

        // ── Reset ──
        function resetFilters() {
            document.querySelectorAll('.filter-brand, .filter-seller').forEach(c => c.checked = false);
            document.getElementById('priceMin').value = '';
            document.getElementById('priceMax').value = '';
            activeRating = 0;
            document.querySelectorAll('.rating-opt').forEach(o => o.classList.remove('active'));
            currentSort = 'sold';
            document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('[data-sort="sold"]').classList.add('active');
            applyFilters();
        }

        // Bind checkbox change
        document.querySelectorAll('.filter-brand, .filter-seller').forEach(cb => {
            cb.addEventListener('change', applyFilters);
        });

        function setPresetPrice(min, max) {
    // Toggle: click lại preset đang active thì bỏ chọn
    const btns = document.querySelectorAll('.price-preset-btn');
    const clickedBtn = event.currentTarget;
    const isActive = clickedBtn.classList.contains('active');

    btns.forEach(b => b.classList.remove('active'));

    if (isActive) {
        // Bỏ chọn
        document.getElementById('priceMin').value = '';
        document.getElementById('priceMax').value = '';
    } else {
        clickedBtn.classList.add('active');
        document.getElementById('priceMin').value = min || '';
        document.getElementById('priceMax').value = max || '';
    }

    applyFilters();
}