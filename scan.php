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

function scanFile($directory) {
    $result = [];
    $scriptDir = realpath(__DIR__); // Absolute path of the directory where scan.php is located

    $items = scandir($directory);

    $tokenNeedles = array(
        'Obfuscation' => [
            'base64_decode', 'rawurldecode', 'urldecode', 'gzinflate', 'gzuncompress', 'str_rot13', 'convert_uu',
            'htmlspecialchars_decode', 'bin2hex', 'hex2bin', 'hexdec', 'chr', 'strrev', 'goto', 'implode',
            'strtr', 'extract', 'parse_str', 'substr', 'mb_substr', 'str_replace', 'substr_replace', 'preg_replace',
            'exif_read_data', 'readgzfile'
        ],
        'Shell / Process' => [
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

    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..') {
            $path = $directory . '/' . $item;

            // Get the current path relative to the base directory, with a leading slash
            $currentPath = str_replace($scriptDir . '/', '', $path);

            if (is_file($path)) {
                $fileContents = file_get_contents($path);
                $fileResult = [
                    'path' => $path, // Full path
                    'current_path' => '/' . $currentPath, // Relative path from the base directory
                    'name' => basename($path),
                    'matches' => []
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

                // Only add files that have matches
                if (!empty($fileResult['matches'])) {
                    $result[] = $fileResult;
                }
            } elseif (is_dir($path)) {
                // Recursively scan subdirectories
                $result = array_merge($result, scanFile($path));
            }
        }
    }
    return $result;
}

function generateFile($directory) {
    $result = [];
    
    // Mendapatkan protokol (http/https) dan host dari permintaan saat ini
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host . '/';

    function findDeepestDirectories($dir, &$deepestDirs, $excludedDirs = [], $currentLevel = 0) {
        $subDirs = [];
        $files = [];
        
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            
            if (is_dir($path) && !in_array($item, $excludedDirs)) {
                $subDirs[] = $path;
                findDeepestDirectories($path, $deepestDirs, $excludedDirs, $currentLevel + 1);
            } else if (is_file($path)) {
                $files[] = $path;
            }
        }
        
        if (empty($subDirs)) {
            $deepestDirs[] = $dir;
        }
    }

    $excludedDirs = ['themes', 'plugins'];
    $deepestDirs = [];
    findDeepestDirectories($directory, $deepestDirs, $excludedDirs);

    foreach ($deepestDirs as $deepDir) {
        $filePath = $deepDir . '/generated_file.txt';
        file_put_contents($filePath, "This is a generated file.");

        // Mengonversi path file menjadi path relatif dari direktori yang diberikan
        $relativePath = str_replace($directory . '/', '', $filePath);

        // Menggabungkan URL dasar dengan path relatif
        $result[] = $baseUrl . $relativePath;
    }

    return $result;
}

function deleteFile($filePath) {
    $file_path = preg_replace('/^\//', '', $filePath); // Sanitize file path
    $fullPath = __DIR__ . '/' . $file_path; // Adjust the path if necessary

    if (file_exists($fullPath)) {
        // Attempt to set file permissions to 644
        if (chmod($fullPath, 0644)) {
            // Try to delete the file
            if (unlink($fullPath)) {
                return json_encode(["message" => "File deleted successfully."]);
            } else {
                return json_encode(["message" => "Failed to delete file."]);
            }
        } else {
            return json_encode(["message" => "Failed to set file permissions."]);
        }
    }
    return json_encode(["message" => "File does not exist."]);
}

$directory = __DIR__; // Definisikan direktori yang ingin dipindai atau ubah sesuai kebutuhan

$action = isset($_GET['action']) ? $_GET['action'] : '';
$filePath = isset($_POST['file']) ? $_POST['file'] : '';

header('Content-Type: application/json');

switch ($action) {
    case 'scanFolder':
        echo json_encode(scanFolder($directory));
        break;
    case 'scanFile':
        echo json_encode(scanFile($directory));
        break;
    case 'generateFile':
        echo json_encode(generateFile($directory));
        break;
    case 'deleteFile':
        echo json_encode([deleteFile($filePath)]);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
