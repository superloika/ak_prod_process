<?php

class WooCommerceLocalMediaUploader
{
    private $site_url;
    private $username;
    private $app_password;

    public function __construct($site_url, $auth = [])
    {
        $this->site_url = rtrim($site_url, '/');

        if (isset($auth['username']) && isset($auth['app_password'])) {
            $this->username     = $auth['username'];
            $this->app_password = $auth['app_password'];
        } else {
            throw new Exception("Authentication details missing");
        }
    }

    /**
     * Upload a local image to WordPress Media Library
     */
    public function uploadImage($local_file_path, $filename = null)
    {
        if (!file_exists($local_file_path)) {
            throw new Exception("File not found: $local_file_path");
        }

        $filename = $filename ?: basename($local_file_path);
        $file_content = file_get_contents($local_file_path);
        $content_type = mime_content_type($local_file_path) ?: 'application/octet-stream';

        $url = $this->site_url . '/wp-json/wp/v2/media';
        
        $headers = [
            'Content-Disposition: attachment; filename="' . $filename . '"',
            'Content-Type: ' . $content_type,
        ];

        $headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->app_password);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $file_content,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'WooCommerce Product Uploader/1.0',
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 201) {
            throw new Exception("Media upload failed (HTTP $http_code): $response");
        }

        $media = json_decode($response, true);
        
        return [
            'id'        => $media['id'],
            'src'       => $media['source_url'],
            'alt'       => $media['alt_text'] ?? '',
            'title'     => $media['title']['rendered'] ?? ''
        ];
    }
}