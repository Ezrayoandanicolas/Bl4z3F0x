<?php
    function scanFolder($directory) {
        $result = [];

        // Ensure the directory path ends with a DIRECTORY_SEPARATOR
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Check if the directory exists
        if (!is_dir($directory)) {
            http_response_code(400); // Bad Request if directory doesn't exist
            return ['error' => 'Invalid directory.'];
        }

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

    // Set the header to return JSON content
    header('Content-Type: application/json');

    try {
        // Set directory to scan
        $directory = isset($_GET['directory']) ? $_GET['directory'] : __DIR__;

        // Scan the folder
        $scannedItems = scanFolder($directory);

        // Set response code 200 OK
        http_response_code(200);

        // Output the result as JSON
        echo json_encode([
            'status' => 'success',
            'data' => $scannedItems
        ]);
    } catch (Exception $e) {
        // Set response code 500 Internal Server Error
        http_response_code(500);

        // Output error as JSON
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
?>
