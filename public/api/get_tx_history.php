<?php
header('Content-Type: application/json');

$wallet = trim($_GET['wallet'] ?? '');
if (!$wallet || !preg_match('/^0x[0-9a-fA-F]{40}$/i', $wallet)) {
    echo json_encode(['success' => false, 'message' => 'Địa chỉ ví không hợp lệ']);
    exit;
}

define('MORALIS_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJub25jZSI6IjE5NTQxMDA3LWVhZDAtNGY0NS04ZTYxLTdlNDUzMDBlZWQ4YyIsIm9yZ0lkIjoiNTE0ODczIiwidXNlcklkIjoiNTI5ODEwIiwidHlwZUlkIjoiZmMwOTlmMzItMWYwYS00ZDZhLThmNTAtZDc1NWUxZWRkNDcxIiwidHlwZSI6IlBST0pFQ1QiLCJpYXQiOjE3NzgzMTgwMzAsImV4cCI6NDkzNDA3ODAzMH0.s9IJTyO4ufQH95WSJmTeQyanni-LoK5NlsKjZbGngL0');
$limit = min((int)($_GET['limit'] ?? 30), 60);

$url = 'https://deep-index.moralis.io/api/v2.2/' . urlencode($wallet)
     . '?chain=bsc%20testnet&limit=' . $limit;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'X-API-Key: ' . MORALIS_API_KEY,
        'Accept: application/json',
    ],
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối Moralis: ' . $err]);
    exit;
}

$data = json_decode($raw, true);

if (!isset($data['result'])) {
    echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Lỗi Moralis API']);
    exit;
}

if (empty($data['result'])) {
    echo json_encode(['success' => true, 'txs' => [], 'note' => 'Chưa có giao dịch nào']);
    exit;
}

$wallet_lc = strtolower($wallet);
$txs = array_map(function ($tx) use ($wallet_lc) {
    $value_bnb = bcdiv($tx['value'] ?? '0', bcpow('10', '18', 0), 8);
    $direction = strtolower($tx['from_address'] ?? '') === $wallet_lc ? 'out' : 'in';
    $ts        = strtotime($tx['block_timestamp'] ?? 'now');

    return [
        'hash'       => $tx['hash'],
        'hash_short' => substr($tx['hash'], 0, 8) . '…' . substr($tx['hash'], -6),
        'from'       => $tx['from_address'],
        'to'         => $tx['to_address'],
        'value_bnb'  => $value_bnb,
        'direction'  => $direction,
        'timestamp'  => $ts,
        'status'     => ($tx['receipt_status'] ?? '1') === '0' ? 'failed' : 'success',
        'gas_used'   => $tx['receipt_gas_used'] ?? '0',
        'input'      => $tx['input'] ?? '0x',  
    ];
}, $data['result']);

echo json_encode(['success' => true, 'txs' => $txs]);