<?php
require_once __DIR__ . '/PHPMailer-7.0.0/src/Exception.php';
require_once __DIR__ . '/PHPMailer-7.0.0/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-7.0.0/src/SMTP.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'chinthakasw000@gmail.com';
$mail->Password   = 'kbyz grwk sorx lyba'; // replace with env var in real code
$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // 'tls'
$mail->Port       = 587;

// Typical headers
$mail->setFrom('chinthakasw000@gmail.com', 'Your Name');
$mail->addAddress('recipient@example.com');
$mail->Subject = 'Test email';
$mail->Body    = 'Hello from PHPMailer + Gmail SMTP.';

// Send
$mail->send();

echo 'OK';
