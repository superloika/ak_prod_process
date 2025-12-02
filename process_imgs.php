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
$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($outputFolder)) {
    mkdir($outputFolder, 0777, true);
}

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$results = []; // response

if (!isset($_FILES['images'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => "❌ No images to process"]);
    exit;
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

if (count($savedFiles) > 0) {
    if (!file_exists($watermarkPath)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => "❌ Watermark file not found at: $watermarkPath"]);
        exit;
    }

    // Load watermark
    $watermark = imagecreatefrompng($watermarkPath);
    $wmWidth = imagesx($watermark);
    $wmHeight = imagesy($watermark);

    foreach ($savedFiles as $url) {
        $result = ['url' => $url, 'status' => '', 'original' => '', 'watermarked' => ''];

        $imageData = @file_get_contents($url);
        if ($imageData === false) {
            $result['status'] = '❌ Failed to download image.';
            $results[] = $result;
            continue;
        }
        
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
