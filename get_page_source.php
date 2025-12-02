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

function getProductImgLinks($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    
    $nodes = $xpath->query("//div[contains(@class,'cloud-zoom-thumb-container')]//a");
    
    return $xpath;

    $images = [];
    foreach ($nodes as $a) {
        
        $link = $a->getAttribute('href');
        if ($link) {
            $images[] = $link;
        }
    }
    return $images;
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = $_POST['url'];
    $html = getPageSource($url);

    if ($html) {
        $images = getProductImgLinks($html);
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
