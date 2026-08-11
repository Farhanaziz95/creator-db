<?php
$config = require __DIR__ . '/../config/backup.php';

$filename = 'creator_db_backup_' . date('Y-m-d_His') . '.sql';
$tmpPath  = sys_get_temp_dir() . '/' . $filename;

$passArg = $config['db_pass'] !== '' ? '-p' . escapeshellarg($config['db_pass']) : '';

$command = sprintf(
    '"%s" -u %s %s %s > "%s" 2>&1',
    $config['mysqldump_path'],
    escapeshellarg($config['db_user']),
    $passArg,
    escapeshellarg($config['db_name']),
    $tmpPath
);

exec($command, $output, $returnCode);

if ($returnCode !== 0 || !file_exists($tmpPath) || filesize($tmpPath) === 0) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Backup failed. Check config/backup.php has the correct mysqldump path.']);
    exit;
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpPath));
readfile($tmpPath);
unlink($tmpPath);
