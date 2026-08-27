// ── ĐỊA CHỈ: dữ liệu GHN ──
// Dùng window.* thay vì const để tránh lỗi "already declared" khi load nhiều file
if (typeof window.GHN_TOKEN === 'undefined') window.GHN_TOKEN = 'YOUR_GHN_TOKEN';
if (typeof window.GHN_BASE === 'undefined') window.GHN_BASE = 'https://online-gateway.ghn.vn/shiip/public-api/master-data';

async function ghnFetch(endpoint, body = {}) {
    const res = await fetch(window.GHN_BASE + endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Token': window.GHN_TOKEN
        },
        body: JSON.stringify(body)
    });
    const json = await res.json();
    return json.data || [];
}

// ── ĐỊA CHỈ: dùng provinces.open-api.vn (miễn phí, không cần token) ──

async function loadProvinces() {
    const res = await fetch('https://provinces.open-api.vn/api/p/');
    const data = await res.json();
    const sel = document.getElementById('addr-province');
    sel.innerHTML = '<option value="">Chọn tỉnh/thành</option>';
    data.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.code;
        opt.dataset.name = p.name;
        opt.textContent = p.name;
        sel.appendChild(opt);
    });
}
// ── MỞ MODAL CẬP NHẬT (load data hiện tại vào form) ──
async function openEditAddressModal(addrId) {
    document.getElementById('modal-edit-address').style.display = 'flex';
    document.getElementById('edit-addr-id').value = addrId;

    try {
        const res = await fetch(`/MantaMarket/public/api/update_profile.php?action=get_address&id=${addrId}`);
        const json = await res.json();
        if (!json.success) {
            showProfileToast(json.message, 'error');
            return;
        }

        const a = json.address;
        document.getElementById('edit-addr-fullname').value = a.full_name;
        document.getElementById('edit-addr-phone').value = a.phone;
        document.getElementById('edit-addr-line').value = a.address_line;
        document.getElementById('edit-addr-default').checked = a.is_default == 1;

        // Load provinces rồi chọn đúng tỉnh → huyện → xã
        await loadEditProvinces(a.province);
        await loadEditDistricts(
            document.getElementById('edit-addr-province').value,
            a.district
        );
        await loadEditWards(
            document.getElementById('edit-addr-district').value,
            a.ward
        );
    } catch (e) {
        showProfileToast('Lỗi tải địa chỉ!', 'error');
    }
}

function closeEditAddressModal() {
    document.getElementById('modal-edit-address').style.display = 'none';
}

// ── LOAD TỈNH/HUYỆN/XÃ CHO MODAL EDIT ──
async function loadEditProvinces(selectedName = '') {
    const res = await fetch('https://provinces.open-api.vn/api/p/');
    const data = await res.json();
    const sel = document.getElementById('edit-addr-province');
    sel.innerHTML = '<option value="">Chọn tỉnh/thành</option>';
    data.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.code;
        opt.dataset.name = p.name;
        opt.textContent = p.name;
        if (p.name === selectedName) opt.selected = true;
        sel.appendChild(opt);
    });
}

async function loadEditDistricts(provinceCode, selectedName = '') {
    const distSel = document.getElementById('edit-addr-district');
    const wardSel = document.getElementById('edit-addr-ward');
    distSel.innerHTML = '<option value="">Chọn quận/huyện</option>';
    wardSel.innerHTML = '<option value="">Chọn phường/xã</option>';
    distSel.disabled = true;
    wardSel.disabled = true;
    if (!provinceCode) return;

    const res = await fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`);
    const data = await res.json();
    data.districts.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.code;
        opt.dataset.name = d.name;
        opt.textContent = d.name;
        if (d.name === selectedName) opt.selected = true;
        distSel.appendChild(opt);
    });
    distSel.disabled = false;
}

async function loadEditWards(districtCode, selectedName = '') {
    const wardSel = document.getElementById('edit-addr-ward');
    wardSel.innerHTML = '<option value="">Chọn phường/xã</option>';
    wardSel.disabled = true;
    if (!districtCode) return;

    const res = await fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`);
    const data = await res.json();
    data.wards.forEach(w => {
        const opt = document.createElement('option');
        opt.value = w.code;
        opt.dataset.name = w.name;
        opt.textContent = w.name;
        if (w.name === selectedName) opt.selected = true;
        wardSel.appendChild(opt);
    });
    wardSel.disabled = false;
}

