<?php
// ===== SETTINGS =====
$outputFolder = __DIR__ . '/output';
$watermarkPath = __DIR__ . '/watermark.png';

// Auto-create output folder
if (!is_dir($outputFolder)) {
    mkdir($outputFolder, 0777, true);
}

// Handle form submission
$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urls = array_filter(array_map('trim', explode("\n", $_POST['image_urls'])));

    if (!file_exists($watermarkPath)) {
        die("❌ Watermark file not found at: $watermarkPath");
    }

    // Load watermark
    $watermark = imagecreatefrompng($watermarkPath);
    $wmWidth = imagesx($watermark);
    $wmHeight = imagesy($watermark);

    foreach ($urls as $url) {
        $url = str_replace("135/135", "900/900", $url);

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
                $mainImage = imagecreatefromstring($imageData);
                $ext = 'jpg';
                break;
            case IMAGETYPE_PNG:
                $mainImage = imagecreatefromstring($imageData);
                $ext = 'png';
                break;
            default:
                $result['status'] = '❌ Unsupported format.';
                $results[] = $result;
                continue 2;
        }

        // Generate filenames
        $filenameBase = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME) ?: uniqid('img');
        $filenameBase = str_replace('-MK', '-AK', $filenameBase);
        $filenameBase = str_replace('-mk', '-AK', $filenameBase);
        $originalPath = "$outputFolder/{$filenameBase}.$ext";
        $watermarkedPath = "$outputFolder/{$filenameBase}_wm.$ext";

        // Save original
        if ($ext === 'jpg') {
            imagejpeg($mainImage, $originalPath, 90);
        } else {
            imagepng($mainImage, $originalPath);
        }

        // Resize watermark to 40% width of main image
        $mainWidth = imagesx($mainImage);
        $mainHeight = imagesy($mainImage);
        $newWmWidth = $mainWidth * 0.4;
        $newWmHeight = ($newWmWidth / $wmWidth) * $wmHeight;

        $resizedWatermark = imagecreatetruecolor($newWmWidth, $newWmHeight);
        imagealphablending($resizedWatermark, false);
        imagesavealpha($resizedWatermark, true);
        imagecopyresampled($resizedWatermark, $watermark, 0, 0, 0, 0, $newWmWidth, $newWmHeight, $wmWidth, $wmHeight);

        // Center watermark
        $destX = ($mainWidth - $newWmWidth) / 2;
        $destY = ($mainHeight - $newWmHeight) / 2;

        // Merge watermark
        imagecopy($mainImage, $resizedWatermark, $destX, $destY, 0, 0, $newWmWidth, $newWmHeight);

        // Save watermarked
        if ($ext === 'jpg') {
            imagejpeg($mainImage, $watermarkedPath, 90);
        } else {
            imagepng($mainImage, $watermarkedPath);
        }

        // Cleanup
        imagedestroy($mainImage);
        imagedestroy($resizedWatermark);

        $result['status'] = '✅ Success';
        $result['original'] = basename($originalPath);
        $result['watermarked'] = basename($watermarkedPath);
        $results[] = $result;
    }

    imagedestroy($watermark);
}
?>


<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>ak_prod_process</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        textarea {
            width: 100%;
            height: 60px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        * {
            box-sizing: border-box;
        }

        body {
            /* font-family: system-ui, sans-serif; */
            background-color: #f9fafb;
        }

        .container {
            display: block;
        }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 500;
            color: #374151;
        }

        textarea,
        input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #d1d5db;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
            background-color: #f9fafb;
        }

        #outputHTML {
            height: 30vh;
        }

        textarea:focus,
        input:focus {
            border-color: #3b82f6;
            background-color: white;
        }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            font-size: 0.85rem;
            font-weight: 500;
            background-color: #3b82f6;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        button:hover {
            background-color: #2563eb;
        }

        .card {
            background: white;
            padding: 0.75rem;
            box-shadow: rgba(0, 0, 0, 0.05) 0px 0px 0px 1px;
            width: 100%;
        }
    </style>
</head>

