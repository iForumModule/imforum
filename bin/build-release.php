#!/usr/bin/env php
<?php
/**
 * Builds dist/imforum-<version>.zip: the module folder `imforum` and the redirect stub `iforum`.
 *
 * Cross-platform (Windows, Linux, macOS); needs only PHP 8 with the zip extension.
 * Usage: php bin/build-release.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);

/** Reads the module version, with spaces replaced by dashes (1.0.0 Alpha 8 -> 1.0.0-Alpha-8). */
function readVersion(string $root): string
{
    $source = file_get_contents($root . '/src/icms_version.php');
    if ($source === false || !preg_match("/'status_version' => '([^']+)'/", $source, $m)) {
        throw new RuntimeException('Cannot read status_version from src/icms_version.php');
    }

    return str_replace(' ', '-', $m[1]);
}

/**
 * Adds a directory tree (or single file) to the archive under $target, with forward slashes.
 */
function addTree(ZipArchive $zip, string $source, string $target): void
{
    if (is_file($source)) {
        if (!$zip->addFile($source, $target)) {
            throw new RuntimeException("Cannot add $source");
        }
        return;
    }

    $zip->addEmptyDir($target);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $entries = [];
    foreach ($iterator as $item) {
        $entries[str_replace('\\', '/', $iterator->getSubPathname())] = $item->isDir();
    }
    ksort($entries);
    foreach ($entries as $relative => $isDir) {
        $path = $source . '/' . $relative;
        $ok = $isDir ? $zip->addEmptyDir("$target/$relative") : $zip->addFile($path, "$target/$relative");
        if (!$ok) {
            throw new RuntimeException("Cannot add $path");
        }
    }
}

try {
    $version = readVersion($root);
    $distDir = $root . '/dist';
    if (!is_dir($distDir) && !mkdir($distDir, 0777, true) && !is_dir($distDir)) {
        throw new RuntimeException("Cannot create $distDir");
    }

    $out = $distDir . '/imforum-' . $version . '.zip';
    if (is_file($out) && !unlink($out)) {
        throw new RuntimeException("Cannot remove old $out");
    }

    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create $out");
    }

    $zip->addEmptyDir('modules');
    addTree($zip, $root . '/src', 'modules/imforum');
    addTree($zip, $root . '/stub/iforum', 'modules/iforum');
    foreach (['LICENSE', 'README.md', 'CHANGELOG.md'] as $file) {
        addTree($zip, "$root/$file", "modules/imforum/$file");
    }
    addTree($zip, $root . '/docs', 'modules/imforum/docs');

    if (!$zip->close()) {
        throw new RuntimeException("Cannot write $out");
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Build failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $out, "\n";
