<?php
// ajax_edit.php — Xử lý cập nhật từng section của sản phẩm
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json');

// ── Dùng singleton từ DbConnection ──
$db  = new app_Libs_DbConnection();
$pdo = $db->connect();

// ── Helpers ──
function ok(array $extra = []): void {
    echo json_encode(array_merge(['status' => 'success'], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}
function err(string $msg): void {
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit();
}
// Lấy POST string đã trim
function ip(string $k): string  { return trim($_POST[$k] ?? ''); }
// Lấy POST int
function ii(string $k): int     { return intval($_POST[$k] ?? 0); }
// Lấy POST float (loại bỏ dấu phẩy nghìn)
function ff(string $k): float   { return floatval(str_replace(',', '', $_POST[$k] ?? 0)); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed');

$section    = ip('section');
$product_id = ii('product_id');
if (!$product_id) err('Thiếu product_id');

// ══════════════════════════════════════════════════════════════
//  SECTION: basic — Thông tin cơ bản (bảng products)
// ══════════════════════════════════════════════════════════════
if ($section === 'basic') {
    $name        = ip('name');
    $slug        = ip('slug');
    $description = ip('description');
    $base_price  = ff('base_price');
    $brand_id    = ii('brand_id')  ?: null; // 0 → null
    $seller_id   = ii('seller_id');
    $status      = ip('status');
    $is_featured = ii('is_featured');

    if (!$name || !$slug || !$seller_id) err('Vui lòng điền đủ tên, slug và seller.');
    if (!in_array($status, ['draft', 'active', 'inactive', 'banned'])) $status = 'draft';

    // Kiểm tra slug trùng (loại trừ chính nó)
    $chk = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
    $chk->execute([$slug, $product_id]);
    if ($chk->fetch()) err('Slug đã tồn tại trong hệ thống.');

    $pdo->prepare("
        UPDATE products SET
            name        = ?,
            slug        = ?,
            description = ?,
            base_price  = ?,
            brand_id    = ?,
            seller_id   = ?,
            status      = ?,
            is_featured = ?
        WHERE id = ?
    ")->execute([$name, $slug, $description, $base_price, $brand_id, $seller_id, $status, $is_featured, $product_id]);

    ok(['name' => $name, 'slug' => $slug]);
}

// ══════════════════════════════════════════════════════════════
//  SECTION: variants — Biến thể (bảng product_variants)
//  Nhận JSON array qua POST['variants_json']
// ══════════════════════════════════════════════════════════════
if ($section === 'variants') {
    $variants = json_decode(ip('variants_json'), true);
    if (!is_array($variants)) err('Dữ liệu biến thể không hợp lệ.');

    $stmt = $pdo->prepare("
        UPDATE product_variants SET
            sku             = ?,
            color           = ?,
            size            = ?,
            material        = ?,
            price           = ?,
            compare_price   = ?,
            stock_quantity  = ?,
            low_stock_alert = ?,
            is_active       = ?
        WHERE id = ? AND product_id = ?
    ");

    foreach ($variants as $v) {
        $vid = intval($v['id'] ?? 0);
        if (!$vid) continue;

        $stmt->execute([
            trim($v['sku']      ?? ''),
            trim($v['color']    ?? ''),
            trim($v['size']     ?? ''),
            trim($v['material'] ?? ''),
            max(0, floatval(str_replace(',', '', $v['price']         ?? 0))),
            max(0, floatval(str_replace(',', '', $v['compare_price'] ?? 0))),
            max(0, intval($v['stock_quantity']  ?? 0)),
            max(0, intval($v['low_stock_alert'] ?? 5)),
            intval($v['is_active'] ?? 1),
            $vid,
            $product_id,
        ]);
    }
    ok();
}

// ══════════════════════════════════════════════════════════════
//  SECTION: attributes — Thông số kỹ thuật (bảng product_attributes)
//  Chiến lược: DELETE toàn bộ rồi INSERT lại (giữ thứ tự)
// ══════════════════════════════════════════════════════════════
if ($section === 'attributes') {
    $attrs = json_decode(ip('attributes_json'), true);
    if (!is_array($attrs)) err('Dữ liệu thuộc tính không hợp lệ.');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$product_id]);

        $ins = $pdo->prepare("
            INSERT INTO product_attributes (product_id, attr_name, attr_value, sort_order)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($attrs as $i => $a) {
            $name  = trim($a['attr_name']  ?? '');
            $value = trim($a['attr_value'] ?? '');
            if (!$name || !$value) continue;
            $ins->execute([$product_id, $name, $value, $i]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        err('Lỗi khi lưu thuộc tính: ' . $e->getMessage());
    }
    ok();
}

// ══════════════════════════════════════════════════════════════
//  SECTION: tags — Tags (bảng product_tags)
// ══════════════════════════════════════════════════════════════
if ($section === 'tags') {
    $tags = json_decode(ip('tags_json'), true);
    if (!is_array($tags)) err('Dữ liệu tags không hợp lệ.');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$product_id]);

        $ins = $pdo->prepare("INSERT INTO product_tags (product_id, tag) VALUES (?, ?)");
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag) $ins->execute([$product_id, $tag]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        err('Lỗi khi lưu tags: ' . $e->getMessage());
    }
    ok();
}

// ══════════════════════════════════════════════════════════════
//  SECTION: image_delete / image_set_primary
// ══════════════════════════════════════════════════════════════
if ($section === 'image_delete') {
    $img_id = ii('image_id');
    if (!$img_id) err('Thiếu image_id');

    $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?")
        ->execute([$img_id, $product_id]);

    ok(['deleted' => $img_id]);
}

if ($section === 'image_set_primary') {
    $img_id = ii('image_id');
    if (!$img_id) err('Thiếu image_id');

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")
            ->execute([$product_id]);
        $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?")
            ->execute([$img_id, $product_id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        err('Lỗi: ' . $e->getMessage());
    }
    ok(['primary_id' => $img_id]);
}
if ($section === 'variant_add') {
    $price = max(0, floatval(str_replace(',', '', $_POST['price'] ?? 0)));
    if (!$price) err('Giá bán không được để trống hoặc bằng 0');

    $stmt = $pdo->prepare("
        INSERT INTO product_variants
            (product_id, sku, color, size, material, price, compare_price, stock_quantity, low_stock_alert, is_active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $product_id,
        trim($_POST['sku']      ?? ''),
        trim($_POST['color']    ?? ''),
        trim($_POST['size']     ?? ''),
        trim($_POST['material'] ?? ''),
        $price,
        max(0, floatval(str_replace(',', '', $_POST['compare_price']  ?? 0))),
        max(0, intval($_POST['stock_quantity']  ?? 0)),
        max(1, intval($_POST['low_stock_alert'] ?? 5)),
    ]);

    ok(['new_id' => $pdo->lastInsertId()]);
}
// ══════════════════════════════════════════════════════════════
//  SECTION: image_upload — Upload ảnh mới cho sản phẩm
// ══════════════════════════════════════════════════════════════
if ($section === 'image_upload') {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        err('Không nhận được file ảnh');
    }

    $file      = $_FILES['image'];
    $color     = ip('color');      // màu gắn với ảnh (có thể rỗng)
    $isPrimary = ii('is_primary'); // 0 hoặc 1

    // Validate mime
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) err('Định dạng ảnh không hợp lệ');
    if ($file['size'] > 5 * 1024 * 1024) err('Ảnh quá lớn (tối đa 5MB)');

    // Tạo thư mục lưu
    $uploadDir = dirname(__DIR__, 2) . '/public/uploads/products/' . $product_id . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // Tên file an toàn
    $ext      = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg'
    };
    $filename = uniqid('img_', true) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        err('Không thể lưu file');
    }

    // URL public
    $imageUrl = '/MantaMarket/public/uploads/products/' . $product_id . '/' . $filename;

    // Tìm variant_id tương ứng với màu (nếu có)
    $variantId = null;
    if ($color) {
        $vStmt = $pdo->prepare(
            "SELECT id FROM product_variants WHERE product_id = ? AND color = ? LIMIT 1"
        );
        $vStmt->execute([$product_id, $color]);
        $variantId = $vStmt->fetchColumn() ?: null;
    }

    // Nếu set làm primary → reset các ảnh khác
    if ($isPrimary) {
        $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")
            ->execute([$product_id]);
    }

    // sort_order = max hiện tại + 1
    $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id = ?");
    $maxOrder->execute([$product_id]);
    $sortOrder = (int)$maxOrder->fetchColumn() + 1;

    $ins = $pdo->prepare("
        INSERT INTO product_images (product_id, variant_id, image_url, sort_order, is_primary)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([$product_id, $variantId, $imageUrl, $sortOrder, $isPrimary]);
    $newImgId = $pdo->lastInsertId();

    ok([
        'image' => [
            'id'            => (int)$newImgId,
            'image_url'     => $imageUrl,
            'variant_color' => $color,
            'is_primary'    => $isPrimary,
            'sort_order'    => $sortOrder,
        ]
    ]);
}
err('Section không hợp lệ: ' . $section);