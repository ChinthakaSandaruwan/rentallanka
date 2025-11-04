<?php
// ===============================
// PayHere Checkout Integration
// ===============================

// --- Configuration ---
$merchant_id     = "1224197"; // Replace with your Merchant ID
$merchant_secret = "Mzg1MDk0MTE4ODMyNDU4Mjc0OTMyNjI4MjI2Nzc3MzY5MTk2NzI4NQ=="; // Replace with your Merchant Secret
$currency        = "LKR";
$return_url      = "https://yourdomain.com/return.php";
$cancel_url      = "https://yourdomain.com/cancel.php";
$notify_url      = "https://yourdomain.com/notify.php";

// --- Example Order & Customer Details ---
$order_id   = uniqid("ORD_"); // Auto-generate a unique order ID
$items      = "Wireless Door Bell";
$amount     = 1000.00;

$first_name = "Saman";
$last_name  = "Perera";
$email      = "samanp@gmail.com";
$phone      = "0771234567";
$address    = "No.1, Galle Road";
$city       = "Colombo";
$country    = "Sri Lanka";

// --- Generate Hash ---
$amount_formatted = number_format($amount, 2, '.', '');
$hash = strtoupper(
    md5(
        $merchant_id .
        $order_id .
        $amount_formatted .
        $currency .
        strtoupper(md5($merchant_secret))
    )
);

// --- Sandbox PayHere URL ---
$payhere_url = "https://sandbox.payhere.lk/pay/checkout";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PayHere Checkout</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            text-align: center;
            padding-top: 100px;
        }
        form {
            display: inline-block;
            background: #fff;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
        }
        input[type="submit"] {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<h2>Checkout with PayHere</h2>
<p>Order ID: <strong><?php echo $order_id; ?></strong></p>
<p>Amount: <strong><?php echo number_format($amount, 2); ?> <?php echo $currency; ?></strong></p>

<form method="post" action="<?php echo $payhere_url; ?>">
    <!-- Required Parameters -->
    <input type="hidden" name="merchant_id" value="<?php echo $merchant_id; ?>">
    <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">
    <input type="hidden" name="cancel_url" value="<?php echo $cancel_url; ?>">
    <input type="hidden" name="notify_url" value="<?php echo $notify_url; ?>">

    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
    <input type="hidden" name="items" value="<?php echo $items; ?>">
    <input type="hidden" name="currency" value="<?php echo $currency; ?>">
    <input type="hidden" name="amount" value="<?php echo $amount_formatted; ?>">

    <input type="hidden" name="first_name" value="<?php echo $first_name; ?>">
    <input type="hidden" name="last_name" value="<?php echo $last_name; ?>">
    <input type="hidden" name="email" value="<?php echo $email; ?>">
    <input type="hidden" name="phone" value="<?php echo $phone; ?>">
    <input type="hidden" name="address" value="<?php echo $address; ?>">
    <input type="hidden" name="city" value="<?php echo $city; ?>">
    <input type="hidden" name="country" value="<?php echo $country; ?>">
    <input type="hidden" name="hash" value="<?php echo $hash; ?>">

    <input type="submit" value="Pay Now with PayHere">
</form>

</body>
</html>
