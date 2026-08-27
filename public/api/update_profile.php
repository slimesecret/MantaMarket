<?php
session_start();
header('Content-Type: application/json');

$current_user_id = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'mantamarket');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối DB']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── 1. CẬP NHẬT THÔNG TIN CƠ BẢN ──
// Thay toàn bộ block action === 'update_info':
if ($action === 'update_info') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $new_username = trim($_POST['username'] ?? '');

    // Kiểm tra phone trùng
    if ($phone) {
        $check = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $check->execute([$phone, $current_user_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Số điện thoại đã được sử dụng!']);
            exit;
        }
    }

    // Lấy username hiện tại
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_username = $row['username'] ?? null;

    if ($new_username && $new_username !== $current_username) {
        // Nếu đã có username rồi → không cho đổi
        if (!empty($current_username)) {
            echo json_encode(['success' => false, 'message' => 'Tên đăng nhập không thể thay đổi!']);
            exit;
        }
        // Kiểm tra trùng
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$new_username, $current_user_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Tên đăng nhập đã được sử dụng!']);
            exit;
        }
        // Đặt username lần đầu
        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$new_username, $full_name, $phone, $current_user_id]);
        $_SESSION['username'] = $new_username;
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $current_user_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công!']);
    exit;
}
if ($action === 'request_cancel') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');

    if (!$order_id || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Thiếu thông tin!']);
        exit;
    }

    // Kiểm tra đơn thuộc về user và đang ở trạng thái có thể hủy
    $stmt = $pdo->prepare("
        SELECT id FROM orders
        WHERE id = ? AND user_id = ?
        AND order_status IN ('pending','confirmed','processing')
        LIMIT 1
    ");
    $stmt->execute([$order_id, $current_user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không hợp lệ!']);
        exit;
    }

    // Kiểm tra đã có yêu cầu pending chưa
    $stmt = $pdo->prepare("
        SELECT id FROM cancel_requests
        WHERE order_id = ? AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã gửi yêu cầu hủy, đang chờ duyệt!']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO cancel_requests (order_id, user_id, reason)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$order_id, $current_user_id, $reason]);

    echo json_encode(['success' => true, 'message' => 'Đã gửi yêu cầu hủy đơn, chờ admin duyệt!']);
    exit;
}
// ── 2. CẬP NHẬT EMAIL ──
if ($action === 'update_email') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ!']);
        exit;
    }
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $current_user_id]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email đã được sử dụng!']);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email, $current_user_id]);
    echo json_encode(['success' => true, 'message' => 'Cập nhật email thành công!']);
    exit;
}
// ── GET ADDRESS (dùng cho modal edit) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_address') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $current_user_id]);
    $addr = $stmt->fetch();
    if (!$addr) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy địa chỉ!']);
        exit;
    }
    echo json_encode(['success' => true, 'address' => $addr]);
    exit;
}

// ── CẬP NHẬT ĐỊA CHỈ ──
if ($action === 'edit_address') {
    $id           = (int)($_POST['id'] ?? 0);
    $full_name    = trim($_POST['full_name']    ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $province     = trim($_POST['province']     ?? '');
    $district     = trim($_POST['district']     ?? '');
    $ward         = trim($_POST['ward']         ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $is_default   = (int)($_POST['is_default']  ?? 0);

    if (!$id || !$full_name || !$phone || !$province || !$district || !$ward || !$address_line) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin!']);
        exit;
    }

    // Kiểm tra địa chỉ thuộc user
    $check = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $check->execute([$id, $current_user_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Không có quyền!']);
        exit;
    }

    if ($is_default) {
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")
            ->execute([$current_user_id]);
    }

    $stmt = $pdo->prepare("
        UPDATE user_addresses
        SET full_name=?, phone=?, province=?, district=?, ward=?, address_line=?, is_default=?
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$full_name, $phone, $province, $district, $ward, $address_line, $is_default, $id, $current_user_id]);

    echo json_encode(['success' => true, 'message' => 'Cập nhật địa chỉ thành công!']);
    exit;
}

// ── XÓA ĐỊA CHỈ ──
if ($action === 'delete_address') {
    $id = (int)($_POST['id'] ?? 0);

    // Không cho xóa địa chỉ mặc định
    $check = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
    $check->execute([$id, $current_user_id]);
    $addr = $check->fetch();
    if (!$addr) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy địa chỉ!']);
        exit;
    }
    if ($addr['is_default']) {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa địa chỉ mặc định!']);
        exit;
    }

    $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?")
        ->execute([$id, $current_user_id]);

    echo json_encode(['success' => true, 'message' => 'Đã xóa địa chỉ!']);
    exit;
}

