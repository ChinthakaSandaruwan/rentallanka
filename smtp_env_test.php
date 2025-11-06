<?php
require_once __DIR__ . '/PHPMailer-7.0.0/src/Exception.php';
require_once __DIR__ . '/PHPMailer-7.0.0/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-7.0.0/src/SMTP.php';

$user = getenv('MAIL_USER');
$pass = getenv('MAIL_PASS');
$to   = isset($_GET['to']) ? $_GET['to'] : $user; // default send to self

if (!$user || !$pass) {
    http_response_code(500);
    echo 'Missing MAIL_USER/MAIL_PASS env vars';
    exit;
}

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = $user;
$mail->Password   = $pass;
$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;
$mail->CharSet    = 'UTF-8';

$mail->setFrom($user, 'Rentallanka');
$mail->addAddress($to ?: $user);
$mail->Subject = 'Env test email';
$mail->Body    = 'Hello from PHPMailer + Gmail SMTP via env vars.';

$mail->send();
echo 'OK';