<body>

    <!-- Bulk Image Watermark -->
    <div class="card">
        <h3>Bulk Image Watermark</h3>
        <form method="post">
            <label>Enter image URLs (one per line):</label><br>
            <textarea name="image_urls" placeholder="https://example.com/image1.jpg"></textarea><br><br>
            <button type="submit">Process Images</button>
        </form>

        <?php if (!empty($results)): ?>
            <h2>Results</h2>
            <table>
                <tr>
                    <th>Image URL</th>
                    <th>Status</th>
                    <th>Original</th>
                    <th>Watermarked</th>
                </tr>
                <?php foreach ($results as $res): ?>
                    <tr>
                        <td><?= htmlspecialchars($res['url']) ?></td>
                        <td class="<?= strpos($res['status'], 'Success') !== false ? 'success' : 'error' ?>"><?= $res['status'] ?></td>
                        <td>
                            <?php if ($res['original']): ?>
                                <a href="output/<?= urlencode($res['original']) ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($res['watermarked']): ?>
                                <a href="output/<?= urlencode($res['watermarked']) ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <br>

    <!-- parser -->
    <div class="card">
        <h3>HTML Parser</h3>
        <p id="statusMessage" style="color: green; font-size: 14px;"></p>
        <div class="container">
            <div class="card">
                <button id="pasteClipboardBtn">Paste from Clipboard</button>
                <button id="processBtn">Parse</button>
                <textarea id="sourceHTML" placeholder="Paste your HTML here..."></textarea>
            </div>
            <br>
            <div class="card">
                <button id="copyOutputBtn">Copy Body</button>
                <input type="text" id="productSKU" placeholder="SKU">
                <input type="text" id="productTitle" placeholder="Title">
                <input type="text" id="productShortDesc" placeholder="Short description">
                <textarea id="outputHTML" placeholder="Body (processed HTML)"></textarea>
            </div>
        </div>
        <br>
        <div class="container">
            <div class="card">
                <h3>Preview:</h3>
                <div id="templatePreview" class="template-preview"></div>
            </div>
        </div>

        <script>
            const processBtn = document.getElementById('processBtn');
            const sourceHTML = document.getElementById('sourceHTML');
            const outputHTML = document.getElementById('outputHTML');
            const templatePreview = document.getElementById('templatePreview');

            const pasteClipboardBtn = document.getElementById('pasteClipboardBtn');
            const copyOutputBtn = document.getElementById('copyOutputBtn');

            // Temporary template
            const htmlTemplate = `<div id="prod_desc" class="kcard">
<div class="kcard-b">PROD_DESC</div>
<div></div>
</div>
<div style="height: 20px;"></div>
<div id="prod_specs" class="kcard">
<div class="kcard-h">Product Specifications</div>
<div class="kcard-b">
SPECS_TABLE
</div>
</div>
<div class="" style="height: 20px;"></div>
<div id="prod_fitments" class="kcard">
<div class="kcard-h">Fitments</div>
<div class="kcard-b">
<div id="Models" class="mb-5">
FITMENTS_TABLE
</div>
</div>
</div>
<div class="" style="height: 20px;"></div>
<div id="prod_acc" class="kcard">
<div class="kcard-h">Accessories</div>
<div class="kcard-b">[products name="accessories" skus="123"]</div>
</div>`;

            const statusMessage = document.getElementById("statusMessage");

            function showMessage(msg) {
                statusMessage.textContent = msg;
                statusMessage.style.opacity = 1;

                setTimeout(() => {
                    statusMessage.style.opacity = 0;
                    statusMessage.textContent = "";
                }, 2000);
            }

            processBtn.addEventListener('click', () => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(sourceHTML.value, 'text/html');

                // get SKU
                const skuMatch = sourceHTML.value.match(/\bMK\d{5}\b/);
                const prodSku = skuMatch ? (skuMatch[0]).replace('MK', 'AK') : "";

                const prodTitle = doc.querySelector("article.info-wrap2 h1").innerHTML;

                // Get .alert content
                const alertDiv = doc.querySelector('.alert');
                const prodDesc = alertDiv ? alertDiv.innerHTML.trim() : '';

                const prodShortDesc = `${prodDesc} | Product Number: ${prodSku}`;

                // Get .specsTable inside .inside-product-specs2
                let specsHTML = '';
                const specsContainer = doc.querySelector('.inside-product-specs2');
                if (specsContainer) {
                    const table = specsContainer.querySelector('.specsTable');
                    if (table) {
                        specsHTML = table.outerHTML.trim();
                    }
                }

                // Get .fitmentsTable outer HTML
                const fitmentsTable = doc.querySelector('.fitmentsTable');
                const fitmentsHTML = fitmentsTable ? fitmentsTable.outerHTML.trim() : '';

                // Fill placeholders
                let filledTemplate = htmlTemplate
                    .replace('PROD_DESC', `<strong>${prodDesc} | Product Number: ${prodSku}</strong>`)
                    .replace('SPECS_TABLE', specsHTML)
                    .replace('FITMENTS_TABLE', fitmentsHTML);

                // Output results
                document.getElementById("productSKU").value = prodSku;
                document.getElementById("productShortDesc").value = prodShortDesc;
                document.getElementById("productTitle").value = prodTitle;
                outputHTML.value = filledTemplate;
                templatePreview.innerHTML = filledTemplate;

                showMessage("Done parsing the HTML!");
            });

            // Paste clipboard into source textarea
            pasteClipboardBtn.addEventListener('click', async () => {
                try {
                    const text = await navigator.clipboard.readText();
                    sourceHTML.value = text;
                    showMessage("Pasted clipboard content into source!");
                } catch (err) {
                    // alert("Failed to read clipboard: " + err);
                }
            });

            // Copy output textarea to clipboard
            copyOutputBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(outputHTML.value).then(() => {
                    showMessage("Output HTML copied to clipboard!");
                });
            });
        </script>

    </div>
</body>

</html>