<?php
    $minute = 3;
    $limit = (60 * $minute); // 60 (seconds) = 1 Minutes
    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', $limit);
    set_time_limit($limit);

    function scanFolderAndFile($directory, $url) {
        // $result = [];
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
                'proc_close', 'proc_terminate', 'apache_child_terminate', 'curl_exec', 'curl_multi_exec', 
                'base64_decode', 'include', 'require', 'file_get_contents', 'fopen', 'fwrite', 'fclose', 
                'move_uploaded_file', 'preg_replace', 'unserialize', 'file_put_contents', 'unlink', 'rename',
                'chmod', 'chown', 'symlink', 'copy', 'include_once', 'require_once', 'getimagesize', 'readfile',
                'stream_socket_client', 'stream_socket_server', 'stream_socket_accept', 'pcntl_exec',
                'call_user_func', 'call_user_func_array', 'create_function', 'gzuncompress', 'gzdecode', 
                'mb_ereg_replace', 'phpinfo', 'getenv', 'putenv', 'get_current_user', 'nepokcosf', 'elif'
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
    
        $allMatches = []; // Combined array for all matches

        foreach ($items as $item) {
            // Skip current and parent directory references
            if ($item !== '.' && $item !== '..') {
                $path = $directory . $item;
                $relativePath = str_replace($scriptDir . DIRECTORY_SEPARATOR, '/', $path); // Get relative path
        
                // Create the URL using the base URL and relative path
                $fileUrl = $url . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath); // Ensure the URL uses '/' instead of '\' (Windows-specific)

                // Check if the item is a directory
                if (is_dir($path)) {
                    // Recursively scan subdirectories
                    $subDirResults = scanFolderAndFile($path, $url);
                    $allMatches = array_merge($allMatches, $subDirResults['all_matches']);
                } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                    // Handle file scanning for tokens
                    $fileContents = file_get_contents($path);
                    $fileSize = filesize($path); // Get file size in bytes

                    $fileResult = [
                        'path' => $path,
                        'current_path' => ltrim($fileUrl, DIRECTORY_SEPARATOR),
                        'name' => basename($path),
                        'directory' => false,
                        'size' => $fileSize,
                        'matches' => [],
                        'checksum' => hash_file('sha256', $path) // Calculate file checksum
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

                    // Add the file result to allMatches
                    $allMatches[] = $fileResult;
                }
            }
        }

        // Remove duplicates by 'path'
        $uniqueAllMatches = array_reduce($allMatches, function ($carry, $item) {
            if (!isset($carry[$item['path']])) {
                $carry[$item['path']] = $item;
            }
            return $carry;
        }, []);

        // Return all_matches
        return [
            'all_matches' => array_values($uniqueAllMatches)
        ];
    }
    

    // Set the header to return JSON content
    header('Content-Type: application/json');

    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'];

    try {
        // Set directory to scan
    $directory = isset($_GET['directory']) ? $_GET['directory'] : __DIR__;

    // Cek apakah parameter 'RawFile' ada di URL
    if (isset($_GET['RawFile'])) {
        // Dapatkan path file dari parameter RawFile
        $rawFile = $_GET['RawFile'];

        // Cek apakah file ada dan bisa dibaca
        if (file_exists($rawFile) && is_readable($rawFile)) {
            // Set response header untuk file mentah
            header("Content-Type: text/plain");

            // Baca dan tampilkan isi file mentah
            echo file_get_contents($rawFile);
        } else {
            // Jika file tidak ditemukan atau tidak bisa dibaca
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'File not found or not readable'
            ]);
        }
    } else {
        // Jika 'RawFile' tidak ada, scan folder dan file seperti biasa

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
    }
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
