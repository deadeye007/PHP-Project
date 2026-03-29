<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$schema = getenv('DB_NAME') ?: 'learning_platform';
$tempDir = sys_get_temp_dir();
$backupId = uniqid('backup_', true);
$zipFile = $tempDir . DIRECTORY_SEPARATOR . $backupId . '.zip';
$csvFiles = [];

try {
    global $pdo;

    $tableStmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
    $tableStmt->execute([$schema]);
    $tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $csvPath = $tempDir . DIRECTORY_SEPARATOR . 'backup_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $table) . '_' . uniqid() . '.csv';
        $csvHandle = fopen($csvPath, 'w');
        if ($csvHandle === false) {
            continue;
        }

        $stmt = $pdo->query("SELECT * FROM `$table`");

        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta = $stmt->getColumnMeta($i);
            $columns[] = $meta['name'];
        }

        if (!empty($columns)) {
            fputcsv($csvHandle, $columns);
        }

        while ($rows = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($csvHandle, $rows);
        }

        fclose($csvHandle);
        $csvFiles[] = ['path' => $csvPath, 'name' => $table . '.csv'];
    }

    if (empty($csvFiles)) {
        throw new Exception('No tables found to backup.');
    }

    $createdZip = false;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($csvFiles as $file) {
                $zip->addFile($file['path'], $file['name']);
            }
            $zip->close();
            $createdZip = true;
        }
    }

    if (!$createdZip) {
        $zipBinaryRaw = shell_exec('command -v zip');
        $zipBinary = is_string($zipBinaryRaw) ? trim($zipBinaryRaw) : '';
        if ($zipBinary !== '' && is_executable($zipBinary)) {
            $workDir = $tempDir . DIRECTORY_SEPARATOR . $backupId;
            mkdir($workDir, 0700, true);
            foreach ($csvFiles as $file) {
                copy($file['path'], $workDir . DIRECTORY_SEPARATOR . $file['name']);
            }
            $cmd = escapeshellcmd($zipBinary) . ' -j ' . escapeshellarg($zipFile);
            foreach ($csvFiles as $file) {
                $cmd .= ' ' . escapeshellarg($workDir . DIRECTORY_SEPARATOR . $file['name']);
            }
            exec($cmd, $output, $resultCode);
            array_map('unlink', glob($workDir . DIRECTORY_SEPARATOR . '*.csv'));
            rmdir($workDir);
            $createdZip = ($resultCode === 0 && file_exists($zipFile));
        }
    }

    if (!$createdZip && class_exists('PharData')) {
        $tarFile = $tempDir . DIRECTORY_SEPARATOR . $backupId . '.tar';
        $phar = new PharData($tarFile);
        foreach ($csvFiles as $file) {
            $phar->addFile($file['path'], $file['name']);
        }
        $phar->compress(Phar::GZ);
        unset($phar);
        @unlink($tarFile);
        $zipFile = $tarFile . '.gz';
        $createdZip = file_exists($zipFile);
    }

    if (!$createdZip) {
        throw new Exception('Could not create archive. Install PHP Zip extension or zip command-line utility.');
    }

    $downloadName = 'learning_platform_backup_' . date('Ymd_His') . ((substr($zipFile, -3) === '.gz') ? '.tar.gz' : '.zip');
    $contentType = (substr($zipFile, -3) === '.gz') ? 'application/gzip' : 'application/zip';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($zipFile));
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    readfile($zipFile);

} catch (Exception $e) {
    die('Backup failed: ' . htmlspecialchars($e->getMessage()));
} finally {
    foreach ($csvFiles as $file) {
        if (file_exists($file['path'])) {
            @unlink($file['path']);
        }
    }
    if (isset($zip) && file_exists($zipFile)) {
        @unlink($zipFile);
    }
}

exit;