// ── SUBMIT CẬP NHẬT ──
async function submitEditAddress() {
    const id = document.getElementById('edit-addr-id').value;
    const fullname = document.getElementById('edit-addr-fullname').value.trim();
    const phone = document.getElementById('edit-addr-phone').value.trim();
    const provSel = document.getElementById('edit-addr-province');
    const distSel = document.getElementById('edit-addr-district');
    const wardSel = document.getElementById('edit-addr-ward');
    const line = document.getElementById('edit-addr-line').value.trim();
    const isDefault = document.getElementById('edit-addr-default').checked;

    const province = provSel.options[provSel.selectedIndex]?.dataset.name || '';
    const district = distSel.options[distSel.selectedIndex]?.dataset.name || '';
    const ward = wardSel.options[wardSel.selectedIndex]?.dataset.name || '';

    if (!fullname || !phone || !province || !district || !ward || !line) {
        showProfileToast('Vui lòng điền đầy đủ thông tin!', 'error');
        return;
    }

    const data = new FormData();
    data.append('action', 'edit_address');
    data.append('id', id);
    data.append('full_name', fullname);
    data.append('phone', phone);
    data.append('province', province);
    data.append('district', district);
    data.append('ward', ward);
    data.append('address_line', line);
    data.append('is_default', isDefault ? '1' : '0');

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', {
            method: 'POST',
            body: data
        });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
        if (!json.success) return;

        const btn = document.querySelector(`[onclick="openEditAddressModal(${id})"]`);
        const item = btn?.closest('.address-item');
        if (item) {
            const nameEl = item.querySelector('.address-name');
            const phoneEl = item.querySelector('.address-phone');
            if (nameEl) nameEl.textContent = fullname;
            if (phoneEl) phoneEl.textContent = `(+84) ${phone.replace(/^0/, '')}`;

            const detailEl = item.querySelector('.address-detail');
            if (detailEl) detailEl.innerHTML = `${line}<br>${ward}, ${district}, ${province}`;

            if (isDefault) {
                document.querySelectorAll('.address-item').forEach(other => {
                    if (other !== item) {
                        other.querySelectorAll('.badge-macdinh').forEach(el => el.remove());
                        const otherId = other.querySelector('[onclick^="openEditAddressModal"]')
                            ?.getAttribute('onclick').match(/\d+/)?.[0];
                        const actionLinks = other.querySelector('.address-action-links');
                        const actionsDiv = other.querySelector('.address-actions');
                        if (!otherId) return;

                        if (!other.querySelector('[onclick^="deleteAddress"]') && actionLinks) {
                            const b = document.createElement('button');
                            b.className = 'btn-xoa';
                            b.textContent = 'Xóa';
                            b.setAttribute('onclick', `deleteAddress(${otherId})`);
                            actionLinks.appendChild(b);
                        }
                        if (!other.querySelector('[onclick^="setDefaultAddress"]') && actionsDiv) {
                            const b = document.createElement('button');
                            b.className = 'btn-thietlap';
                            b.textContent = 'Thiết lập mặc định';
                            b.setAttribute('onclick', `setDefaultAddress(${otherId})`);
                            actionsDiv.appendChild(b);
                        }
                    }
                });
                if (!item.querySelector('.badge-macdinh')) {
                    const nameRow = item.querySelector('.address-name-row');
                    if (nameRow) {
                        const badge = document.createElement('span');
                        badge.className = 'badge-macdinh';
                        badge.textContent = 'Mặc định';
                        nameRow.insertAdjacentElement('afterend', badge);
                    }
                }
                item.querySelector('[onclick^="deleteAddress"]')?.remove();
                item.querySelector('[onclick^="setDefaultAddress"]')?.remove();
            }
        }

        closeEditAddressModal();

    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    }
}

// ── XÓA ĐỊA CHỈ ──
async function deleteAddress(addrId) {
    if (!confirm('Bạn có chắc muốn xóa địa chỉ này?')) return;

    const data = new FormData();
    data.append('action', 'delete_address');
    data.append('id', addrId);

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', {
            method: 'POST',
            body: data
        });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
        if (!json.success) return;

        const btn = document.querySelector(`[onclick="deleteAddress(${addrId})"]`);
        const item = btn?.closest('.address-item');
        if (item) {
            item.style.transition = 'opacity .25s';
            item.style.opacity = '0';
            setTimeout(() => item.remove(), 250);
        }

    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    }
}

// ── THIẾT LẬP MẶC ĐỊNH ──
async function setDefaultAddress(addrId) {
    const data = new FormData();
    data.append('action', 'set_default_address');
    data.append('id', addrId);

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', {
            method: 'POST',
            body: data
        });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
        if (!json.success) return;

        document.querySelectorAll('.address-item').forEach(item => {
            item.querySelectorAll('.badge-macdinh').forEach(el => el.remove());

            const btnThietlap = item.querySelector('[onclick^="setDefaultAddress"]');
            const btnXoa = item.querySelector('[onclick^="deleteAddress"]');
            const btnCapnhat = item.querySelector('[onclick^="openEditAddressModal"]');

            const thisId = btnThietlap ?
                btnThietlap.getAttribute('onclick').match(/\d+/)?.[0] :
                btnXoa ?
                    btnXoa.getAttribute('onclick').match(/\d+/)?.[0] :
                    btnCapnhat?.getAttribute('onclick').match(/\d+/)?.[0];

            if (!thisId) return;

            const actionLinks = item.querySelector('.address-action-links');
            const actionsDiv = item.querySelector('.address-actions');

            if (parseInt(thisId) === parseInt(addrId)) {
                const nameRow = item.querySelector('.address-name-row');
                if (nameRow) {
                    const badge = document.createElement('span');
                    badge.className = 'badge-macdinh';
                    badge.textContent = 'Mặc định';
                    nameRow.insertAdjacentElement('afterend', badge);
                }
                if (btnXoa) btnXoa.style.display = 'none';
                if (btnThietlap) btnThietlap.style.display = 'none';

            } else {
                if (btnXoa) {
                    btnXoa.style.display = '';
                } else if (actionLinks) {
                    const newXoa = document.createElement('button');
                    newXoa.className = 'btn-xoa';
                    newXoa.textContent = 'Xóa';
                    newXoa.setAttribute('onclick', `deleteAddress(${thisId})`);
                    actionLinks.appendChild(newXoa);
                }

                if (btnThietlap) {
                    btnThietlap.style.display = '';
                } else if (actionsDiv) {
                    const newThietlap = document.createElement('button');
                    newThietlap.className = 'btn-thietlap';
                    newThietlap.textContent = 'Thiết lập mặc định';
                    newThietlap.setAttribute('onclick', `setDefaultAddress(${thisId})`);
                    actionsDiv.appendChild(newThietlap);
                }
            }
        });

    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    }
}

async function loadDistricts(provinceCode) {
    const distSel = document.getElementById('addr-district');
    const wardSel = document.getElementById('addr-ward');
    distSel.innerHTML = '<option value="">Chọn quận/huyện</option>';
    wardSel.innerHTML = '<option value="">Chọn phường/xã</option>';
    distSel.disabled = true;
    wardSel.disabled = true;
    if (!provinceCode) return;

    const res = await fetch(`https://provinces.open-api.vn/api/p/${provinceCode}?depth=2`);
    const data = await res.json();
    data.districts.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.code;
        opt.dataset.name = d.name;
        opt.textContent = d.name;
        distSel.appendChild(opt);
    });
    distSel.disabled = false;
}

