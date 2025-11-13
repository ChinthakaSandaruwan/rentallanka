<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Colombo');
}
$__s = function_exists('session_status') ? session_status() : PHP_SESSION_NONE;
if ($__s === PHP_SESSION_NONE) {
    session_start();
}
mb_internal_encoding('UTF-8');
header_remove('X-Powered-By');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$__log_dir = __DIR__ . '/../error';
if (!is_dir($__log_dir)) { @mkdir($__log_dir, 0777, true); }
$__log_file = $__log_dir . '/error.log';
ini_set('error_log', $__log_file);

set_error_handler(function ($severity, $message, $file = '', $line = 0) use ($__log_file) {
    if (!(error_reporting() & $severity)) { return false; }
    $entry = '[' . date('Y-m-d H:i:s') . "] PHP Error: {$message} in {$file}:{$line}\n";
    error_log($entry);
    return false;
});

set_exception_handler(function ($ex) use ($__log_file) {
    $entry = '[' . date('Y-m-d H:i:s') . '] Uncaught Exception: ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine() . "\n";
    error_log($entry);
});

register_shutdown_function(function () use ($__log_file) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $entry = '[' . date('Y-m-d H:i:s') . "] Fatal Error: {$e['message']} in {$e['file']}:{$e['line']}\n";
        error_log($entry);
    }
});
