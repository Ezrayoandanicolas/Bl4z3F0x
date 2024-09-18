<?php
    function scanFolder($directory) {
        $result = [];
        
        // Ensure the directory path ends with a DIRECTORY_SEPARATOR
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        // Get items in the directory
        $items = scandir($directory);
    
        foreach ($items as $item) {
            // Skip current and parent directory references
            if ($item !== '.' && $item !== '..') {
                $path = $directory . $item;
    
                if (is_dir($path)) {
                    // Add directory to the result array
                    $result[] = [
                        'path' => $path,
                        'current_path' => ltrim($path, DIRECTORY_SEPARATOR)
                    ];
                    // Recursively scan subdirectories
                    $result = array_merge($result, scanFolder($path));
                } else {
                    // Add files to the result array if needed
                    $result[] = [
                        'path' => $path,
                        'current_path' => ltrim($path, DIRECTORY_SEPARATOR)
                    ];
                }
            }
        }
        
        return $result;
    }

    $directory = __DIR__;
    $scannedItems = scanFolder($directory);

    // Display the result
    foreach ($scannedItems as $item) {
        echo "Path: " . $item['path'] . "<br>";
        echo "Current Path: " . $item['current_path'] . "<br><br>";
    }
?>
