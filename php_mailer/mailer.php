<?php
require_once __DIR__ . '/../PHPMailer-7.0.0/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-7.0.0/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-7.0.0/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailer_send(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'chinthakasw000@gmail.com';
        $mail->Password   = 'kbyz grwk sorx lyba';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('chinthakasw000@gmail.com', 'Rentallanka');
        $mail->addAddress($toEmail, $toName ?: '');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(preg_replace('/<br\s*\/>/i', "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[mailer_send] ' . $e->getMessage());
        return false;
    }
}