async function loadWards(districtCode) {
    const wardSel = document.getElementById('addr-ward');
    wardSel.innerHTML = '<option value="">Chọn phường/xã</option>';
    wardSel.disabled = true;
    if (!districtCode) return;

    const res = await fetch(`https://provinces.open-api.vn/api/d/${districtCode}?depth=2`);
    const data = await res.json();
    data.wards.forEach(w => {
        const opt = document.createElement('option');
        opt.value = w.code;
        opt.dataset.name = w.name;
        opt.textContent = w.name;
        wardSel.appendChild(opt);
    });
    wardSel.disabled = false;
}

function openAddressModal() {
    document.getElementById('modal-add-address').style.display = 'flex';
    loadProvinces();
}

function closeAddressModal() {
    document.getElementById('modal-add-address').style.display = 'none';
    ['addr-fullname', 'addr-phone', 'addr-line'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('addr-province').value = '';
    document.getElementById('addr-district').innerHTML = '<option value="">Chọn quận/huyện</option>';
    document.getElementById('addr-district').disabled = true;
    document.getElementById('addr-ward').innerHTML = '<option value="">Chọn phường/xã</option>';
    document.getElementById('addr-ward').disabled = true;
    document.getElementById('addr-default').checked = false;
}

async function submitAddress() {
    const fullname = document.getElementById('addr-fullname').value.trim();
    const phone = document.getElementById('addr-phone').value.trim();
    const provSel = document.getElementById('addr-province');
    const distSel = document.getElementById('addr-district');
    const wardSel = document.getElementById('addr-ward');
    const line = document.getElementById('addr-line').value.trim();
    const isDefault = document.getElementById('addr-default').checked;

    const province = provSel.options[provSel.selectedIndex]?.dataset.name || '';
    const district = distSel.options[distSel.selectedIndex]?.dataset.name || '';
    const ward = wardSel.options[wardSel.selectedIndex]?.dataset.name || '';

    if (!fullname || !phone || !province || !district || !ward || !line) {
        showProfileToast('Vui lòng điền đầy đủ thông tin!', 'error');
        return;
    }

    const data = new FormData();
    data.append('action', 'add_address');
    data.append('full_name', fullname);
    data.append('phone', phone);
    data.append('province', province);
    data.append('district', district);
    data.append('ward', ward);
    data.append('address_line', line);
    data.append('is_default', isDefault ? '1' : '0');

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', {
            method: 'POST',
            body: data
        });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
        if (!json.success) return;

        if (isDefault) {
            document.querySelectorAll('.address-item').forEach(item => {
                item.querySelectorAll('.badge-macdinh').forEach(el => el.remove());
                const btnThietlap = item.querySelector('[onclick^="setDefaultAddress"]');
                const btnXoa = item.querySelector('[onclick^="deleteAddress"]');
                const actionLinks = item.querySelector('.address-action-links');
                const actionsDiv = item.querySelector('.address-actions');
                const thisId = btnThietlap?.getAttribute('onclick').match(/\d+/)?.[0] ||
                    btnXoa?.getAttribute('onclick').match(/\d+/)?.[0];
                if (!thisId) return;

                if (btnXoa) btnXoa.style.display = '';
                else if (actionLinks) {
                    const b = document.createElement('button');
                    b.className = 'btn-xoa';
                    b.textContent = 'Xóa';
                    b.setAttribute('onclick', `deleteAddress(${thisId})`);
                    actionLinks.appendChild(b);
                }
                if (btnThietlap) btnThietlap.style.display = '';
                else if (actionsDiv) {
                    const b = document.createElement('button');
                    b.className = 'btn-thietlap';
                    b.textContent = 'Thiết lập mặc định';
                    b.setAttribute('onclick', `setDefaultAddress(${thisId})`);
                    actionsDiv.appendChild(b);
                }
            });
        }

        const newId = json.new_id;
        const newHtml = buildAddressItemHtml({
            id: newId,
            full_name: fullname,
            phone,
            province,
            district,
            ward,
            address_line: line,
            is_default: isDefault ? 1 : 0
        });
        const list = document.querySelector('.address-list');
        const empty = list.querySelector('[style*="text-align:center"]');
        if (empty) empty.remove();
        list.insertAdjacentHTML('beforeend', newHtml);

        closeAddressModal();

    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    }
}

