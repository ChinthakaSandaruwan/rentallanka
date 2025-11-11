<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error/error.log');

if (isset($_GET['show_errors']) && $_GET['show_errors'] == '1') {
    $f = __DIR__ . '/../error/error.log';
    if (is_readable($f)) {
        $lines = 100; $data = '';
        $fp = fopen($f, 'r');
        if ($fp) {
            fseek($fp, 0, SEEK_END); $pos = ftell($fp); $chunk = ''; $ln = 0;
            while ($pos > 0 && $ln <= $lines) {
                $step = max(0, $pos - 4096); $read = $pos - $step;
                fseek($fp, $step); $chunk = fread($fp, $read) . $chunk; $pos = $step;
                $ln = substr_count($chunk, "\n");
            }
            fclose($fp);
            $parts = explode("\n", $chunk); $slice = array_slice($parts, -$lines); $data = implode("\n", $slice);
        }
        header('Content-Type: text/plain; charset=utf-8'); echo $data; exit;
    }
}

require_once __DIR__ . '/config.php';

if (!defined('OTP_MODE')) {
    define('OTP_MODE', 'development');
}

function sendOtp(string $phone, string $otp, ?string $message = null): void {
    $enabled = (int)setting_get('otp_enabled', '1');
    if ($enabled !== 1) {
        echo "<div class='alert alert-warning' role='alert' style='margin:10px 0;'>OTP is currently disabled. Please try again later.</div>";
        @file_put_contents(__DIR__ . '/../error/otp_api.log', date('Y-m-d H:i:s') . ' -> OTP disabled, skipping send for phone=' . (string)$phone . PHP_EOL, FILE_APPEND);
        return;
    }
    $raw = $phone;
    $digits = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^07\d{8}$/', $digits)) {
        $display = $digits;
        $e164 = '+94' . substr($digits, 1);
    } elseif (preg_match('/^\+94\d{9}$/', $raw)) {
        $display = '0' . substr($raw, 3);
        $e164 = $raw;
    } else {
        $display = $raw;
        $e164 = $raw;
    }

    if (OTP_MODE === 'development') {
        $otpEsc = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $phEsc = htmlspecialchars($display, ENT_QUOTES, 'UTF-8');
        echo "<div style='padding:10px;background:#e7ffe7;border:1px solid #2ecc71;color:#2ecc71;margin:10px 0;'><strong>[DEV MODE]</strong> OTP for {$phEsc} is <strong>{$otpEsc}</strong></div>";
        return;
    }

    $msg = $message ?? ("Your Rentallanka OTP is: " . $otp);
    $response = smslenz_send_sms($e164, $msg);
    @file_put_contents(__DIR__ . '/../error/otp_api.log', date('Y-m-d H:i:s') . ' -> ' . json_encode($response, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}
