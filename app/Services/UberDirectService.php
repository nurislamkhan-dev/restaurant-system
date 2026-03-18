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

    public function __construct()
    {
        $this->httpClient    = Services::curlrequest();
        $this->deliveryModel = new DeliveryModel();
        $this->orderModel    = new OrderModel();
    }

    public function requestDelivery(array $order): ?array
    {
        $customerId = getenv('CUSTOMER_ID') ?: getenv('UBER_CUSTOMER_ID');
        if (! $customerId) {
            log_message('error', 'UberDirectService requestDelivery missing CUSTOMER_ID');
            return null;
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

        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . ($this->getAccessToken() ?? ''),
                ],
                'json'    => $payload,
                'timeout' => 10,
            ]);

            $data = json_decode($response->getBody(), true) ?? [];

            $deliveryId = $data['delivery_id'] ?? $data['id'] ?? null;
            if (! $deliveryId) {
                log_message('error', 'UberDirectService requestDelivery unexpected response', ['response' => $data]);
                return null;
            }

            $this->deliveryModel->insert([
                'order_id'            => $order['id'],
                'provider'            => 'uber_direct',
                'external_delivery_id'=> $deliveryId,
                'delivery_status'     => $data['status'] ?? 'requested',
                'pickup_address'      => $pickupAddress,
                'dropoff_address'     => $dropoffAddress,
                'fee'                 => $data['fee'] ?? null,
                'raw_request'         => json_encode($payload),
                'raw_response'        => json_encode($data),
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

            log_message('info', 'UberDirectService requestDelivery created', [
                'order_id'    => $order['id'] ?? null,
                'delivery_id' => $deliveryId,
                'status'      => $data['status'] ?? null,
            ]);

            return $data;
        } catch (\Throwable $e) {
            log_message('error', 'UberDirectService requestDelivery error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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
            $response = $this->httpClient->post('https://login.uber.com/oauth/v2/token', [
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

