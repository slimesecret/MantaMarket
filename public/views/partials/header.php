<?php
// Header nhận $router từ view gọi nó
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manta Việt Nam | Mua Sắm Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="/MantaMarket/img/manta-white-icon.png">
    <link rel="stylesheet" href="/MantaMarket/css/1.css" />
    <link rel="stylesheet" href="/MantaMarket/css/bg.css" />
    <link rel="stylesheet" href="/MantaMarket/css/card1.css" />
    <link rel="stylesheet" href="/MantaMarket/css/wallet.css" />
    <link rel="stylesheet" href="/MantaMarket/css/user.css" />
    <link rel="stylesheet" href="/MantaMarket/css/homeproduct.css" />
    <link rel="stylesheet" href="/MantaMarket/css/categories.css" />
    <link rel="stylesheet" href="/MantaMarket/css/myaccount.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
</head>

<body data-logged-in="<?= isset($_SESSION['userId']) ? '1' : '0' ?>">
    <!-- TOP BAR -->
    <div class="top-bar" id="header">
        <div class="top-bar-inner">
            <div class="top-bar-left">
                <a href="#">Kênh Người Bán</a>
                <span class="top-bar-divider">|</span>
                <a href="#">Tải ứng dụng</a>
                <span class="top-bar-divider">|</span>
                <a href="#" class="icon-link">Kết nối</a>
            </div>
            <div class="top-bar-right">
                <a href="#" class="icon-link">Thông Báo</a>
                <a href="#" class="icon-link">Hỗ Trợ</a>
                <a href="#" class="icon-link">Tiếng Việt</a>
                <span class="top-bar-divider">|</span>
                <?php if (!isset($_SESSION["userId"])): ?>
                    <a href="<?= $router->publicLogin() ?>">Đăng nhập</a>
                <?php else: ?>
                    <div class="user-dropdown">
                        <div class="user-trigger">
                            <img src="<?= $_SESSION['avatar'] ?>" alt="avatar" class="user-avatar" />
                            <span><?= $_SESSION["username"] ?? "Tài khoản" ?></span>
                        </div>
                        <div class="dropdown-menu">
                            <a id="triggerBtn" onclick="openMyAccountPanel()" style="cursor:pointer;">Tài Khoản Của Tôi</a>
                            <a href="javascript:void(0)" onclick="openMyAccountPanel('orders')" style="cursor:pointer;">Đơn Mua</a>
                            <a href="?r=logout">Đăng xuất</a>
                        </div>
                    </div>
                <?php endif; ?>
                <div style="display:flex; align-items:center; gap:12px;">
                    <!-- STAKE BUTTON -->
                    <button class="stake-btn" onclick="<?= isset($_SESSION['userId']) ? 'openWallet()' : "requireLogin('Bạn chưa đăng nhập, vui lòng đăng nhập để kết nối ví!')" ?>">
                        <span id="walletBtnText">Kết nối ví</span>
                    </button>
                    <!-- WALLET TRIGGER (hiện sau khi kết nối) -->
                    <div class="wallet-trigger hidden" id="walletTrigger" onclick="toggleWalletPanel()">
                        <div class="avatar"></div>
                        <div class="trigger-info">
                            <span class="trigger-addr" id="triggerAddr">—</span>
                            <span class="trigger-balance" id="triggerBalance">Đang tải…</span>
                        </div>
                        <span class="chevron">▾</span>
                    </div>
                </div>
                <!-- MODAL CONNECT -->
                <div id="walletModal" class="wallet-modal">
                    <div class="wallet-box">
                        <div class="modal-header">
                            <h2>Kết nối ví</h2>
                            <button class="close-btn" onclick="closeWallet()">✕</button>
                        </div>
                        <div class="wallet-list">
                            <div class="wallet-item" onclick="connectMetaMask()">
                                <img alt="MetaMask" class="h-6 w-6 shrink-0 rounded-md" src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzUiIGhlaWdodD0iMzQiIHZpZXdCb3g9IjAgMCAzNSAzNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTMyLjcwNzcgMzIuNzUyMkwyNS4xNjg4IDMwLjUxNzRMMTkuNDgzMyAzMy45MDA4TDE1LjUxNjcgMzMuODk5MUw5LjgyNzkzIDMwLjUxNzRMMi4yOTIyNSAzMi43NTIyTDAgMjUuMDQ4OUwyLjI5MjI1IDE2LjQ5OTNMMCA5LjI3MDk0TDIuMjkyMjUgMC4zMTIyNTZMMTQuMDY3NCA3LjMxNTU0SDIwLjkzMjZMMzIuNzA3NyAwLjMxMjI1NkwzNSA5LjI3MDk0TDMyLjcwNzcgMTYuNDk5M0wzNSAyNS4wNDg5TDMyLjcwNzcgMzIuNzUyMloiIGZpbGw9IiNGRjVDMTYiLz4KPHBhdGggZD0iTTIuMjkzOTUgMC4zMTIyNTZMMTQuMDY5MSA3LjMyMDQ3TDEzLjYwMDggMTIuMTMwMUwyLjI5Mzk1IDAuMzEyMjU2WiIgZmlsbD0iI0ZGNUMxNiIvPgo8cGF0aCBkPSJNOS44Mjk1OSAyNS4wNTIyTDE1LjAxMDYgMjguOTgxMUw5LjgyOTU5IDMwLjUxNzVWMjUuMDUyMloiIGZpbGw9IiNGRjVDMTYiLz4KPHBhdGggZD0iTTE0LjU5NjYgMTguNTU2NUwxMy42MDA5IDEyLjEzMzNMNy4yMjY5MiAxNi41MDA5TDcuMjIzNjMgMTYuNDk5M1YxNi41MDI1TDcuMjQzMzUgMjAuOTk4M0w5LjgyODA5IDE4LjU1NjVIOS44Mjk3NEgxNC41OTY2WiIgZmlsbD0iI0ZGNUMxNiIvPgo8cGF0aCBkPSJNMzIuNzA3NyAwLjMxMjI1NkwyMC45MzI2IDcuMzIwNDdMMjEuMzk5MyAxMi4xMzAxTDMyLjcwNzcgMC4zMTIyNTZaIiBmaWxsPSIjRkY1QzE2Ii8+CjxwYXRoIGQ9Ik0yNS4xNzIyIDI1LjA1MjJMMTkuOTkxMiAyOC45ODExTDI1LjE3MjIgMzAuNTE3NVYyNS4wNTIyWiIgZmlsbD0iI0ZGNUMxNiIvPgo8cGF0aCBkPSJNMjcuNzc2NiAxNi41MDI1SDI3Ljc3ODNIMjcuNzc2NlYxNi40OTkzTDI3Ljc3NSAxNi41MDA5TDIxLjQwMSAxMi4xMzMzTDIwLjQwNTMgMTguNTU2NUgyNS4xNzIyTDI3Ljc1ODYgMjAuOTk4M0wyNy43NzY2IDE2LjUwMjVaIiBmaWxsPSIjRkY1QzE2Ii8+CjxwYXRoIGQ9Ik05LjgyNzkzIDMwLjUxNzVMMi4yOTIyNSAzMi43NTIyTDAgMjUuMDUyMkg5LjgyNzkzVjMwLjUxNzVaIiBmaWxsPSIjRTM0ODA3Ii8+CjxwYXRoIGQ9Ik0xNC41OTQ3IDE4LjU1NDlMMTYuMDM0MSAyNy44NDA2TDE0LjAzOTMgMjIuNjc3N0w3LjIzOTc1IDIwLjk5ODRMOS44MjYxMyAxOC41NTQ5SDE0LjU5M0gxNC41OTQ3WiIgZmlsbD0iI0UzNDgwNyIvPgo8cGF0aCBkPSJNMjUuMTcyMSAzMC41MTc1TDMyLjcwNzggMzIuNzUyMkwzNS4wMDAxIDI1LjA1MjJIMjUuMTcyMVYzMC41MTc1WiIgZmlsbD0iI0UzNDgwNyIvPgo8cGF0aCBkPSJNMjAuNDA1MyAxOC41NTQ5TDE4Ljk2NTggMjcuODQwNkwyMC45NjA3IDIyLjY3NzdMMjcuNzYwMiAyMC45OTg0TDI1LjE3MjIgMTguNTU0OUgyMC40MDUzWiIgZmlsbD0iI0UzNDgwNyIvPgo8cGF0aCBkPSJNMCAyNS4wNDg4TDIuMjkyMjUgMTYuNDk5M0g3LjIyMTgzTDcuMjM5OTEgMjAuOTk2N0wxNC4wMzk0IDIyLjY3NkwxNi4wMzQzIDI3LjgzODlMMTUuMDA4OSAyOC45NzZMOS44Mjc5MyAyNS4wNDcySDBWMjUuMDQ4OFoiIGZpbGw9IiNGRjhENUQiLz4KPHBhdGggZD0iTTM1LjAwMDEgMjUuMDQ4OEwzMi43MDc4IDE2LjQ5OTNIMjcuNzc4M0wyNy43NjAyIDIwLjk5NjdMMjAuOTYwNyAyMi42NzZMMTguOTY1OCAyNy44Mzg5TDE5Ljk5MTIgMjguOTc2TDI1LjE3MjIgMjUuMDQ3MkgzNS4wMDAxVjI1LjA0ODhaIiBmaWxsPSIjRkY4RDVEIi8+CjxwYXRoIGQ9Ik0yMC45MzI1IDcuMzE1NDNIMTcuNDk5OUgxNC4wNjczTDEzLjYwMDYgMTIuMTI1MUwxNi4wMzQyIDI3LjgzNEgxOC45NjU2TDIxLjQwMDggMTIuMTI1MUwyMC45MzI1IDcuMzE1NDNaIiBmaWxsPSIjRkY4RDVEIi8+CjxwYXRoIGQ9Ik0yLjI5MjI1IDAuMzEyMjU2TDAgOS4yNzA5NEwyLjI5MjI1IDE2LjQ5OTNINy4yMjE4M0wxMy41OTkxIDEyLjEzMDFMMi4yOTIyNSAwLjMxMjI1NloiIGZpbGw9IiM2NjE4MDAiLz4KPHBhdGggZD0iTTEzLjE3IDIwLjQxOTlIMTAuOTM2OUw5LjcyMDk1IDIxLjYwNjJMMTQuMDQwOSAyMi42NzI3TDEzLjE3IDIwLjQxODJWMjAuNDE5OVoiIGZpbGw9IiM2NjE4MDAiLz4KPHBhdGggZD0iTTMyLjcwNzcgMC4zMTIyNTZMMzQuOTk5OSA5LjI3MDk0TDMyLjcwNzcgMTYuNDk5M0gyNy43NzgxTDIxLjQwMDkgMTIuMTMwMUwzMi43MDc3IDAuMzEyMjU2WiIgZmlsbD0iIzY2MTgwMCIvPgo8cGF0aCBkPSJNMjEuODMzIDIwLjQxOTlIMjQuMDY5NEwyNS4yODUzIDIxLjYwNzlMMjAuOTYwNCAyMi42NzZMMjEuODMzIDIwLjQxODJWMjAuNDE5OVoiIGZpbGw9IiM2NjE4MDAiLz4KPHBhdGggZD0iTTE5LjQ4MTcgMzAuODM2MkwxOS45OTExIDI4Ljk3OTRMMTguOTY1OCAyNy44NDIzSDE2LjAzMjdMMTUuMDA3MyAyOC45Nzk0TDE1LjUxNjcgMzAuODM2MiIgZmlsbD0iIzY2MTgwMCIvPgo8cGF0aCBkPSJNMTkuNDgxNiAzMC44MzU5VjMzLjkwMjFIMTUuNTE2NlYzMC44MzU5SDE5LjQ4MTZaIiBmaWxsPSIjQzBDNENEIi8+CjxwYXRoIGQ9Ik05LjgyOTU5IDMwLjUxNDJMMTUuNTIgMzMuOTAwOFYzMC44MzQ2TDE1LjAxMDYgMjguOTc3OEw5LjgyOTU5IDMwLjUxNDJaIiBmaWxsPSIjRTdFQkY2Ii8+CjxwYXRoIGQ9Ik0yNS4xNzIxIDMwLjUxNDJMMTkuNDgxNyAzMy45MDA4VjMwLjgzNDZMMTkuOTkxMSAyOC45Nzc4TDI1LjE3MjEgMzAuNTE0MloiIGZpbGw9IiNFN0VCRjYiLz4KPC9zdmc+Cg==">
                                <span>MetaMask</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- WALLET PANEL -->
                <div id="walletPanel" class="wallet-panel">
                    <!-- Confirm disconnect overlay -->
                    <div class="confirm-overlay" id="confirmOverlay">
                        <div class="confirm-icon">X</div>
                        <h3>Hủy kết nối ví?</h3>
                        <p>Bạn sẽ cần kết nối lại để thực hiện giao dịch.</p>
                        <div class="confirm-btns">
                            <button class="btn-cancel" onclick="hideConfirm()">Hủy bỏ</button>
                            <button class="btn-confirm-dc" onclick="confirmDisconnect()">Xác nhận</button>
                        </div>
                    </div>
                    <div class="panel-header">
                        <div class="panel-addr-row">
                            <div class="panel-avatar"></div>
                            <div>
                                <div class="panel-addr" id="panelAddress">—</div>
                                <div class="panel-network">
                                    <span class="net-dot"></span> BNB Smart Chain
                                </div>
                            </div>
                        </div>
                        <button class="disconnect-btn" onclick="showConfirm()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <polyline points="16 17 21 12 16 7" />
                                <line x1="21" y1="12" x2="9" y2="12" />
                            </svg>
                            Ngắt kết nối
                        </button>
                    </div>
                    <!-- Balance card -->
                    <div class="balance-card">
                        <div class="balance-label">Số dư</div>
                        <div class="balance-amount" id="walletBalance">0.0000 BNB</div>
                        <div class="balance-usd" id="walletBalanceUSD">≈ $0.00 USD</div>
                        <div class="balance-usd" id="walletBalanceVND">≈ 0đ</div>
                        <div class="balance-change">
                            <b>●</b> BNB Smart Chain Testnet
                        </div>
                    </div>
                    <!-- Full address copy row -->
                    <div class="copy-row">
                        <b class="full-addr" id="fullAddress">—</b>
                        <button class="copy-btn" onclick="copyAddress()" title="Sao chép địa chỉ">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" />
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                            </svg>
                        </button>
                    </div>
                    <div class="wallet-actions">
                        <!-- hiển thị lịch sử thay đổi số dư ở đây -->
                    </div>
                </div>
                <!-- TOAST -->
                <div class="toast" id="toast">Đã sao chép địa chỉ</div>
            </div>
        </div>
        <!-- HEADER (search bar) -->
        <div class="header">
            <div class="header-inner">
                <a class="navbar-brand" href="/MantaMarket/public/index.php">
                    <img id="logo" src="/MantaMarket/img/new-logo.png" alt="Manta Marketplace Logo" />
                </a>
                <div class="search-bar">
                    <input class="search-input" type="text" placeholder="Manta bao ship 0Đ - Đăng ký ngay!">
                    <button class="search-btn">
                        <svg viewBox="0 0 24 24">
                            <path d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"
                                stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                <div class="cart-wrap" id="cartWrap"
                    onmouseenter="openCartDropdown()"
                    onmouseleave="closeCartDropdown()">
                    <div class="cart-btn" onclick="<?= isset($_SESSION['userId']) ? 'openCartDropdown()' : "requireLogin('Vui lòng đăng nhập để xem giỏ hàng!')" ?>">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6zM3 6h18M16 10a4 4 0 01-8 0" />
                        </svg>
                        <div class="cart-badge" id="cartBadge">0</div>
                    </div>
                    <div class="cart-dropdown" id="cartDropdown">
                        <div class="cart-dropdown-header">Sản Phẩm Mới Thêm</div>
                        <div class="cart-dropdown-items" id="cartDropdownItems">
                            <div class="cart-empty">Chưa có sản phẩm</div>
                        </div>
                        <div class="cart-dropdown-footer">
                            <span id="cartFooterCount">0 Thêm Hàng Vào Giỏ</span>
                            <button class="btn-view-cart" href="javascript:void(0)" onclick="openMyAccountPanel('cart')">Xem Giỏ Hàng</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="search-tags">
                <a href="#"> Tìm kiếm ngay! </a>
                <a href="#"> </a>
            </div>
        </div>
    </div>