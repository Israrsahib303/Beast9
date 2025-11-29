<?php
// panel/full_backup.php - FULL SITE ZIP DOWNLOADER

// 1. Security Checks
require_once __DIR__ . '/../includes/helpers.php'; 
require_once __DIR__ . '/admin_lock.php'; // Ghost Mode
require_once __DIR__ . '/_auth_check.php'; // Admin Auth

// 2. Increase Server Limits (Badi files ke liye)
set_time_limit(0); // No time limit
ini_set('memory_limit', '1024M'); // 1GB Ram allow

// 3. Setup Paths
$rootPath = realpath(__DIR__ . '/../'); // Public_html (Root)
$zipName = 'Full_Backup_' . date('Y-m-d_H-i') . '.zip';
$zipPath = __DIR__ . '/' . $zipName; // Temp save in panel folder

// 4. Create Zip
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Error: Cannot create zip file. Check folder permissions.");
}

// Recursive Iterator (Sab folders ko scan karega)
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($rootPath) + 1);

        // --- EXCLUDE USELESS FILES (Size bachane ke liye) ---
        if (strpos($filePath, $zipName) !== false) continue; // Khud zip ko skip karo
        if (strpos($filePath, 'error_log') !== false) continue; // Error logs nahi chahiye
        if (strpos($filePath, '.git') !== false) continue; // Git files skip
        if (strpos($filePath, 'node_modules') !== false) continue; // Node modules skip

        // Add to Zip
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

// 5. Force Download
if (file_exists($zipPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.basename($zipPath).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zipPath));
    
    // Clear buffers to avoid corruption
    while (ob_get_level()) { ob_end_clean(); }
    flush();
    
    readfile($zipPath);
    
    // 6. Delete Zip after download (Server saaf rakhein)
    unlink($zipPath);
    exit;
} else {
    echo "Error: Backup file creation failed.";
}
?>