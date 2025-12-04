<?php
header('Content-Type: application/json');

// Enable error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1); // set to 1 for debugging

require_once __DIR__ . '/wc_create_product.php';
require_once __DIR__ . '/wc_media_uploader.php';
require_once __DIR__ . '/env.php';

$response = [
    'success' => false,
    'message' => '',
    'data'    => []
];

try {
    // Check if it's a multipart/form-data request (FormData)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $img_ids = [];

    $wm_img_paths = $_POST['wm_img_paths'] ?? [];
    $category_ids = $_POST['category_ids'] ?? [];
    $brand_ids = $_POST['brand_ids'] ?? [];
    $manufacturer_ids = $_POST['manufacturer_ids'] ?? [];
    $name = $_POST['name'] ?? '';
    $sku = $_POST['sku'] ?? '';
    $short_description = $_POST['short_description'] ?? '';
    $description = $_POST['description'] ?? '';
    $product_id = $_POST['product_id'] ?? '';

    // upload images
    if(count($wm_img_paths) > 0) {
        $mediaUploader = new WooCommerceLocalMediaUploader(env('SITE_URL'), [
            'username'     => env('USER_NAME'),
            'app_password' => env('APP_PASSWORD')
        ]);

        foreach ($wm_img_paths as $path) {
            $image = $mediaUploader->uploadImage(__DIR__ . "\/output\/" . $path);
            $img_ids[] = $image["id"];
        }
    }

    // create product
    $wc = new WooCommerceLocalProductCreator(
        env('SITE_URL'),
        env('CONSUMER_KEY'),
        env('CONSUMER_SECRET')
    );

    $product = $wc->createSimpleProduct([
            'name'              => $name,
            'sku'               => $sku,
            'short_description' => $short_description,
            'description'       => $description,

            // Built-in fields (standard object format)
            // 'categories'        => [ ['id' => $category_id] ],  // car remotes term id
            'categories'        => array_map(fn($val) => ['id' => $val], $category_ids),

            // Custom fields (flat integer arrays – your real IDs!)
            'brands'            => $brand_ids,             // any of brands term ID
            'manufacturer'      => $manufacturer_ids,             //  any of Manufacturers term ID
            'images'            => array_map(fn($val) => ['id' => $val], $img_ids),
            'manage_stock'      => false,
            'stock_quantity'    => null,

            'meta_data' => [
                [ "key" => "_elementor_edit_mode", "value" => "" ],
                [ "key" => "_elementor_data", "value" => "" ],
                [ "key" => "_elementor_template_type", "value" => "" ],
                [ "key" => "_elementor_version", "value" => "" ],
                [ "key" => "_elementor_css", "value" => ""]
            ]
        ],
        $product_id
    );
    
    // Build response
    $response['success'] = true;
    $response['message'] = 'Success';
    $response['data'] = [
        'product_id' => $product["id"],
        'product_permalink' => $product["permalink"],
        // 'img_ids' => $img_ids,
        // 'category_id' => $category_id,
        // 'manufacturer_id' => $manufacturer_id,
        // 'brand_id' => $brand_id,
    ];
} catch (\Throwable $th) {
    http_response_code(400);
    $response['message'] = $th->getMessage();
}

// Always return JSON
echo json_encode($response, JSON_UNESCAPED_SLASHES);