// ── THIẾT LẬP MẶC ĐỊNH ──
if ($action === 'set_default_address') {
    $id = (int)($_POST['id'] ?? 0);

    $check = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
    $check->execute([$id, $current_user_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy địa chỉ!']);
        exit;
    }

    $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")
        ->execute([$current_user_id]);
    $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")
        ->execute([$id, $current_user_id]);

    echo json_encode(['success' => true, 'message' => 'Đã thiết lập địa chỉ mặc định!']);
    exit;
}
// ── 5. THÊM ĐỊA CHỈ ──
// ── 5. THÊM ĐỊA CHỈ ──
if ($action === 'add_address') {
    $full_name    = trim($_POST['full_name']    ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $province     = trim($_POST['province']     ?? '');
    $district     = trim($_POST['district']     ?? '');
    $ward         = trim($_POST['ward']         ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $is_default   = (int)($_POST['is_default']  ?? 0);

    if (!$full_name || !$phone || !$province || !$district || !$ward || !$address_line) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin!']);
        exit;
    }

    // Nếu set mặc định → bỏ mặc định cũ
    if ($is_default) {
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")
            ->execute([$current_user_id]);
    }

    // Nếu chưa có địa chỉ nào → tự động đặt mặc định
    $count = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
    $count->execute([$current_user_id]);
    if ($count->fetchColumn() == 0) $is_default = 1;

    $stmt = $pdo->prepare("
        INSERT INTO user_addresses (user_id, full_name, phone, province, district, ward, address_line, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$current_user_id, $full_name, $phone, $province, $district, $ward, $address_line, $is_default]);

    echo json_encode([
        'success' => true,
        'message' => 'Thêm địa chỉ thành công!',
        'new_id'  => (int)$pdo->lastInsertId(),
        'is_default' => $is_default  // trả về is_default thực tế (có thể bị đổi nếu chưa có địa chỉ)
    ]);
    exit;
}
// ── 3. UPLOAD AVATAR ──
if ($action === 'update_avatar') {
    if (empty($_FILES['avatar'])) {
        echo json_encode(['success' => false, 'message' => 'Không có file!']);
        exit;
    }
    $file     = $_FILES['avatar'];
    $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize  = 1 * 1024 * 1024; // 1MB

    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận JPEG, PNG, WEBP!']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File quá lớn (tối đa 1MB)!']);
        exit;
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatars/' . $current_user_id . '_' . time() . '.' . $ext;
    $dest = __DIR__ . '/../../img/' . $filename;

    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'Lỗi upload file!']);
        exit;
    }

    $avatar_path = '/MantaMarket/img/' . $filename;
    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$avatar_path, $current_user_id]);
    $_SESSION['avatar'] = $avatar_path;

    echo json_encode(['success' => true, 'message' => 'Cập nhật ảnh thành công!', 'avatar' => $avatar_path]);
    exit;
}
// ── 4. ĐỔI / ĐẶT MẬT KHẨU ──
if ($action === 'update_password') {
    $new_password = trim($_POST['new_password'] ?? '');
    $old_password = trim($_POST['old_password'] ?? '');

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự!']);
        exit;
    }

    // Lấy password hiện tại
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_password = $row['password'] ?? null;

    $has_password = !empty($current_password);

    if ($has_password) {
        // Đã có password → xác minh password cũ (plain text)
        if (empty($old_password)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mật khẩu hiện tại!']);
            exit;
        }
        if ($old_password !== $current_password) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng!']);
            exit;
        }
    }

    // Lưu plain text
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_password, $current_user_id]);

    $msg = $has_password ? 'Đổi mật khẩu thành công!' : 'Đặt mật khẩu thành công!';
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
