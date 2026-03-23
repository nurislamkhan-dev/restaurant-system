<?php

namespace App\Services;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

class UberEatsSandboxService
{
    protected CURLRequest $httpClient;

    /**
     * If set (e.g. from dashboard session after Uber sandbox login), use this token
     * instead of fetching a new one with client credentials.
     */
    protected ?string $sessionAccessToken = null;

    // Uber Eats Marketplace sandbox domains (per Uber docs).
    // - Token: https://sandbox-login.uber.com/oauth/v2/token
    // - API:    https://test-api.uber.com/v1
    protected string $baseUrl = 'https://test-api.uber.com/v1';
    protected string $oauthTokenUrl = 'https://sandbox-login.uber.com/oauth/v2/token';

    public function __construct(?string $sessionAccessToken = null)
    {
        $this->httpClient = Services::curlrequest();
        $t = $sessionAccessToken !== null ? trim($sessionAccessToken) : '';
        $this->sessionAccessToken = $t !== '' ? $t : null;

        // Allow overrides for sandbox vs program-specific setups.
        $apiBaseOverride = trim((string) (getenv('UBER_EATS_API_BASE_URL') ?: ''));
        if ($apiBaseOverride !== '') {
            $this->baseUrl = rtrim($apiBaseOverride, '/');
        }

        $tokenUrlOverride = trim((string) (getenv('UBER_EATS_OAUTH_TOKEN_URL') ?: ''));
        if ($tokenUrlOverride !== '') {
            $this->oauthTokenUrl = $tokenUrlOverride;
        }
    }

    public function getStore(string $storeId): ?array
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return null;
        }

        $url = $this->baseUrl . '/delivery/stores/' . rawurlencode($storeId);

        $response = $this->httpClient->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'timeout'     => 15,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $data = is_array($data) ? $data : ['raw' => $body];

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'error' => true,
                'status_code' => $statusCode,
                'response' => $data,
            ];
        }

        return $data;
    }

    public function acceptPosOrder(string $orderId): ?array
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return null;
        }

        $url = $this->baseUrl . '/delivery/orders/' . rawurlencode($orderId) . '/accept_pos_order';

        $response = $this->httpClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'timeout'     => 15,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $data = is_array($data) ? $data : ['raw' => $body];

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'error' => true,
                'status_code' => $statusCode,
                'response' => $data,
            ];
        }

        return $data;
    }

    protected function getAccessToken(): ?string
    {
        if ($this->sessionAccessToken !== null) {
            return $this->sessionAccessToken;
        }

        $clientId = getenv('UBER_CLIENT_ID');
        $clientSecret = getenv('UBER_CLIENT_SECRET');
        $grantType = getenv('GRANT_TYPE') ?: 'client_credentials';

        // Prefer the explicit sandbox scope if provided, otherwise fall back to repo defaults.
        $scope = getenv('UBER_SANDBOX_SCOPE')
            ?: (getenv('UBER_EATS_SANDBOX_SCOPE') ?: (getenv('SCOPE') ?: 'eats.store eats.order'));

        $scope = trim((string) $scope);
        $scope = trim($scope, "\"'");

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        try {
            $response = $this->httpClient->post($this->oauthTokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'http_errors' => false,
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => $grantType,
                    'scope' => $scope,
                ],
                'timeout' => 15,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            $data = is_array($data) ? $data : [];

            if ($statusCode < 200 || $statusCode >= 300) {
                return null;
            }

            return $data['access_token'] ?? null;
        } catch (\Throwable $e) {
            log_message('error', 'UberEatsSandboxService getAccessToken error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

