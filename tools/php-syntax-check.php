<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$excludedDirectories = [
    '.codex-tmp',
    '.git',
    '.idea',
    '.phpunit.cache',
    '.vscode',
    'node_modules',
    'runtime',
    'vendor',
];
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file) use ($excludedDirectories): bool {
            return !$file->isDir() || !in_array($file->getFilename(), $excludedDirectories, true);
        }
    )
);

foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

$errors = [];
foreach ($files as $file) {
    try {
        token_get_all((string)file_get_contents($file), TOKEN_PARSE);
    } catch (ParseError $error) {
        $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
        $errors[] = $relative . ': ' . $error->getMessage();
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("PHP syntax check passed (%d files).%s", count($files), PHP_EOL));
