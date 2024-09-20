<?php
    function scanFolderAndFile($directory, $url) {
        $result = [];
        $scriptDir = realpath(__DIR__); // Absolute path of the directory where this script is located
        
        // Ensure the directory path ends with a DIRECTORY_SEPARATOR
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
        // Check if the directory exists
        if (!is_dir($directory)) {
            http_response_code(400); // Bad Request if directory doesn't exist
            return ['error' => 'Invalid directory.'];
        }
    
        // Tokens for scanning file content
        $tokenNeedles = array(
            'Obfuscation' => [
                'base64_decode', 'rawurldecode', 'urldecode', 'gzinflate', 'gzuncompress', 'str_rot13', 'convert_uu',
                'htmlspecialchars_decode', 'bin2hex', 'hex2bin', 'hexdec', 'chr', 'strrev', 'goto', 'implode',
                'strtr', 'extract', 'parse_str', 'substr', 'mb_substr', 'str_replace', 'substr_replace', 'preg_replace',
                'exif_read_data', 'readgzfile'
            ],
            'ShellProcess' => [
                'eval', 'exec', 'shell_exec', 'system', 'passthru', 'pcntl_fork', 'fsockopen', 'proc_open',
                'popen', 'assert', 'posix_kill', 'posix_setpgid', 'posix_setsid', 'posix_setuid', 'proc_nice',
                'proc_close', 'proc_terminate', 'apache_child_terminate'
            ],
            'Server Information' => [
                'posix_getuid', 'posix_geteuid', 'posix_getegid', 'posix_getpwuid', 'posix_getgrgid', 'posix_mkfifo',
                'posix_getlogin', 'posix_ttyname', 'getenv', 'proc_get_status', 'get_cfg_var', 'disk_free_space',
                'disk_total_space', 'diskfreespace', 'getlastmo', 'getmyinode', 'getmypid', 'getmyuid', 'getmygid',
                'fileowner', 'filegroup', 'get_current_user', 'pathinfo', 'getcwd', 'sys_get_temp_dir', 'basename',
                'phpinfo'
            ],
            'Database' => [
                'mysql_connect', 'mysqli_connect', 'mysqli_query', 'mysql_query'
            ],
            'I/O' => [
                'fopen', 'fsockopen', 'file_put_contents', 'file_get_contents', 'url_get_contents', 'stream_get_meta_data',
                'move_uploaded_file', '$_files', 'copy', 'include', 'include_once', 'require', 'require_once', '__file__'
            ],
            'Miscellaneous' => [
                'mail', 'putenv', 'curl_init', 'tmpfile', 'allow_url_fopen', 'ini_set', 'set_time_limit', 'session_start',
                'symlink', '__halt_compiler', '__compiler_halt_offset__', 'error_reporting', 'create_function',
                'get_magic_quotes_gpc', '$auth_pass', '$password'
            ]
        );
    
        // Get items in the directory
        $items = scandir($directory);
    
        $matches = [];     // For files with token matches
        $nonMatches = [];  // For files without token matches

        foreach ($items as $item) {
            // Skip current and parent directory references
            if ($item !== '.' && $item !== '..') {
                $path = $directory . $item;
                $relativePath = str_replace($scriptDir . DIRECTORY_SEPARATOR, '/', $path); // Get relative path
        
                // Create the URL using the base URL and relative path
                $fileUrl = $url . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath); // Ensure the URL uses '/' instead of '\' (Windows-specific)
        
                if (is_dir($path)) {
                    // Recursively scan subdirectories
                    $subDirResult = scanFolderAndFile($path, $url);
                    
                    // Calculate folder checksum based on the checksums of its contents
                    $folderChecksum = hash('sha256', json_encode($subDirResult)); // You can change 'sha256' to 'md5' if needed
        
                    // Calculate folder size by summing up the size of contents
                    $folderSize = array_reduce($subDirResult, function($carry, $item) {
                        return $carry + (isset($item['size']) ? $item['size'] : 0);
                    }, 0);
        
                    // Add directory to the result array
                    $result[] = [
                        'path' => $path, // Use URL instead of system path
                        'current_path' => ltrim($fileUrl, DIRECTORY_SEPARATOR),
                        'directory' => true,
                        'checksum' => $folderChecksum,
                        'size' => $folderSize, // Add folder size
                        'contents' => $subDirResult
                    ];
                    
                    // Merge subdirectory result
                    $matches = array_merge($matches, $subDirResult['matches']);
                    $nonMatches = array_merge($nonMatches, $subDirResult['non_matches']);
                } else {
                    // Handle file scanning for tokens
                    $fileContents = file_get_contents($path);
                    $fileSize = filesize($path); // Get file size in bytes
                    $fileResult = [
                        'path' => $path, // Use URL instead of system path
                        'current_path' => ltrim($fileUrl, DIRECTORY_SEPARATOR), // Relative path from the base directory
                        'name' => basename($path),
                        'directory' => false,
                        'size' => $fileSize, // Add file size
                        'matches' => [],
                        'checksum' => hash_file('sha256', $path) // Calculate file checksum (sha256 or md5)
                    ];
        
                    // Search for tokens within the file, categorized by groups
                    foreach ($tokenNeedles as $category => $tokens) {
                        $matchedTokens = [];
                        foreach ($tokens as $needle) {
                            if (stripos($fileContents, $needle) !== false) {
                                $matchedTokens[] = $needle;
                            }
                        }
                        if (!empty($matchedTokens)) {
                            $fileResult['matches'][$category] = $matchedTokens;
                        }
                    }
        
                    // If the file has matches, add to $matches, otherwise to $nonMatches
                    if (!empty($fileResult['matches'])) {
                        $matches[] = $fileResult;
                    } else {
                        $nonMatches[] = $fileResult;
                    }
                }
            }
        }
        
        // Merge matches and non matches without duplicates based on 'path'
        $allMatches = array_merge($matches, $nonMatches);
        
        // Remove duplicates by 'path'
        $uniqueAllMatches = array_reduce($allMatches, function ($carry, $item) {
            // Using 'path' as ​​key to filter duplicates
            if (!isset($carry[$item['path']])) {
                $carry[$item['path']] = $item;
            }
            return $carry;
        }, []);
        
        // Return hasil matches, non_matches, dan all_matches
        return [
            'matches' => $matches,
            'non_matches' => $nonMatches,
            'all_matches' => array_values($uniqueAllMatches)
        ];
        
    }
    

    // Set the header to return JSON content
    header('Content-Type: application/json');

    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'];

    try {
        // Set directory to scan
        $directory = isset($_GET['directory']) ? $_GET['directory'] : __DIR__;

        // Scan the folder
        // $scannedItems = scanFolder($directory, $url);
        $scannedItems = scanFolderAndFile($directory, $url);

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
