<?php
header('Content-Type: application/json');

function getPageSource($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $source = curl_exec($ch);
    curl_close($ch);

    return $source ?: false;
}

function getImagesFromThumbnailsCarousel($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//div[contains(@class,'thumbnailsCarousel')]//img");

    $images = [];
    foreach ($nodes as $img) {
        $src = $img->getAttribute('src');
        if (!$src) { // handle lazy-loading
            $src = $img->getAttribute('data-src');
        }
        if ($src) {
            $src = 'https://www.mk3.com' . str_replace("135/135", "900/900", $src);
            $images[] = $src;
        }
    }
    return $images;
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = $_POST['url'];
    $html = getPageSource($url);

    if ($html) {
        $images = getImagesFromThumbnailsCarousel($html);
        echo json_encode([
            "source" => $html,
            "images" => $images
        ]);
    } else {
        echo json_encode([
            "source" => null,
            "images" => []
        ]);
    }
}
