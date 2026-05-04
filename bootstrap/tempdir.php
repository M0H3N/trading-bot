<?php

declare(strict_types=1);

/**
 * Force PHP's temp directory into the app storage so tempnam() does not fall back
 * to /tmp (which can trigger notices, open_basedir issues, or non-writable paths on Linux).
 */
$tmpDir = dirname(__DIR__).'/storage/framework/tmp';

if (! is_dir($tmpDir)) {
    @mkdir($tmpDir, 0755, true);
}

if (! is_dir($tmpDir) || ! is_writable($tmpDir)) {
    return;
}

putenv('TMPDIR='.$tmpDir);
$_ENV['TMPDIR'] = $tmpDir;
$_SERVER['TMPDIR'] = $tmpDir;
