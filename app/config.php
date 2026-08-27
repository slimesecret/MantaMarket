<?php
// Đường dẫn gốc của website (tính từ localhost)
define("BASE_URL", "/MantaMarket");
// Hàm tạo URL tuyệt đối (ví dụ: /WebProject/css/admin.css)
function asset($path) {
    return BASE_URL . "/" . ltrim($path, "/");
}
