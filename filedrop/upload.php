<?php
header('Content-Type: application/json');

// 1) Validate upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['error'=>'Upload failed']); exit;
}

// 2) Generate UUIDv4
$u    = bin2hex(random_bytes(16));
$uuid = vsprintf('%s-%s-%s-%s-%s', [
  substr($u, 0, 8), substr($u, 8, 4),
  substr($u,12,4), substr($u,16,4),
  substr($u,20,12),
]);

// 3) Build storage path
$base = __DIR__.'/.files';
$sub1 = substr($u,0,2);
$sub2 = substr($u,2,2);
$dir  = "$base/$sub1/$sub2/$uuid";
if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
  echo json_encode(['error'=>'Cannot create storage']); exit;
}

// 4) Move uploaded file
$name = basename($_FILES['file']['name']);
move_uploaded_file($_FILES['file']['tmp_name'], "$dir/$name");

// 5) Create tar.gz
$tarball = "$base/$sub1/$sub2/$uuid.tar.gz";
exec(
  sprintf(
    'tar czf %s -C %s %s',
    escapeshellarg($tarball),
    escapeshellarg($dir),
    escapeshellarg($name)
  ),
  $out, $rc
);
if ($rc !== 0) {
  echo json_encode(['error'=>'Tar failed']); exit;
}

// 6) Generate one-time key & IV
$key = random_bytes(32);
$iv  = random_bytes(16);
$keyHex = bin2hex($key);
$ivHex  = bin2hex($iv);

// 7) Encrypt with OpenSSL (AES-256-CBC)
$enc = "$tarball.enc";
exec(
  sprintf(
    'openssl enc -aes-256-cbc -K %s -iv %s -in %s -out %s',
    $keyHex, $ivHex,
    escapeshellarg($tarball),
    escapeshellarg($enc)
  ),
  $out, $rc
);
if ($rc !== 0) {
  echo json_encode(['error'=>'Encrypt failed']); exit;
}

// 8) Cleanup
unlink($tarball);
unlink("$dir/$name");

// 9) Build URLs & one-liner
$scheme = 'https';
$host   = $_SERVER['HTTP_HOST'];
$path   = "fetch/$uuid";
$url    = "$scheme://$host/$path";

$cmd = <<<BASH
wget -qO - '$url' \\
  | openssl enc -d -aes-256-cbc -K $keyHex -iv $ivHex \\
  | tee >(tar xz) \\
  | tar tz \\
  | while read -r f; do echo "Extracted: \$f"; done
BASH;

// 10) Return JSON
echo json_encode([
  'url'          => $url,
  'download_cmd' => $cmd,
]);
