<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide warnings/notices from breaking JSON

header('Content-Type: application/json');

// Stop caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// ===== SETTINGS =====
$outputFolder = __DIR__ . '/output';
$watermarkPath = __DIR__ . '/watermark.png';

if (!is_dir($outputFolder)) {
    mkdir($outputFolder, 0777, true);
}

$results = [];

// helpers
function fetchWithCurl($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,   // fixes most local SSL problems
        CURLOPT_SSL_VERIFYHOST => false,   // fixes most local SSL problems
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHP Image Fetcher',
        CURLOPT_HEADER         => false,
    ]);

    $data = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false) {
        echo "cURL Error: $error\n";
        return false;
    }
    if ($httpCode !== 200) {
        echo "HTTP $httpCode returned\n";
        return false;
    }
    return $data;
}

function fetchWithFileGetContents($url) {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHP Image Fetcher',
        ]
    ]);

    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        echo "file_get_contents failed\n";
        return false;
    }
    return $data;
}

function fetchWithCurlAdvanced($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL                => $url,
        CURLOPT_RETURNTRANSFER     => true,
        CURLOPT_FOLLOWLOCATION     => true,
        CURLOPT_MAXREDIRS          => 5,
        CURLOPT_TIMEOUT            => 60,        // Increased timeout
        CURLOPT_CONNECTTIMEOUT     => 20,        // Connection timeout
        CURLOPT_LOW_SPEED_LIMIT    => 1000,      // Abort if <1KB/s
        CURLOPT_LOW_SPEED_TIME     => 30,        // For 30s
        CURLOPT_SSL_VERIFYPEER     => false,
        CURLOPT_SSL_VERIFYHOST     => false,
        CURLOPT_TCP_KEEPALIVE      => 1,         // Keep connection alive
        CURLOPT_TCP_FASTOPEN       => true,      // Faster TCP
        CURLOPT_HTTP_VERSION       => CURL_HTTP_VERSION_2_0, // Prefer HTTP/2
        CURLOPT_HTTPHEADER         => [
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            // Full Chrome-like User-Agent
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ],
        CURLOPT_ENCODING           => '',         // Handle gzip auto
        CURLOPT_COOKIEJAR          => '/tmp/cookie.txt', // Enable cookies (write)
        CURLOPT_COOKIEFILE         => '/tmp/cookie.txt', // Enable cookies (read)
        CURLOPT_FORBID_REUSE       => false,      // Reuse connections
        CURLOPT_FRESH_CONNECT      => false,
    ]);

    // Enable verbose logging to file for debugging
    $verboseLog = fopen('curl_verbose.log', 'a');
    curl_setopt($ch, CURLOPT_STDERR, $verboseLog);

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    fclose($verboseLog);

    if ($data === false) {
        echo "cURL Error: $error\n";
        echo "cURL Info: " . print_r($info, true) . "\n";
        echo "Check curl_verbose.log for full debug output.\n";
        return false;
    }
    if ($httpCode !== 200) {
        echo "HTTP Error: $httpCode\n";
        return false;
    }
    return $data;
}

function fetchImageData($url) {
    // $context = stream_context_create([
    //     'http' => [
    //         'method' => 'GET',
    //         'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
    //         'timeout' => 30,
    //     ],
    //     'ssl' => [
    //         'verify_peer' => false,
    //         'verify_peer_name' => false,
    //     ]
    // ]);
    
    // $data = file_get_contents($url, false, $context);
    
    // if ($data === false) {
    //     die("Still failed. Your PHP might have stream wrappers disabled.\n");
    // }

    // return $data;

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false, // optional, but helps local testing
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0', // some hosts block missing UA
        CURLOPT_TIMEOUT => 15,
    ]);

    $data = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        echo "cURL error: " . curl_error($ch) . PHP_EOL;
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "HTTP $httpCode returned when fetching image." . PHP_EOL;
        return null;
    }

    return $data;
}
// /helpers



