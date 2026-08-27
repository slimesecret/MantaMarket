<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('ngrok-skip-browser-warning: true');

$data        = json_decode(file_get_contents('php://input'), true);
$buyerWallet = $data['wallet']    ?? '';
$txHash      = $data['txHash']    ?? '';
$productId   = intval($data['productId'] ?? 1);
$image       = $data['image']     ?? '';

if (!$buyerWallet || !$txHash) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin']);
    exit;
}

// ── 1. Verify giao dịch testnet (bỏ qua nếu JS đã chờ confirmed) ──
$skipVerify = $data['skipVerify'] ?? false;
if (!$skipVerify) {
    $rpc     = 'https://data-seed-prebsc-1-s1.binance.org:8545/';
    $receipt = rpcCallWithRetry($rpc, $txHash);
    if (!$receipt) {
        echo json_encode(['success' => false, 'error' => 'Giao dịch chưa xác nhận']);
        exit;
    }
}

// ── 2. Thay localhost → ngrok TRƯỚC khi tạo metadata ──
$ngrokUrl = "https://reenter-joyfully-juvenile.ngrok-free.dev";

if (str_starts_with($image, 'http://localhost')) {
    $image = str_replace('http://localhost', $ngrokUrl, $image);
} elseif (str_starts_with($image, '/')) {
    // Relative path → thêm ngrok vào đầu
    $image = $ngrokUrl . $image;
} elseif (!str_starts_with($image, 'http')) {
    // Không có scheme → thêm ngrok + slash
    $image = $ngrokUrl . '/' . $image;
}

// ── 3. Tạo fileKey theo productId + color + size ──
$color   = $data['color'] ? substr(md5($data['color']), 0, 6) : '';
$size    = $data['size']  ? substr(md5($data['size']),  0, 6) : '';
$fileKey = $productId . ($color ? "_{$color}" : '') . ($size ? "_{$size}" : '');

// ── 4. Ghi DB + tạo NFT metadata ──
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=mantamarket;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS nft_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wallet VARCHAR(100),
        tx_hash VARCHAR(100) UNIQUE,
        product_id INT,
        status ENUM('pending','minted','failed') DEFAULT 'pending',
        token_id INT NULL,
        created_at DATETIME DEFAULT NOW()
    )");

    $stmt = $pdo->prepare("INSERT IGNORE INTO nft_orders (wallet, tx_hash, product_id) VALUES (?, ?, ?)");
    $stmt->execute([$buyerWallet, $txHash, $productId]);

    // ── Lấy thông tin sản phẩm ──
    $stmt2 = $pdo->prepare("SELECT name, base_price FROM products WHERE id = ?");
    $stmt2->execute([$productId]);
    $product = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($product && $image) {
        $nft = [
            "name"        => $product['name'],
            "description" => "NFT xác nhận mua hàng tại MantaMarket",
            "image"       => $image,
            "attributes"  => [
                ["trait_type" => "Product", "value" => $product['name']],
                ["trait_type" => "Color",   "value" => $data['color'] ?? ''],
                ["trait_type" => "Size",    "value" => $data['size']  ?? ''],
                ["trait_type" => "Price",   "value" => number_format($product['base_price']) . "đ"],
                ["trait_type" => "TxHash",  "value" => $txHash],
                ["trait_type" => "Store",   "value" => "MantaMarket"],
            ]
        ];

        $dir = $_SERVER['DOCUMENT_ROOT'] . '/MantaMarket/public/nft';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        file_put_contents(
            $dir . '/' . $fileKey . '.json',
            json_encode($nft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

} catch (Exception $e) {
    error_log('mint_nft DB error: ' . $e->getMessage());
}

// ── 5. Trả về kết quả ──
$tokenURI = "{$ngrokUrl}/MantaMarket/public/nft/get.php?id={$fileKey}";

echo json_encode([
    'success'  => true,
    'tokenURI' => $tokenURI,
    'message'  => 'NFT đã được tạo cho ví ' . $buyerWallet
]);

// ── Helpers ──
function rpcCallWithRetry(string $url, string $txHash, int $retries = 5): ?array
{
    for ($i = 0; $i < $retries; $i++) {
        $receipt = rpcCall($url, 'eth_getTransactionReceipt', [$txHash]);
        if ($receipt && ($receipt['status'] ?? '') === '0x1') {
            return $receipt;
        }
        if ($i < $retries - 1) sleep(3);
    }
    return null;
}

function rpcCall(string $url, string $method, array $params = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => json_encode([
            'jsonrpc' => '2.0', 'method' => $method,
            'params'  => $params, 'id'    => 1
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res['result'] ?? null;
}