function buildAddressItemHtml(addr) {
    const phoneFmt = `(+84) ${addr.phone.replace(/^0/, '')}`;
    return `
        <div class="address-item">
            <div class="address-info">
                <div class="address-name-row">
                    <span class="address-name">${addr.full_name}</span>
                    <span class="address-divider-v"></span>
                    <span class="address-phone">${phoneFmt}</span>
                </div>
                <div class="address-detail">
                    ${addr.address_line}<br>
                    ${addr.ward}, ${addr.district}, ${addr.province}
                </div>
                ${addr.is_default ? '<span class="badge-macdinh">Mặc định</span>' : ''}
            </div>
            <div class="address-actions">
                <div class="address-action-links">
                    <button class="btn-capnhat" onclick="openEditAddressModal(${addr.id})">Cập nhật</button>
                    ${!addr.is_default ? `<button class="btn-xoa" onclick="deleteAddress(${addr.id})">Xóa</button>` : ''}
                </div>
                ${!addr.is_default ? `<button class="btn-thietlap" onclick="setDefaultAddress(${addr.id})">Thiết lập mặc định</button>` : ''}
            </div>
        </div>`;
}
async function loadDeliveryEstimates() {
    const shippingOrders = document.querySelectorAll('.order-group[data-status="cho-giao-hang"]');

    for (const group of shippingOrders) {
        const orderId = group.dataset.orderId;
        if (!orderId) continue;

        // Kiểm tra đã có estimate chưa
        if (group.querySelector('.delivery-estimate')) continue;

        try {
            const res = await fetch(`/MantaMarket/public/api/estimate_delivery.php?order_id=${orderId}`);
            const json = await res.json();

            if (json.success) {
                const totalRow = group.querySelector('.order-total-row');
                if (totalRow) {
                    const badge = document.createElement('div');
                    badge.className = 'delivery-estimate';
badge.innerHTML = `
    <span class="delivery-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13" rx="1"/>
            <path d="M16 8h4l3 5v3h-7V8z"/>
            <circle cx="5.5" cy="18.5" r="2.5"/>
            <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
    </span>
    <span>Dự kiến giao: <strong>${json.estimated_date}</strong></span>
    ${json.distance_km ? `<span class="delivery-dist">(${json.distance_km} km)</span>` : ''}
`;
                    totalRow.insertAdjacentElement('beforebegin', badge);
                }
            }
        } catch (e) { }
    }
}
// ── Map payment method ──
function paymentLabel(method) {
    const icons = {
        cod: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M6 12h.01M18 12h.01"/></svg>`,
        bank_transfer: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="10" width="18" height="11" rx="1"/><path d="M3 10l9-7 9 7"/><line x1="12" y1="10" x2="12" y2="21"/><line x1="7" y1="10" x2="7" y2="21"/><line x1="17" y1="10" x2="17" y2="21"/></svg>`,
        momo: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/></svg>`,
        vnpay: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>`,
        zalopay: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M7 15l3-6 3 6M17 9v6"/></svg>`,
        credit_card: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>`,
        bnb: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`
    };

    const labels = {
        cod: 'Thanh toán khi nhận hàng (COD)',
        bank_transfer: 'Chuyển khoản ngân hàng',
        momo: 'MoMo',
        vnpay: 'VNPay',
        zalopay: 'ZaloPay',
        credit_card: 'Thẻ tín dụng',
        bnb: 'BNB (Crypto)'
    };

    const icon = icons[method] || '';
    const label = labels[method] || method;
    return `<span style="display:inline-flex;align-items:center;gap:5px;">${icon}${label}</span>`;
}

function paymentStatusLabel(status) {
    return {
        pending: { text: 'Chờ thanh toán', color: '#f59e0b' },
        paid: { text: 'Đã thanh toán', color: '#22c55e' },
        failed: { text: 'Thất bại', color: '#ef4444' },
        refunded: { text: 'Đã hoàn tiền', color: '#6366f1' }
    }[status] || { text: status, color: '#888' };
}
if (typeof window.ORDER_STATUS_ICONS === 'undefined') {
    window.ORDER_STATUS_ICONS = {
        pending:    `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
        confirmed:  `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
        processing: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>`,
        shipped:    `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>`,
        delivered:  `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>`,
        cancelled:  `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
        returned:   `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.45"/></svg>`
    };
}

