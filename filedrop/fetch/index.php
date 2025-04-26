<?php
declare(strict_types=1);

// print_r($_GET);

if (
    empty($_GET['uuid'])
    || !preg_match(
         '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
         $_GET['uuid'],
         $matches
      )
) {
    http_response_code(404);
    exit;
}

$uuid = $matches[0];
$hex  = strtolower(str_replace('-', '', $uuid));
$sub1 = substr($hex, 0, 2);
$sub2 = substr($hex, 2, 2);

// 2) Encrypted file location
$file = __DIR__ . "/../.files/$sub1/$sub2/$uuid.tar.gz.enc";

// 3) File must exist
if (! is_file($file)) {
    http_response_code(404);
    exit;
}

// 4) Stream it with appropriate headers
header('Content-Type: application/octet-stream');
header(
  'Content-Disposition: attachment; filename="' .
  basename($file) .
  '"'
);
header('Content-Length: ' . filesize($file));
readfile($file);
exit;

