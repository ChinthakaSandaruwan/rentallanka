<?php
include '../config/config.php';

// Read POST data
$merchant_id      = $_POST['merchant_id'];
$order_id         = $_POST['order_id'];
$payhere_amount   = $_POST['payhere_amount'];
$payhere_currency = $_POST['payhere_currency'];
$status_code      = $_POST['status_code'];
$md5sig           = $_POST['md5sig'];

// Validate the signature
$local_md5sig = strtoupper(
    md5(
        $merchant_id .
        $order_id .
        $payhere_amount .
        $payhere_currency .
        $status_code .
        strtoupper(md5($merchant_secret))
    )
);

// Only process if signature is valid and payment successful
if (($local_md5sig === $md5sig) && ($status_code == 2)) {
    // ✅ Payment Success
    // You can log or update your database here
    file_put_contents("payment_log.txt", "SUCCESS: Order $order_id | Amount: $payhere_amount $payhere_currency\n", FILE_APPEND);
} else {
    // ❌ Failed or invalid request
    file_put_contents("payment_log.txt", "FAILED: Order $order_id | Status: $status_code\n", FILE_APPEND);
}
?>