function orderStatusInfo(status) {
    const icons = window.ORDER_STATUS_ICONS;
    return {
        pending:    { icon: icons.pending,    text: 'Chờ xác nhận',  sub: 'Đơn hàng đang chờ shop xác nhận',      color: '#3b82f6', bg: '#eff6ff' },
        confirmed:  { icon: icons.confirmed,  text: 'Đã xác nhận',   sub: 'Shop đã xác nhận, đang chuẩn bị hàng', color: '#3b82f6', bg: '#eff6ff' },
        processing: { icon: icons.processing, text: 'Đang xử lý',    sub: 'Đơn hàng đang được đóng gói',          color: '#f59e0b', bg: '#fffbeb' },
        shipped:    { icon: icons.shipped,    text: 'Chờ giao hàng', sub: 'Đơn hàng đang trên đường giao',        color: '#8b5cf6', bg: '#f5f3ff' },
        delivered:  { icon: icons.delivered,  text: 'Hoàn thành',    sub: 'Bạn đã nhận hàng thành công',          color: '#22c55e', bg: '#f0fdf4' },
        cancelled:  { icon: icons.cancelled,  text: 'Đã hủy',        sub: 'Đơn hàng đã bị hủy',                  color: '#ef4444', bg: '#fef2f2' },
        returned:   { icon: icons.returned,   text: 'Trả hàng',      sub: 'Đơn hàng đang được xử lý hoàn trả',   color: '#6b7280', bg: '#f9fafb' }
    }[status] || { icon: icons.pending, text: status, sub: '', color: '#888', bg: '#f8f9fa' };
}
function renderOrderDetail(d) {
    const si = orderStatusInfo(d.order_status);

    document.getElementById('od-code').textContent = '#' + d.order_code;
    document.getElementById('od-code2').textContent = d.order_code;
    document.getElementById('od-date').textContent =
        new Date(d.created_at).toLocaleDateString('vi-VN', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

    const statusBar = document.getElementById('od-status-bar');
    statusBar.style.background = si.bg;

    const statusIconEl = document.getElementById('od-status-icon');
    statusIconEl.innerHTML = si.icon;
    statusIconEl.style.color = si.color;

    document.getElementById('od-status-text').textContent = si.text;
    document.getElementById('od-status-text').style.color = si.color;
    document.getElementById('od-status-sub').textContent = si.sub;

    const estimateBadge = document.getElementById('od-estimate-badge');
    if (d.estimated_date) {
        estimateBadge.style.display = 'block';
        estimateBadge.innerHTML = `🚚 Dự kiến: <strong>${d.estimated_date}</strong>${d.distance_km ? ` · ${d.distance_km}km` : ''}`;
    } else {
        estimateBadge.style.display = 'none';
    }

    document.getElementById('od-payment').innerHTML = paymentLabel(d.payment_method);
    const ps = paymentStatusLabel(d.payment_status);
    const psEl = document.getElementById('od-payment-status');
    psEl.textContent = ps.text;
    psEl.style.color = ps.color;

    const shopInitial = (d.shop_name || 'S').charAt(0).toUpperCase();
    document.getElementById('od-shop').innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:40px;height:40px;border-radius:8px;background:#f0f0f0;
                        overflow:hidden;flex-shrink:0;border:1px solid #eee;
                        display:flex;align-items:center;justify-content:center;">
                <img src="${d.shop_avatar || ''}"
                    alt="${d.shop_name || ''}"
                    style="width:100%;height:100%;object-fit:cover;${d.shop_avatar ? '' : 'display:none'}"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div style="${d.shop_avatar ? 'display:none' : 'display:flex'};width:100%;height:100%;
                            align-items:center;justify-content:center;
                            font-size:16px;font-weight:700;color:#ee4d2d;background:#fff0ee;">
                    ${shopInitial}
                </div>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;color:#ee4d2d;">${d.shop_name || '—'}</div>
                <div style="font-size:11px;color:#aaa;margin-top:1px;">Cửa hàng</div>
            </div>
        </div>
    `;

    document.getElementById('od-address').innerHTML = d.address
        ? `<strong>${d.address.full_name}</strong> · ${d.address.phone}<br>
           ${d.address.address_line}, ${d.address.ward}, ${d.address.district}, ${d.address.province}`
        : 'Chưa có địa chỉ';

    const itemsEl = document.getElementById('od-items');
    itemsEl.innerHTML = (d.items || []).map(item => `
        <div class="od-item">
            <img src="${item.product_image || ''}"
                onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2256%22 height=%2256%22><rect fill=%22%23eee%22 width=%2256%22 height=%2256%22/></svg>'">
            <div style="flex:1;min-width:0;">
                <div class="od-item-name">${item.product_name}</div>
                ${item.color || item.size
            ? `<div class="od-item-variant">Phân loại: ${[item.color, item.size].filter(Boolean).join(' / ')}</div>`
            : ''}
                <div class="od-item-variant">x${item.quantity}</div>
            </div>
            <div class="od-item-price">
                <div class="price-unit">${formatPrice(item.unit_price)} / cái</div>
                <div class="price-total">${formatPrice(item.unit_price * item.quantity)}</div>
            </div>
        </div>
    `).join('');

    document.getElementById('od-subtotal').textContent = formatPrice(d.subtotal);
    document.getElementById('od-shipping-fee').textContent = formatPrice(d.shipping_fee);
    document.getElementById('od-total').textContent = formatPrice(d.total_amount);

    const vRow = document.getElementById('od-voucher-row');
    if (d.coupon_code && parseFloat(d.discount_amount) > 0) {
        vRow.style.display = 'flex';
        document.getElementById('od-coupon-code').textContent = d.coupon_code;
        document.getElementById('od-discount').textContent = '−' + formatPrice(d.discount_amount);
    } else {
        vRow.style.display = 'none';
    }

    document.getElementById('od-loading').style.display = 'none';
    document.getElementById('od-content').style.display = 'block';
}

// ── Mở modal chi tiết ──
async function openOrderDetail(orderId, event) {
    if (event && event.target.closest('button, a')) return;

    const modal = document.getElementById('modal-order-detail');
    modal.style.display = 'flex';
    document.getElementById('od-loading').style.display = 'block';
    document.getElementById('od-content').style.display  = 'none';
    document.getElementById('od-code').textContent = '';

    try {
        const res  = await fetch(`/MantaMarket/public/api/order_detail.php?order_id=${orderId}`);
        const text = await res.text(); // ✅ đọc text trước
        console.log('order_detail raw:', text);  // ✅ xem lỗi PHP nếu có

        const json = JSON.parse(text);
        if (!json.success) throw new Error(json.message);
        renderOrderDetail(json.data);
    } catch (e) {
        console.error('openOrderDetail error:', e);
        document.getElementById('od-loading').innerHTML =
            `<div style="color:#ef4444;padding:48px;text-align:center;">Lỗi: ${e.message}</div>`;
    }
}

function closeOrderDetail() {
    document.getElementById('modal-order-detail').style.display = 'none';
}

// Click backdrop để đóng
document.addEventListener('click', function (e) {
    const modal = document.getElementById('modal-order-detail');
    if (modal && e.target === modal) closeOrderDetail();
});
async function requestCancelOrder(orderId, orderCode, event) {
    event.stopPropagation();

    const reason = prompt(`Lý do hủy đơn hàng #${orderCode}:\n(Bắt buộc)`);
    if (reason === null) return; // user bấm Cancel
    if (!reason.trim()) {
        showProfileToast('Vui lòng nhập lý do hủy đơn!', 'error');
        return;
    }

    const data = new FormData();
    data.append('action', 'request_cancel');
    data.append('order_id', orderId);
    data.append('reason', reason.trim());

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', {
            method: 'POST',
            body: data
        });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');

        if (json.success) {
            // Đổi nút thành "Đang chờ duyệt"
            const btn = event.target;
            btn.textContent = 'Đang chờ duyệt hủy';
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.onclick = null;
        }
    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    }
}
function showPage(pageId, linkEl) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetEl = document.getElementById('page-' + pageId);
    if (!targetEl) return;
    targetEl.classList.add('active');

    // ✅ Xóa active khỏi tất cả nav items (cả subnav lẫn nav-title)
    document.querySelectorAll('.sidebar-subnav a').forEach(a => a.classList.remove('active'));
    document.querySelectorAll('.sidebar-nav-title').forEach(t => t.classList.remove('active'));

    // ✅ Set active cho item được click
    if (linkEl) {
        // Nếu là link subnav (Hồ Sơ, Địa Chỉ)
        if (linkEl.closest('.sidebar-subnav')) {
            linkEl.classList.add('active');
        } else {
            // Nếu là nav-title (Đơn Mua, Kho Voucher, Giỏ hàng)
            linkEl.classList.add('active');
        }
    }

    sessionStorage.setItem('myaccount_page', pageId);
    const newUrl = window.location.pathname + '?page=myaccount&tab=' + pageId;
    history.pushState({ accountPanel: true, tab: pageId }, '', newUrl);

    if (pageId === 'cart') loadCartPage();
    if (pageId === 'orders') {
        // Đợi DOM render xong rồi load estimates
        setTimeout(loadDeliveryEstimates, 300);
    }
}
function formatPrice(amount) {
    return parseInt(amount || 0).toLocaleString('vi-VN') + 'đ';
}
function toggleOrderItems(btn, event) {
    // Ngăn mở modal chi tiết khi click "Xem thêm"
    event.stopPropagation();

    const group = btn.closest('.order-group');
    const hidden = group.querySelectorAll('.order-product-hidden');
    const textEl = btn.querySelector('.show-more-text');
    const isHidden = hidden[0]?.style.display === 'none';

    hidden.forEach(el => el.style.display = isHidden ? '' : 'none');

    const total = group.querySelectorAll('.order-product').length;
    textEl.textContent = isHidden
        ? 'Thu gọn ▴'
        : `Xem thêm ${total - 2} sản phẩm ▾`;
}
function init() {
    const validPages = ['profile', 'address', 'orders', 'vouchers', 'cart'];
    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    const saved = sessionStorage.getItem('myaccount_page');
    const pageId = validPages.includes(tabFromUrl) ? tabFromUrl
        : validPages.includes(saved) ? saved
            : 'profile';

    const targetPage = document.getElementById('page-' + pageId);
    if (!targetPage) return;

    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    targetPage.classList.add('active');

    // ✅ Xóa tất cả active trước
    document.querySelectorAll('.sidebar-subnav a').forEach(a => a.classList.remove('active'));
    document.querySelectorAll('.sidebar-nav-title').forEach(t => t.classList.remove('active'));

    // ✅ Set active đúng item theo pageId
    if (pageId === 'profile' || pageId === 'address') {
        // Subnav items
        document.querySelectorAll('.sidebar-subnav a').forEach(a => {
            if (a.getAttribute('onclick')?.includes(`'${pageId}'`)) {
                a.classList.add('active');
            }
        });
    } else {
        // Nav-title items (orders, vouchers, cart)
        document.querySelectorAll('.sidebar-nav-title').forEach(t => {
            const onclick = t.getAttribute('onclick') || '';
            if (onclick.includes(`'${pageId}'`)) {
                t.classList.add('active');
            }
        });
    }

    if (pageId === 'cart' && typeof loadCartPage === 'function') {
        loadCartPage();
    }

    if (tabFromUrl) {
        const newUrl = window.location.pathname + '?page=myaccount';
        history.replaceState(null, '', newUrl);
    }
}
document.addEventListener('DOMContentLoaded', init);

