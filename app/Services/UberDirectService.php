<?php

namespace App\Services;

use App\Models\DeliveryModel;
use App\Models\OrderModel;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

class UberDirectService
{
    protected CURLRequest $httpClient;
    protected DeliveryModel $deliveryModel;
    protected OrderModel $orderModel;

    protected string $baseUrl = 'https://api.uber.com/v1';
    protected string $oauthTokenUrl = 'https://login.uber.com/oauth/v2/token';

    public function __construct()
    {
        $this->httpClient    = Services::curlrequest();
        $this->deliveryModel = new DeliveryModel();
        $this->orderModel    = new OrderModel();

        // Allow sandbox/prod switching via env without code changes.
        // Defaults remain production unless explicitly overridden.
        $env = strtolower(trim((string) (getenv('UBER_ENV') ?: '')));
        $env = $env === 'sandbox' ? 'sandbox' : 'production';

        $baseUrlOverride = trim((string) (getenv('UBER_API_BASE_URL') ?: ''));
        $tokenUrlOverride = trim((string) (getenv('UBER_OAUTH_TOKEN_URL') ?: ''));

        if ($baseUrlOverride !== '') {
            $this->baseUrl = rtrim($baseUrlOverride, '/');
        } elseif ($env === 'sandbox') {
            $this->baseUrl = 'https://sandbox-api.uber.com/v1';
        }

        if ($tokenUrlOverride !== '') {
            $this->oauthTokenUrl = $tokenUrlOverride;
        } elseif ($env === 'sandbox') {
            $this->oauthTokenUrl = 'https://sandbox-login.uber.com/oauth/v2/token';
        }
    }

    public function requestDelivery(array $order): ?array
    {
        $orderId = $order['id'] ?? null;
        if (! $orderId) {
            log_message('error', 'UberDirectService requestDelivery missing order id');
            return null;
        }

        $customerId = getenv('CUSTOMER_ID') ?: getenv('UBER_CUSTOMER_ID');
        if (! $customerId) {
            log_message('error', 'UberDirectService requestDelivery missing CUSTOMER_ID');
            return null;
        }

        // De-dupe: if we already have a delivery for this order, don't create another.
        $existingDelivery = $this->deliveryModel
            ->where('order_id', $orderId)
            ->where('provider', 'uber_direct')
            ->orderBy('id', 'DESC')
            ->first();
        if ($existingDelivery) {
            log_message('info', 'UberDirectService requestDelivery skipped (already exists)', [
                'order_id'    => $orderId,
                'delivery_id' => $existingDelivery['external_delivery_id'] ?? null,
            ]);
            return [
                'delivery_id' => $existingDelivery['external_delivery_id'] ?? null,
                'status'      => $existingDelivery['delivery_status'] ?? null,
                'skipped'     => true,
            ];
        }

        $pickupAddress  = getenv('RESTAURANT_ADDRESS');
        $dropoffAddress = $order['address'] ?? '';
        $customerPhone  = $order['phone'] ?? '';

        $itemsDescription = $order['items_description'] ?? ('Order #' . ($order['id'] ?? ''));

        $payload = [
            'pickup_address'  => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'customer_phone'  => $customerPhone,
            'order_description' => $itemsDescription,
            'pickup'  => [
                'address' => $pickupAddress,
                'contact' => [
                    'first_name' => getenv('RESTAURANT_NAME') ?: 'Restaurant',
                    'phone'      => getenv('RESTAURANT_PHONE') ?: '',
                ],
            ],
            'dropoff' => [
                'address' => $dropoffAddress,
                'contact' => [
                    'first_name' => $order['customer_name'] ?? 'Customer',
                    'phone'      => $customerPhone,
                ],
                'notes'   => $itemsDescription,
            ],
        ];

        $url = $this->baseUrl . '/customers/' . rawurlencode($customerId) . '/deliveries';

        $maxAttempts = (int) (getenv('UBER_DIRECT_RETRY_MAX_ATTEMPTS') ?: 3);
        $baseDelayMs = (int) (getenv('UBER_DIRECT_RETRY_BASE_DELAY_MS') ?: 250);
        $maxDelayMs  = (int) (getenv('UBER_DIRECT_RETRY_MAX_DELAY_MS') ?: 4000);
        $maxAttempts = max(1, min($maxAttempts, 6));
        $baseDelayMs = max(0, $baseDelayMs);
        $maxDelayMs  = max($baseDelayMs, $maxDelayMs);

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            log_message('error', 'UberDirectService requestDelivery missing access token');
            return null;
        }

