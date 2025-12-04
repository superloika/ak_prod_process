<?php

class WooCommerceLocalProductCreator
{
    private $store_url;
    private $consumer_key;
    private $consumer_secret;

    public function __construct(string $store_url, string $consumer_key, string $consumer_secret)
    {
        $this->store_url       = rtrim($store_url, '/');
        $this->consumer_key    = $consumer_key;
        $this->consumer_secret = $consumer_secret;
    }

    /**
     * Create a simple product
     */
    public function createSimpleProduct(array $data): array
    {
        $defaults = [
            'type'               => 'simple',
            'status'             => 'publish',
            'catalog_visibility' => 'visible',
        ];

        return $this->request('POST', '/wc/v3/products', array_merge($defaults, $data));
    }

    /**
     * Create variable product + variations in one call
     */
    public function createVariableProduct(array $product_data, array $variations = []): array
    {
        $product_data['type'] = 'variable';
        if ($variations) {
            $product_data['variations'] = $variations;
        }
        return $this->request('POST', '/wc/v3/products', $product_data);
    }

    /**
     * Generic API request using cURL (no WordPress needed)
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->store_url . '/wp-json' . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->consumer_key . ':' . $this->consumer_secret)
            ],
            CURLOPT_SSL_VERIFYPEER => true,  // keep true in production
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($data && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $raw_response = curl_exec($ch);
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $response = json_decode($raw_response, true);

        if ($http_code >= 200 && $http_code < 300) {
            return $response; // Success
        }

        // Error handling
        $message = $response['message'] ?? 'Unknown API error';
        $code    = $response['code'] ?? 'unknown_error';

        throw new Exception("WooCommerce API Error ($http_code): $message (Code: $code)");
    }
}
