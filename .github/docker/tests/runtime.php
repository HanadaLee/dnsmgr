<?php

declare(strict_types=1);

// CVE-2026-109618 and CVE-2026-109619 are fixed in PHP 8.3.36.
if (PHP_VERSION_ID < 80336 || PHP_VERSION_ID >= 80400) {
    fwrite(STDERR, 'Expected patched PHP 8.3 (>= 8.3.36), got ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

foreach (['curl', 'ftp', 'gd', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'sockets', 'ssh2', 'swoole', 'zip'] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, 'Missing runtime extension: ' . $extension . PHP_EOL);
        exit(1);
    }
}

printf("Runtime check passed: PHP %s, Swoole %s, architecture %s\n", PHP_VERSION, phpversion('swoole'), php_uname('m'));
