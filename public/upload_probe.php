<?php
header('Content-Type: application/json');
echo json_encode([
    'files' => $_FILES,
    'post_keys' => array_keys($_POST),
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
    'tmp_writable' => is_writable(sys_get_temp_dir()),
    'tmp_dir' => sys_get_temp_dir(),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'file_uploads' => ini_get('file_uploads'),
], JSON_PRETTY_PRINT);