function resetOrderFilter() {
    document.querySelectorAll('.order-tab').forEach(t => t.classList.remove('active'));
    const allTab = document.querySelector('.order-tab[data-filter="all"]');
    if (allTab) allTab.classList.add('active');
    document.querySelectorAll('.order-group').forEach(g => g.style.display = '');
    const emptyEl = document.getElementById('order-empty-state');
    if (emptyEl) emptyEl.style.display = 'none';
}

function checkOrderEmpty() {
    const visible = Array.from(document.querySelectorAll('.order-group')).filter(g => g.style.display !== 'none');
    const emptyEl = document.getElementById('order-empty-state');
    if (emptyEl) emptyEl.style.display = visible.length === 0 ? 'block' : 'none';
}

document.addEventListener('click', function (e) {
    const tab = e.target.closest('.order-tab');
    if (tab) {
        document.querySelectorAll('.order-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const filter = tab.getAttribute('data-filter');
        document.querySelectorAll('.order-group').forEach(function (group) {
            group.style.display = (filter === 'all' || group.getAttribute('data-status') === filter) ? '' : 'none';
        });
        checkOrderEmpty();
    }

    const vcat = e.target.closest('.voucher-cat');
    if (vcat) {
        document.querySelectorAll('.voucher-cat').forEach(c => c.classList.remove('active'));
        vcat.classList.add('active');
        const type = vcat.dataset.type;
        document.querySelectorAll('.voucher-item').forEach(item => {
            item.style.display = (type === 'all' || item.classList.contains(type)) ? '' : 'none';
        });
    }
});

function filterOrderBySearch(query) {
    const q = query.toLowerCase().trim();
    const activeFilter = document.querySelector('.order-tab.active')?.dataset.filter || 'all';
    document.querySelectorAll('.order-group').forEach(function (group) {
        const statusMatch = (activeFilter === 'all' || group.getAttribute('data-status') === activeFilter);
        if (!statusMatch) { group.style.display = 'none'; return; }
        if (!q) { group.style.display = ''; return; }
        const shop = group.dataset.shop || '';
        const code = group.dataset.orderCode || '';
        const products = group.dataset.products || '';
        group.style.display = (shop.includes(q) || code.includes(q) || products.includes(q)) ? '' : 'none';
    });
    checkOrderEmpty();
}

function copyVoucherCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
        setTimeout(() => { btn.innerHTML = orig; }, 1500);
    });
}

function saveVoucherCode() {
    const input = document.getElementById('voucher-search-input');
    const code = input.value.trim().toUpperCase();
    if (!code) { alert('Vui lòng nhập mã voucher!'); return; }
    alert('Đã lưu mã: ' + code);
}

