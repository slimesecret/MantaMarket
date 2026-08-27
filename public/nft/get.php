<?php
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
header('Access-Control-Allow-Origin: *');

// Dùng cùng regex với mint_nft.php khi tạo fileKey
$id   = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['id'] ?? '');
$file = __DIR__ . '/' . $id . '.json';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    // Debug: liệt kê file có trong thư mục
    $files = glob(__DIR__ . '/*.json');
    echo json_encode([
        'error'      => 'Not found',
        'requested'  => $id,
        'available'  => array_map('basename', $files)
    ]);
}