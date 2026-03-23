<?php

namespace App\Controllers;

use App\Services\UberDirectService;
use CodeIgniter\API\ResponseTrait;

class UberDirect extends BaseController
{
    use ResponseTrait;

    protected function requireDashboardAuth()
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            return $this->failUnauthorized('Unauthorized');
        }

        return null;
    }

    /**
     * POST JSON: { "pickup_address": { ... }, "dropoff_address": { ... } }
     * See https://developer.uber.com/docs/deliveries/api-reference
     */
    public function quote()
    {
        if (($auth = $this->requireDashboardAuth()) !== null) {
            return $auth;
        }

        $data = $this->request->getJSON(true);
        if (! is_array($data)) {
            return $this->failValidationErrors('Invalid JSON payload');
        }

        $pickup  = $data['pickup_address'] ?? null;
        $dropoff = $data['dropoff_address'] ?? null;
        if (! is_array($pickup) || ! is_array($dropoff)) {
            return $this->failValidationErrors('pickup_address and dropoff_address must be JSON objects');
        }

        $service = new UberDirectService();
        $result  = $service->deliveryQuote($pickup, $dropoff);

        if ($result['status_code'] === 0) {
            return $this->respond($result['data'], 502);
        }

        return $this->respond($result['data'], $result['ok'] ? 200 : $result['status_code']);
    }

    /**
     * POST JSON: full Uber Direct create-delivery body (quote_id, addresses as stringified JSON strings, lat/lng, manifest_items, …).
     */
    public function delivery()
    {
        if (($auth = $this->requireDashboardAuth()) !== null) {
            return $auth;
        }

        $data = $this->request->getJSON(true);
        if (! is_array($data)) {
            return $this->failValidationErrors('Invalid JSON payload');
        }

        if (empty($data['quote_id']) || ! is_string($data['quote_id'])) {
            return $this->failValidationErrors('quote_id is required');
        }

        $service = new UberDirectService();
        $result  = $service->createDaasDelivery($data);

        if ($result['status_code'] === 0) {
            return $this->respond($result['data'], 502);
        }

        return $this->respond($result['data'], $result['ok'] ? 200 : $result['status_code']);
    }
}
