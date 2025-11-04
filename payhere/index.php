<?php
include '../config/config.php';

// Example Order Info
$order_id = uniqid("ORDER_");  // Generate unique order ID
$items = "Monthly Subscription";
$currency = "LKR";
$amount = 1000.00;
$recurrence = "1 Month";
$duration = "Forever";

// Customer Info
$first_name = "Saman";
$last_name = "Perera";
$email = "samanp@gmail.com";
$phone = "0771234567";
$address = "No.1, Galle Road";
$city = "Colombo";
$country = "Sri Lanka";

// Generate Hash
$hash = strtoupper(
    md5(
        $merchant_id .
        $order_id .
        number_format($amount, 2, '.', '') .
        $currency .
        strtoupper(md5($merchant_secret))
    )
);
?>

<!DOCTYPE html>
<html>
<head>
  <title>PayHere Recurring Payment</title>
</head>
<body>
  <h2>Recurring Payment Example</h2>
  <form method="post" action="<?= $payhere_url ?>">
    <input type="hidden" name="merchant_id" value="<?= $merchant_id ?>">
    <input type="hidden" name="return_url" value="<?= $return_url ?>">
    <input type="hidden" name="cancel_url" value="<?= $cancel_url ?>">
    <input type="hidden" name="notify_url" value="<?= $notify_url ?>">

    <input type="hidden" name="order_id" value="<?= $order_id ?>">
    <input type="hidden" name="items" value="<?= $items ?>">
    <input type="hidden" name="currency" value="<?= $currency ?>">
    <input type="hidden" name="recurrence" value="<?= $recurrence ?>">
    <input type="hidden" name="duration" value="<?= $duration ?>">
    <input type="hidden" name="amount" value="<?= number_format($amount, 2, '.', '') ?>">

    <input type="hidden" name="first_name" value="<?= $first_name ?>">
    <input type="hidden" name="last_name" value="<?= $last_name ?>">
    <input type="hidden" name="email" value="<?= $email ?>">
    <input type="hidden" name="phone" value="<?= $phone ?>">
    <input type="hidden" name="address" value="<?= $address ?>">
    <input type="hidden" name="city" value="<?= $city ?>">
    <input type="hidden" name="country" value="<?= $country ?>">

    <input type="hidden" name="hash" value="<?= $hash ?>">

    <button type="submit">Subscribe Now (LKR <?= number_format($amount, 2) ?>)</button>
  </form>
</body>
</html>


