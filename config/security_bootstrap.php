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