        $lastErrorContext = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->httpClient->post($url, [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                    // Avoid throwing exceptions on 4xx/5xx so we can decide retry policy.
                    'http_errors' => false,
                    'json'        => $payload,
                    'timeout'     => 10,
                ]);

                $statusCode = $response->getStatusCode();
                $body       = $response->getBody();
                $data       = json_decode($body, true);
                $data       = is_array($data) ? $data : ['raw' => $body];

                // Success path: store delivery record.
                if ($statusCode >= 200 && $statusCode < 300) {
                    $deliveryId = $data['delivery_id'] ?? $data['id'] ?? null;
                    if (! $deliveryId) {
                        log_message('error', 'UberDirectService requestDelivery missing delivery id in response', [
                            'status_code' => $statusCode,
                            'response'    => $data,
                        ]);
                        return null;
                    }

                    $this->deliveryModel->insert([
                        'order_id'             => $orderId,
                        'provider'             => 'uber_direct',
                        'external_delivery_id' => $deliveryId,
                        'delivery_status'      => $data['status'] ?? 'requested',
                        'pickup_address'       => $pickupAddress,
                        'dropoff_address'      => $dropoffAddress,
                        'fee'                  => $data['fee'] ?? null,
                        'raw_request'          => json_encode($payload),
                        'raw_response'         => json_encode($data),
                        'created_at'           => date('Y-m-d H:i:s'),
                    ]);

                    log_message('info', 'UberDirectService requestDelivery created', [
                        'order_id'    => $orderId,
                        'delivery_id' => $deliveryId,
                        'status'      => $data['status'] ?? null,
                        'attempt'     => $attempt,
                    ]);

                    return $data;
                }

                // Non-success: decide whether to retry.
                $isRetryable = in_array($statusCode, [408, 425, 429, 500, 502, 503, 504], true);
                $lastErrorContext = [
                    'status_code' => $statusCode,
                    'response'    => $data,
                ];

                if (! $isRetryable || $attempt === $maxAttempts) {
                    log_message('error', 'UberDirectService requestDelivery failed', [
                        'order_id'     => $orderId,
                        'attempt'      => $attempt,
                        'max_attempts' => $maxAttempts,
                        'status_code'  => $statusCode,
                        'response'     => $data,
                    ]);
                    return null;
                }

                $delayMs = min($maxDelayMs, $baseDelayMs * (2 ** ($attempt - 1)));
                log_message('warning', 'UberDirectService requestDelivery retrying', [
                    'order_id'    => $orderId,
                    'attempt'     => $attempt,
                    'next_delay_ms' => $delayMs,
                    'status_code' => $statusCode,
                ]);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            } catch (\Throwable $e) {
                $lastErrorContext = ['exception' => $e->getMessage()];
                if ($attempt === $maxAttempts) {
                    log_message('error', 'UberDirectService requestDelivery error: {message}', [
                        'message' => $e->getMessage(),
                        'order_id' => $orderId,
                        'attempt'  => $attempt,
                    ]);
                    return null;
                }

                $delayMs = min($maxDelayMs, $baseDelayMs * (2 ** ($attempt - 1)));
                log_message('warning', 'UberDirectService requestDelivery exception retrying', [
                    'order_id'       => $orderId,
                    'attempt'        => $attempt,
                    'next_delay_ms'  => $delayMs,
                    'error_message'  => $e->getMessage(),
                ]);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        log_message('error', 'UberDirectService requestDelivery exhausted retries', [
            'order_id' => $orderId,
            'last'     => $lastErrorContext,
        ]);
        return null;
    }

    protected function getAccessToken(): ?string
    {
        $clientId     = getenv('UBER_CLIENT_ID');
        $clientSecret = getenv('UBER_CLIENT_SECRET');
        $grantType    = getenv('GRANT_TYPE') ?: 'client_credentials';
        $scope        = getenv('SCOPE') ?: 'eats.store eats.order';
        $scope        = trim($scope);
        $scope        = trim($scope, "\"'");

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        try {
            $response = $this->httpClient->post($this->oauthTokenUrl, [
                'form_params' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => $grantType,
                    'scope'         => $scope,
                ],
                'timeout' => 10,
            ]);

            $data = json_decode($response->getBody(), true) ?? [];

            return $data['access_token'] ?? null;
        } catch (\Throwable $e) {
            log_message('error', 'UberDirectService getAccessToken error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

