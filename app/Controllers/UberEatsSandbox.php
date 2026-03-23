<?php

namespace App\Controllers;

use App\Services\UberEatsSandboxService;
use CodeIgniter\API\ResponseTrait;

class UberEatsSandbox extends BaseController
{
    use ResponseTrait;

    protected UberEatsSandboxService $service;

    public function __construct()
    {
        $session = session();
        $token   = null;
        if ($session->get('is_logged_in')) {
            $t = $session->get('uber_access_token') ?: $session->get('access_token');
            $exp = $session->get('uber_token_expires_at');
            if (is_string($t) && $t !== '' && ($exp === null || ! is_numeric($exp) || (int) $exp > time())) {
                $token = $t;
            }
        }

        $this->service = new UberEatsSandboxService($token);
    }

    protected function requireDashboardAuth()
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            return $this->failUnauthorized('Unauthorized');
        }

        return null;
    }

    public function store()
    {
        if (($auth = $this->requireDashboardAuth()) !== null) {
            return $auth;
        }

        $data = $this->request->getJSON(true);
        if (! $data) {
            return $this->failValidationErrors('Invalid JSON payload');
        }

        $storeId = $data['store_id'] ?? null;
        if (! $storeId) {
            return $this->failValidationErrors('store_id is required');
        }

        $result = $this->service->getStore((string) $storeId);

        if ($result === null) {
            return $this->failServerError('Failed to get store from Uber Eats sandbox');
        }

        if (is_array($result) && ($result['error'] ?? false) === true) {
            return $this->respond($result, 502);
        }

        return $this->respond($result);
    }

    public function acceptPosOrder()
    {
        if (($auth = $this->requireDashboardAuth()) !== null) {
            return $auth;
        }

        $data = $this->request->getJSON(true);
        if (! $data) {
            return $this->failValidationErrors('Invalid JSON payload');
        }

        $orderId = $data['order_id'] ?? null;
        if (! $orderId) {
            return $this->failValidationErrors('order_id is required');
        }

        $result = $this->service->acceptPosOrder((string) $orderId);

        if ($result === null) {
            return $this->failServerError('Failed to accept POS order in Uber Eats sandbox');
        }

        if (is_array($result) && ($result['error'] ?? false) === true) {
            return $this->respond($result, 502);
        }

        return $this->respond($result);
    }
}