if (!isset($_FILES['images'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => "❌ Watermark file not found at: $watermarkPath"]);
    exit;
}

$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$files = $_FILES['images'];
$count = count($files['name']);
$savedFiles = [];

for ($i = 0; $i < $count; $i++) {
    $tmpName = $files['tmp_name'][$i];
    $fileName = basename($files['name'][$i]);
    $destination = $uploadDir . $fileName;

    if (move_uploaded_file($tmpName, $destination)) {
        $savedFiles[] = $destination;
    }
}



// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (count($savedFiles) > 0) {
    // $urls = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));

    if (!file_exists($watermarkPath)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => "❌ Watermark file not found at: $watermarkPath"]);
        exit;
    }

    // Load watermark
    $watermark = imagecreatefrompng($watermarkPath);
    $wmWidth = imagesx($watermark);
    $wmHeight = imagesy($watermark);

    // foreach ($urls as $url) {
    foreach ($savedFiles as $url) {
        // $url = str_replace("135/135", "900/900", $url);
        $result = ['url' => $url, 'status' => '', 'original' => '', 'watermarked' => ''];

        // dl image ***************************************************
        // $imageData = fetchWithFileGetContents($url);
        // $imageData = fetchWithCurl($url);
        // $imageData = fetchWithCurlAdvanced($url);
        // $imageData = fetchImageData($url);
        $imageData = @file_get_contents($url);

        if ($imageData === false) {
            $result['status'] = '❌ Failed to download image.';
            $results[] = $result;
            continue;
        }
        // /dl image *************************************************

        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            $result['status'] = '❌ Invalid image.';
            $results[] = $result;
            continue;
        }

        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_PNG:
                $mainImage = imagecreatefromstring($imageData);
                $ext = ($imageInfo[2] == IMAGETYPE_JPEG) ? 'jpg' : 'png';
                break;
            default:
                $result['status'] = '❌ Unsupported format.';
                $results[] = $result;
                continue 2;
        }

        // Filename
        $filenameBase = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME) ?: uniqid('img');
        $filenameBase = str_replace(['-MK', '-mk'], '-AK', $filenameBase);
        $originalPath = "$outputFolder/{$filenameBase}.$ext";
        $watermarkedPath = "$outputFolder/{$filenameBase}_wm.$ext";

        // Save original
        if ($ext === 'jpg') {
            imagejpeg($mainImage, $originalPath, 90);
        } else {
            imagepng($mainImage, $originalPath);
        }

        // Resize watermark
        $mainWidth = imagesx($mainImage);
        $mainHeight = imagesy($mainImage);
        $newWmWidth = $mainWidth * 0.5;
        $newWmHeight = ($newWmWidth / $wmWidth) * $wmHeight;

        $resizedWatermark = imagecreatetruecolor($newWmWidth, $newWmHeight);
        imagealphablending($resizedWatermark, false);
        imagesavealpha($resizedWatermark, true);
        imagecopyresampled($resizedWatermark, $watermark, 0, 0, 0, 0, $newWmWidth, $newWmHeight, $wmWidth, $wmHeight);

        // Center watermark
        $destX = ($mainWidth - $newWmWidth) / 2;
        $destY = ($mainHeight - $newWmHeight) / 2;
        imagecopy($mainImage, $resizedWatermark, $destX, $destY, 0, 0, $newWmWidth, $newWmHeight);

        // Save watermarked
        if ($ext === 'jpg') {
            imagejpeg($mainImage, $watermarkedPath, 90);
        } else {
            imagepng($mainImage, $watermarkedPath);
        }

        imagedestroy($mainImage);
        imagedestroy($resizedWatermark);

        $result['status'] = 'Success';
        $result['original'] = basename($originalPath);
        $result['watermarked'] = basename($watermarkedPath);
        $results[] = $result;
    }

    imagedestroy($watermark);

    header('Content-Type: application/json');
    echo json_encode(['results' => $results]);
    exit;
}
?>