function filterVoucherByCode(query) {
    const q = query.trim().toUpperCase();
    const activeType = document.querySelector('.voucher-cat.active')?.dataset.type || 'all';

    document.querySelectorAll('.voucher-item').forEach(item => {
        const typeMatch = activeType === 'all' || item.classList.contains(activeType);
        if (!typeMatch) { item.style.display = 'none'; return; }
        if (!q) { item.style.display = ''; return; }
        const codeEl = item.querySelector('.voucher-code-text');
        const code = codeEl ? codeEl.textContent.toUpperCase() : '';
        item.style.display = code.includes(q) ? '' : 'none';
    });

    const visible = [...document.querySelectorAll('.voucher-item')].filter(i => i.style.display !== 'none');
    let emptyEl = document.getElementById('voucher-empty-state');
    if (!emptyEl) {
        emptyEl = document.createElement('div');
        emptyEl.id = 'voucher-empty-state';
        emptyEl.style.cssText = 'padding:2rem;color:#999;text-align:center;grid-column:1/-1;';
        emptyEl.textContent = 'Không tìm thấy voucher với mã này.';
        document.querySelector('.voucher-grid').appendChild(emptyEl);
    }
    emptyEl.style.display = visible.length === 0 ? 'block' : 'none';
}

// ── SAVE PROFILE ──
async function saveProfile() {
    const btn = document.querySelector('.btn-save');
    btn.textContent = 'Đang lưu...';
    btn.disabled = true;

    const data = new FormData();
    data.append('action', 'update_info');
    data.append('username', document.getElementById('inp-username')?.value || '');
    data.append('full_name', document.getElementById('inp-fullname').value);
    data.append('phone', document.getElementById('inp-phone').value);

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', { method: 'POST', body: data });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    } finally {
        btn.textContent = 'Lưu';
        btn.disabled = false;
    }
}

function togglePasswordSection() {
    const sec = document.getElementById('password-section');
    const isHidden = sec.style.display === 'none' || sec.style.display === '';
    sec.style.display = isHidden ? 'block' : 'none';
    if (!isHidden) {
        ['inp-old-pw', 'inp-new-pw', 'inp-confirm-pw'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    }
}

async function savePassword(hasPassword) {
    const newPw = document.getElementById('inp-new-pw')?.value || '';
    const confirmPw = document.getElementById('inp-confirm-pw')?.value || '';
    const oldPw = document.getElementById('inp-old-pw')?.value || '';

    if (!newPw || newPw.length < 6) { showProfileToast('Mật khẩu mới phải có ít nhất 6 ký tự!', 'error'); return; }
    if (newPw !== confirmPw) { showProfileToast('Mật khẩu xác nhận không khớp!', 'error'); return; }
    if (hasPassword && !oldPw) { showProfileToast('Vui lòng nhập mật khẩu hiện tại!', 'error'); return; }

    const data = new FormData();
    data.append('action', 'update_password');
    data.append('new_password', newPw);
    if (hasPassword) data.append('old_password', oldPw);

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', { method: 'POST', body: data });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
        if (json.success) togglePasswordSection();
    } catch (e) {
        showProfileToast('Lỗi kết nối server!', 'error');
    }
}

// ── UPLOAD AVATAR ──
async function uploadAvatar(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => document.getElementById('avatarPreview').src = e.target.result;
    reader.readAsDataURL(file);

    const data = new FormData();
    data.append('action', 'update_avatar');
    data.append('avatar', file);

    try {
        const res = await fetch('/MantaMarket/public/api/update_profile.php', { method: 'POST', body: data });
        const json = await res.json();
        showProfileToast(json.message, json.success ? 'success' : 'error');
        if (!json.success) {
            document.getElementById('avatarPreview').src = document.getElementById('avatarPreview').dataset.orig;
        }
    } catch (e) {
        showProfileToast('Lỗi upload!', 'error');
    }
}

// ── TOAST THÔNG BÁO ──
function showProfileToast(msg, type = 'success') {
    let t = document.getElementById('profileToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'profileToast';
        t.style.cssText = `
            position:fixed; bottom:24px; right:24px; padding:12px 20px;
            border-radius:8px; font-size:14px; font-weight:500; z-index:99999;
            box-shadow:0 4px 16px rgba(0,0,0,.15); transition:opacity .3s;
            color:#fff; min-width:200px; text-align:center;
        `;
        document.body.appendChild(t);
    }
    t.style.background = type === 'success' ? '#22c55e' : '#ef4444';
    t.textContent = msg;
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 3000);
}

