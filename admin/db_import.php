<?php
require_once '../includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$title = 'Import Database';
$message = '';
$allowedExtensions = ['zip', 'tar', 'tar.gz', 'tgz'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['database_backup']) || $_FILES['database_backup']['error'] !== UPLOAD_ERR_OK) {
        $message = '<div class="alert alert-danger">Please upload a valid backup file.</div>';
    } else {
        $uploaded = $_FILES['database_backup'];
        $origName = $uploaded['name'];
        $tmpName = $uploaded['tmp_name'];

        $isTarGz = preg_match('/\.(tar\.gz|tgz)$/i', $origName) === 1;
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!$isTarGz && !in_array($ext, ['zip', 'tar'], true)) {
            $message = '<div class="alert alert-danger">Only ZIP, TAR, TAR.GZ or TGZ archives are allowed.</div>';
        } else {
            $tmpFile = tempnam(sys_get_temp_dir(), 'import_');
            if (!move_uploaded_file($tmpName, $tmpFile)) {
                $message = '<div class="alert alert-danger">Failed to store uploaded file.</div>';
            } else {
                $extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_' . uniqid();
                mkdir($extractDir, 0700, true);

                $extracted = false;
                $isZip = !$isTarGz && $ext === 'zip';
                $isTar = $isTarGz || $ext === 'tar';

                if ($isZip && class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($tmpFile) === TRUE) {
                        $zip->extractTo($extractDir);
                        $zip->close();
                        $extracted = true;
                    }
                } elseif ($isTar && class_exists('PharData')) {
                    try {
                        $phar = new PharData($tmpFile);
                        if ($isTarGz) {
                            $phar->decompress();
                            $tarFile = str_replace(['.tar.gz', '.tgz'], '.tar', $tmpFile);
                            $phar = new PharData($tarFile);
                        }
                        $phar->extractTo($extractDir); 
                        $extracted = true;
                    } catch (Exception $e) {
                        $extracted = false;
                    }
                }

                if (!$extracted) {
                    // Additional extraction fallbacks for environments where PharData may fail
                    if ($isZip) {
                        $unzipBinRaw = shell_exec('command -v unzip');
                        $unzipBin = is_string($unzipBinRaw) ? trim($unzipBinRaw) : '';
                        if ($unzipBin !== '' && is_executable($unzipBin)) {
                            $cmd = escapeshellcmd($unzipBin) . ' -o ' . escapeshellarg($tmpFile) . ' -d ' . escapeshellarg($extractDir);
                            exec($cmd, $output, $resultCode);
                            if ($resultCode === 0) {
                                $extracted = true;
                            }
                        }
                    }

                    if (!$extracted && $isTar) {
                        $tarBinRaw = shell_exec('command -v tar');
                        $tarBin = is_string($tarBinRaw) ? trim($tarBinRaw) : '';
                        if ($tarBin !== '' && is_executable($tarBin)) {
                            $cmd = escapeshellcmd($tarBin) . ' -xzf ' . escapeshellarg($tmpFile) . ' -C ' . escapeshellarg($extractDir);
                            if (!$isTarGz) {
                                $cmd = escapeshellcmd($tarBin) . ' -xf ' . escapeshellarg($tmpFile) . ' -C ' . escapeshellarg($extractDir);
                            }
                            exec($cmd, $output, $resultCode);
                            if ($resultCode === 0) {
                                $extracted = true;
                            }
                        }
                    }
                }

                if (!$extracted) {
                    @unlink($tmpFile);
                    if (is_dir($extractDir)) {
                        $files = glob($extractDir . '/*');
                        if ($files !== false) {
                            array_map('unlink', $files);
                        }
                        @rmdir($extractDir);
                    }
                    $message = '<div class="alert alert-danger">Unable to extract uploaded archive. Ensure it is a valid backup file (ZIP/TAR/TAR.GZ).\nPlease check that PHP ZipArchive or tar/unzip is installed.</div>';
                } else {
                    global $pdo;
                    $schema = getenv('DB_NAME') ?: 'learning_platform';
                    $tableStmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
                    $tableStmt->execute([$schema]);
                    $validTables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);

                    $csvPaths = glob($extractDir . '/*.csv');
                    if (empty($csvPaths)) {
                        $message = '<div class="alert alert-danger">No CSV files found in archive.</div>';
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $totalAdded = 0;
                            $totalSkipped = 0;
                            $importIssues = [];

                            foreach ($csvPaths as $csvPath) {
                                $table = pathinfo($csvPath, PATHINFO_FILENAME);
                                if (!in_array($table, $validTables, true)) {
                                    $importIssues[] = "Skipping unknown table: {$table}";
                                    continue;
                                }

                                $fieldsStmt = $pdo->prepare("DESCRIBE `$table`");
                                $fieldsStmt->execute();
                                $validColumns = array_column($fieldsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                                $pkStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI'");
                                $pkStmt->execute([$schema, $table]);
                                $pkCols = $pkStmt->fetchAll(PDO::FETCH_COLUMN);

                                $handle = fopen($csvPath, 'r');
                                if (!$handle) {
                                    $importIssues[] = "Unable to open CSV: {$csvPath}";
                                    continue;
                                }

                                $headers = fgetcsv($handle);
                                if (empty($headers) || !is_array($headers)) {
                                    $importIssues[] = "Empty header row in CSV for table {$table}.";
                                    fclose($handle);
                                    continue;
                                }

                                $headers = array_map('trim', $headers);
                                $diffCols = array_diff($headers, $validColumns);
                                if (!empty($diffCols)) {
                                    $importIssues[] = "Invalid columns in {$table}: " . implode(', ', $diffCols);
                                    fclose($handle);
                                    continue;
                                }

                                $placeholders = implode(', ', array_fill(0, count($headers), '?'));
                                $columnList = implode('`,`', array_map('trim', $headers));
                                $insertSql = "INSERT INTO `$table` (`{$columnList}`) VALUES ({$placeholders})";
                                $insertStmt = $pdo->prepare($insertSql);

                                while (($row = fgetcsv($handle)) !== false) {
                                    if (count($row) !== count($headers)) {
                                        $importIssues[] = "Row column count mismatch in {$table} (skipping row).";
                                        continue;
                                    }

                                    $rowData = array_combine($headers, $row);

                                    if (!empty($pkCols)) {
                                        $whereParts = [];
                                        $whereValues = [];
                                        foreach ($pkCols as $pk) {
                                            if (!array_key_exists($pk, $rowData)) {
                                                continue;
                                            }
                                            $whereParts[] = "`{$pk}` = ?";
                                            $whereValues[] = $rowData[$pk];
                                        }

                                        if (!empty($whereParts)) {
                                            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE " . implode(' AND ', $whereParts));
                                            $checkStmt->execute($whereValues);
                                            if ((int)$checkStmt->fetchColumn() > 0) {
                                                $totalSkipped++;
                                                continue;
                                            }
                                        }
                                    }

                                    $insertStmt->execute(array_values($rowData));
                                    $totalAdded++;
                                }

                                fclose($handle);
                            }

                            $pdo->commit();

                            $message = '<div class="alert alert-success">Import complete. Records added: ' . $totalAdded . '. Skipped duplicates: ' . $totalSkipped . '.</div>';
                            if (!empty($importIssues)) {
                                $message .= '<div class="alert alert-warning"><strong>Warnings:</strong><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $importIssues)) . '</li></ul></div>';
                            }
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $message = '<div class="alert alert-danger">Import failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }

                @unlink($tmpFile);
                if (is_dir($extractDir)) {
                    $files = glob($extractDir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    }
                    @rmdir($extractDir);
                }
            }
        }
    }
}

$content = '<h2>Import Database</h2>';
$content .= $message;
$content .= '<form method="post" enctype="multipart/form-data">';
$content .= '<div class="mb-3">';
$content .= '<label for="database_backup" class="form-label">Backup Archive (.zip, .tar, .tar.gz, .tgz)</label>';
$content .= '<input class="form-control" type="file" id="database_backup" name="database_backup" accept=".zip,.tar,.tar.gz,.tgz" required>';
$content .= '</div>';
$content .= '<button type="submit" class="btn btn-primary">Upload and Import</button>';
$content .= ' <a href="index.php" class="btn btn-secondary">Cancel</a>';
$content .= '</form>';

include '../includes/header.php';
