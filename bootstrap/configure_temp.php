<?php

/**
 * Ensure PHP has a writable temp directory for multipart uploads.
 * On some Windows hosts sys_get_temp_dir() resolves to a non-writable path
 * (e.g. C:\WINDOWS), which makes every browser upload fail with
 * UPLOAD_ERR_NO_TMP_DIR and Laravel's "The excel file failed to upload."
 */
$tempDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'php-tmp';

if (! is_dir($tempDir)) {
    @mkdir($tempDir, 0775, true);
}

if (is_dir($tempDir) && is_writable($tempDir)) {
    putenv('TMP='.$tempDir);
    putenv('TEMP='.$tempDir);
    putenv('TMPDIR='.$tempDir);
    $_ENV['TMP'] = $tempDir;
    $_ENV['TEMP'] = $tempDir;
    $_ENV['TMPDIR'] = $tempDir;
    $_SERVER['TMP'] = $tempDir;
    $_SERVER['TEMP'] = $tempDir;
    $_SERVER['TMPDIR'] = $tempDir;
}