// ── LOAD GIỎ HÀNG FULL PAGE ──
async function loadCartPage() {
    const body = document.getElementById('cartPageBody');
    if (!body) return;

    body.innerHTML = `<div class="cart-loading"><div class="cart-spinner"></div><span>Đang tải giỏ hàng...</span></div>`;

    try {
        const res = await fetch('/MantaMarket/public/api/cart.php?action=get');
        const json = await res.json();

        if (!json.success) {
            body.innerHTML = '<div style="padding:2rem;color:#999;text-align:center;">Không thể tải giỏ hàng.</div>';
            return;
        }

        updateCartBadge(json.total_count);
        document.getElementById('totalItemCount').textContent = json.total_count;
        document.getElementById('selectedCount').textContent = 0;
        document.getElementById('cartTotalPrice').textContent = '0đ';

        if (!json.items || json.items.length === 0) {
            body.innerHTML = `
                <div class="cart-empty-wrap">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.2">
                        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6zM3 6h18M16 10a4 4 0 01-8 0"/>
                    </svg>
                    <p>Giỏ hàng của bạn đang trống</p>
                    <a href="/MantaMarket/public/index.php">Tiếp tục mua sắm</a>
                </div>`;
            return;
        }

        const shopGroups = {};
        json.items.forEach(item => {
            const sid = item.seller_id || 'unknown';
            if (!shopGroups[sid]) {
                shopGroups[sid] = { shop_name: item.shop_name || 'Cửa hàng', shop_slug: item.shop_slug || '', items: [] };
            }
            shopGroups[sid].items.push(item);
        });

        body.innerHTML = Object.entries(shopGroups).map(([sid, group]) => {
            const itemsHtml = group.items.map(item => {
                const price = parseInt(item.price).toLocaleString('vi-VN') + 'đ';
                const total = (parseInt(item.price) * item.quantity).toLocaleString('vi-VN') + 'đ';
                const variant = [item.color, item.size].filter(Boolean).join(' / ');
                const img = item.image_url || '';

                return `
                <div class="cart-item-row" data-item-id="${item.id}" data-price="${item.price}">
                    <div class="cart-item-info">
                        <label class="cart-check-wrap">
                            <input type="checkbox" class="cart-item-check" onchange="updateCartTotal()">
                        </label>
                        <img class="cart-item-img" src="${img}"
                            onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22><rect fill=%22%23eee%22 width=%2280%22 height=%2280%22/></svg>'"
                            alt="${item.product_name}">
                        <div>
                            <div class="cart-item-name">${item.product_name}</div>
                            ${variant ? `<div class="cart-item-variant">Phân loại: ${variant}</div>` : ''}
                        </div>
                    </div>
                    <div class="cart-item-price">${price}</div>
                    <div class="cart-qty-wrap">
                        <button class="cart-qty-btn" onclick="changeQty(this,-1)">−</button>
                        <input class="cart-qty-input qty-input" type="number"
                            value="${item.quantity}" min="1" max="99"
                            onchange="updateCartTotal()">
                        <button class="cart-qty-btn" onclick="changeQty(this,1)">+</button>
                    </div>
                    <div class="cart-item-total">${total}</div>
                    <div class="cart-item-delete">
                        <button class="cart-btn-delete cart-btn-remove"
                            onclick="removeCartItem(${item.id}, this)">Xóa</button>
                    </div>
                </div>`;
            }).join('');

            const shopUrl = group.shop_slug ? `/MantaMarket/public/index.php?seller=${group.shop_slug}` : '#';

            return `
            <div class="cart-shop-group">
                <div class="cart-shop-header">
                    <label class="cart-check-wrap">
                        <input type="checkbox" class="cart-shop-check" onchange="toggleShopCheck(this)">
                    </label>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ee4d2d" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    <a href="${shopUrl}" class="cart-shop-name-link">${group.shop_name}</a>
                </div>
                ${itemsHtml}
            </div>`;
        }).join('');

    } catch (e) {
        body.innerHTML = '<div style="padding:2rem;color:#e00;text-align:center;">Lỗi kết nối!</div>';
    }
}

function changeQty(btn, delta) {
    const row = btn.closest('.cart-item-row');
    const input = row.querySelector('.qty-input');
    const newVal = Math.max(1, Math.min(99, parseInt(input.value) + delta));
    input.value = newVal;
    updateCartTotal();
}

function updateCartTotal() {
    let total = 0, count = 0;
    document.querySelectorAll('.cart-item-row').forEach(row => {
        const cb = row.querySelector('.cart-item-check');
        const qty = parseInt(row.querySelector('.qty-input')?.value || 1);
        const price = parseInt(row.dataset.price || 0);
        const itemTotal = price * qty;
        const totalEl = row.querySelector('.cart-item-total');
        if (totalEl) totalEl.textContent = itemTotal.toLocaleString('vi-VN') + 'đ';
        if (cb?.checked) { total += itemTotal; count++; }
    });
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('cartTotalPrice').textContent = total.toLocaleString('vi-VN') + 'đ';
}

function toggleCheckAll(masterCb) {
    document.querySelectorAll('.cart-item-check').forEach(cb => cb.checked = masterCb.checked);
    document.querySelectorAll('#checkAll, #checkAllBottom').forEach(cb => cb.checked = masterCb.checked);
    updateCartTotal();
}

function toggleShopCheck(shopCb) {
    const group = shopCb.closest('.cart-shop-group');
    group.querySelectorAll('.cart-item-check').forEach(cb => cb.checked = shopCb.checked);
    updateCartTotal();
}

async function removeCartItem(itemId, btn) {
    if (!confirm('Xóa sản phẩm này khỏi giỏ hàng?')) return;
    const data = new FormData();
    data.append('action', 'remove');
    data.append('item_id', itemId);

    try {
        const res = await fetch('/MantaMarket/public/api/cart.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            const row = btn.closest('.cart-shop-group');
            row?.remove();
            updateCartBadge(json.cart_count);
            updateCartTotal();
            document.getElementById('totalItemCount').textContent = json.cart_count;
        }
    } catch (e) { }
}

async function deleteSelected() {
    const checked = [...document.querySelectorAll('.cart-item-check:checked')];
    if (!checked.length) { alert('Chưa chọn sản phẩm nào!'); return; }
    if (!confirm(`Xóa ${checked.length} sản phẩm đã chọn?`)) return;
    for (const cb of checked) {
        const row = cb.closest('.cart-item-row');
        const itemId = row?.dataset.itemId;
        if (!itemId) continue;
        const data = new FormData();
        data.append('action', 'remove');
        data.append('item_id', itemId);
        await fetch('/MantaMarket/public/api/cart.php', { method: 'POST', body: data });
        row.closest('.cart-shop-group')?.remove();
    }
    updateCartTotal();
    loadCartPage();
}

function proceedCheckout() {
    const count = parseInt(document.getElementById('selectedCount').textContent);
    if (!count) { showProfileToast('Vui lòng chọn ít nhất 1 sản phẩm!', 'error'); return; }

    const selectedIds = [];
    document.querySelectorAll('.cart-item-row').forEach(row => {
        const cb = row.querySelector('.cart-item-check');
        if (cb?.checked) {
            const itemId = row.dataset.itemId;
            if (itemId) selectedIds.push(itemId);
        }
    });

    if (!selectedIds.length) { showProfileToast('Vui lòng chọn ít nhất 1 sản phẩm!', 'error'); return; }

    if (typeof openCheckoutPanel === 'function') {
        openCheckoutPanel(selectedIds);
    } else {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/MantaMarket/public/thanhtoan.php';
        form.style.display = 'none';
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cart_item_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }
}

function openVoucherPicker() {
    showPage('vouchers', null);
}