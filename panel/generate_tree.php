<?php
// Sirf Auth Check agar direct access ho, taake include error na de
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    require_once __DIR__ . '/_auth_check.php';
}

// Function definition
if (!function_exists('getDirectoryTree')) {
    function getDirectoryTree($dir, $prefix = '') {
        $result = '';
        
        // Check if directory exists
        if (!is_dir($dir)) {
            return "";
        }

        $files = scandir($dir);
        
        // Filter unwanted folders
        $exclude = ['.', '..', '.git', 'node_modules', 'error_log', '.well-known', 'cgi-bin', 'vendor', '.zip'];
        $files = array_diff($files, $exclude);
        
        // Sort: Folders first, then files
        usort($files, function($a, $b) use ($dir) {
            $isDirA = is_dir($dir . '/' . $a);
            $isDirB = is_dir($dir . '/' . $b);
            if ($isDirA == $isDirB) return strcasecmp($a, $b);
            return $isDirB - $isDirA; // Folders first
        });

        $count = count($files);
        $i = 0;

        foreach ($files as $file) {
            $i++;
            $path = $dir . '/' . $file;
            $isDir = is_dir($path);
            $isLast = ($i == $count);
            
            // Visual connectors
            $connector = $isLast ? '└── ' : '├── ';
            $childPrefix = $isLast ? '    ' : '│   ';
            
            $result .= $prefix . $connector . $file . ($isDir ? '/' : '') . "\n";
            
            if ($isDir) {
                $result .= getDirectoryTree($path, $prefix . $childPrefix);
            }
        }
        return $result;
    }
}

// --- IMPORTANT: Run only if accessed directly, NOT when included ---
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    
    // Root path set karein
    $rootPath = realpath(__DIR__ . '/../');
    
    // Generate
    $tree = getDirectoryTree($rootPath);

    // Output
    header('Content-Type: text/plain');
    echo "Project Structure for: " . ($GLOBALS['settings']['site_name'] ?? 'Beast9') . "\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "---------------------------------------------------\n\n";
    echo $tree;
}
?>