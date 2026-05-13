<?php
/**
 * classes/ShopeeAPI.php
 * Handles authentication, signature generation, and API requests for Shopee Open API v2.
 */
declare(strict_types=1);

class ShopeeAPI {
    private $partner_id;
    private $partner_key;
    private $is_test;
    private $base_url;

    public function __construct(string $partner_id, string $partner_key, bool $is_test = true) {
        $this->partner_id = $partner_id;
        $this->partner_key = $partner_key;
        $this->is_test = $is_test;
        $this->base_url = $is_test ? 'https://partner.test-stable.shopeemobile.com' : 'https://partner.shopeemobile.com';
    }

    /**
     * Generates the signature required by Shopee.
     */
    public function generateSignature(string $api_path, int $timestamp, string $access_token = '', string $shop_id = ''): string {
        $base_string = $this->partner_id . $api_path . $timestamp . $access_token . $shop_id;
        return hash_hmac('sha256', $base_string, $this->partner_key);
    }

    /**
     * Generates the URL for the user to authorize the app.
     */
    public function getAuthUrl(string $redirect_url): string {
        $api_path = '/api/v2/shop/auth_partner';
        $timestamp = time();
        $sign = $this->generateSignature($api_path, $timestamp);
        
        $params = [
            'partner_id' => $this->partner_id,
            'timestamp'  => $timestamp,
            'sign'       => $sign,
            'redirect'   => $redirect_url
        ];

        return $this->base_url . $api_path . '?' . http_build_query($params);
    }

    /**
     * Exchanges an authorization code for an access token.
     */
    public function getAccessToken(string $code, string $shop_id): array {
        $api_path = '/api/v2/auth/token/get';
        $timestamp = time();
        $sign = $this->generateSignature($api_path, $timestamp);
        
        $body = [
            'code' => $code,
            'shop_id' => (int) $shop_id,
            'partner_id' => (int) $this->partner_id
        ];

        return $this->postRow($api_path, $body, $timestamp, $sign);
    }

    /**
     * Refreshes an expired access token using the refresh token.
     */
    public function refreshToken(string $refresh_token, string $shop_id): array {
        $api_path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $sign = $this->generateSignature($api_path, $timestamp);
        
        $body = [
            'refresh_token' => $refresh_token,
            'shop_id' => (int) $shop_id,
            'partner_id' => (int) $this->partner_id
        ];

        return $this->postRow($api_path, $body, $timestamp, $sign);
    }

    /**
     * Generic GET request handler.
     */
    public function get(string $api_path, array $queryParams, string $access_token, string $shop_id): array {
        $timestamp = time();
        $sign = $this->generateSignature($api_path, $timestamp, $access_token, $shop_id);
        
        $baseParams = [
            'partner_id' => $this->partner_id,
            'timestamp'  => $timestamp,
            'access_token' => $access_token,
            'shop_id'    => $shop_id,
            'sign'       => $sign
        ];

        $url = $this->base_url . $api_path . '?' . http_build_query(array_merge($baseParams, $queryParams));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Generic POST request handler.
     */
    public function post(string $api_path, array $body, string $access_token, string $shop_id): array {
        $timestamp = time();
        $sign = $this->generateSignature($api_path, $timestamp, $access_token, $shop_id);
        
        $queryParams = [
            'partner_id' => $this->partner_id,
            'timestamp'  => $timestamp,
            'access_token' => $access_token,
            'shop_id'    => $shop_id,
            'sign'       => $sign
        ];

        $url = $this->base_url . $api_path . '?' . http_build_query($queryParams);

        return $this->postRow($url, $body);
    }

    private function postRow(string $api_path_or_url, array $body, int $timestamp = 0, string $sign = ''): array {
        if (strpos($api_path_or_url, 'http') === 0) {
            $url = $api_path_or_url;
        } else {
            // Internal use for auth where query params are constructed differently (without access_token)
            $queryParams = [
                'partner_id' => $this->partner_id,
                'timestamp'  => $timestamp,
                'sign'       => $sign
            ];
            $url = $this->base_url . $api_path_or_url . '?' . http_build_query($queryParams);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        return json_decode($response, true) ?? [];
    }
}
