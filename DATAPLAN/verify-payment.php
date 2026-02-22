<?php
// verify-payment.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 🔴 LIVE Paystack secret key (KEEP THIS SECURE ON SERVER)
$secret_key = ' ';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference'] ?? '';
$phone = $input['phone'] ?? '';
$bundle = $input['bundle'] ?? '';
$amount = $input['amount'] ?? 0;

if (empty($reference)) {
    echo json_encode(['status' => 'error', 'message' => 'No reference provided']);
    exit;
}

// Verify transaction with Paystack
$url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $secret_key",
    "Cache-Control: no-cache"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to verify payment']);
    exit;
}

$result = json_decode($response, true);

if ($result['status'] && $result['data']['status'] === 'success') {
    // Payment verified successfully
    $paid_amount = $result['data']['amount'] / 100; // Convert from pesewas
    $expected_amount = floatval($amount);
    
    // Verify amount matches (prevents tampering)
    if (abs($paid_amount - $expected_amount) > 0.01) {
        echo json_encode(['status' => 'error', 'message' => 'Amount mismatch']);
        exit;
    }
    
    // Get payment details
    $payment_method = $result['data']['channel'];
    $customer_email = $result['data']['customer']['email'] ?? '';
    $transaction_date = $result['data']['paid_at'] ?? date('Y-m-d H:i:s');
    
    // TODO: Save to your database
    /*
    $db = new PDO('mysql:host=localhost;dbname=your_db', 'username', 'password');
    $stmt = $db->prepare("INSERT INTO transactions (reference, phone, bundle, amount, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())");
    $stmt->execute([$reference, $phone, $bundle, $paid_amount, $payment_method]);
    */
    
    // TODO: Trigger data delivery via your API
    // sendDataToPhone($phone, $bundle);
    
    // TODO: Send SMS confirmation
    // sendSMS($phone, "Your $bundle data bundle has been delivered successfully. Thank you for choosing K.K Affordable Data!");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Payment verified successfully',
        'data' => [
            'reference' => $reference,
            'amount' => $paid_amount,
            'phone' => $phone,
            'bundle' => $bundle,
            'payment_method' => $payment_method,
            'transaction_date' => $transaction_date
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Payment verification failed']);
}
?>