<?php
require_once __DIR__ . '/_auth_check.php';

// Function to generate tree
function getDirectoryTree($dir, $prefix = '') {
    $result = '';
    $files = scandir($dir);
    
    // Filter unwanted folders
    $exclude = ['.', '..', '.git', 'node_modules', 'error_log', '.well-known', 'cgi-bin'];
    $files = array_diff($files, $exclude);
    
    // Sort: Folders first, then files
    usort($files, function($a, $b) use ($dir) {
        return is_dir($dir . '/' . $a) < is_dir($dir . '/' . $b);
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

// Root path set karein (Panel se ek step peeche)
$rootPath = realpath(__DIR__ . '/../');
$tree = getDirectoryTree($rootPath);

// Output as Plain Text
header('Content-Type: text/plain');
echo "Project Structure for: " . $GLOBALS['settings']['site_name'] . "\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n";
echo "---------------------------------------------------\n\n";
echo $tree;
?>