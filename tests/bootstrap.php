<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/common.php';

date_default_timezone_set('Asia/Shanghai');

$_SERVER['DOCUMENT_ROOT'] ??= dirname(__DIR__) . '/public';
$_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
