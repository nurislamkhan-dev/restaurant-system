<?php

namespace App\Controllers;

use App\Models\DeliveryModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class UberWebhook extends BaseController
{
    use ResponseTrait;

    protected OrderModel $orderModel;
    protected OrderItemModel $orderItemModel;
    protected DeliveryModel $deliveryModel;

    public function __construct()
    {
        $this->orderModel     = new OrderModel();
        $this->orderItemModel = new OrderItemModel();
        $this->deliveryModel  = new DeliveryModel();
    }

    public function uberEatsOrders()
    {
        if (! $this->isValidWebhookSecret('UBER_EATS_WEBHOOK_SECRET')) {
            log_message('warning', 'UberWebhook::uberEatsOrders unauthorized webhook');
            return $this->failUnauthorized('Unauthorized webhook');
        }

        $payload = $this->request->getJSON(true);

        if (! $payload) {
            log_message('error', 'UberWebhook::uberEatsOrders invalid JSON payload');
            return $this->failValidationErrors('Invalid JSON payload');
        }

        if (empty($payload['order_id']) || empty($payload['customer_name']) || empty($payload['address'])) {
            log_message('error', 'UberWebhook::uberEatsOrders missing required fields', ['payload' => $payload]);
            return $this->failValidationErrors('Missing required fields');
        }

        $existing = $this->orderModel
            ->where('external_order_id', $payload['order_id'])
            ->where('order_source', 'uber_eats')
            ->first();

        if ($existing) {
            return $this->respond(['message' => 'Uber Eats order already received']);
        }

        $itemsData = $payload['items'] ?? [];
        if (! is_array($itemsData) || $itemsData === []) {
            return $this->failValidationErrors('Items are required');
        }

        $normalizedItems = [];
        foreach ($itemsData as $item) {
            if (! is_array($item) || empty($item['name']) || ! array_key_exists('qty', $item)) {
                return $this->failValidationErrors('Each item must have name and qty');
            }
            $qty = (int) $item['qty'];
            if ($qty < 1) {
                return $this->failValidationErrors('Each item qty must be at least 1');
            }

            if (! array_key_exists('price', $item) || $item['price'] === null || $item['price'] === '') {
                return $this->failValidationErrors('Each item must have price');
            }
            if (! is_numeric($item['price'])) {
                return $this->failValidationErrors('Item price must be numeric');
            }
            $price = (float) $item['price'];
            if ($price < 0) {
                return $this->failValidationErrors('Item price cannot be negative');
            }

            $normalizedItems[] = [
                'item_name' => $item['name'],
                'quantity'  => $qty,
                'price'     => $price,
            ];
        }

        $total = 0.0;
        foreach ($normalizedItems as $item) {
            $total += $item['quantity'] * (float) $item['price'];
        }

        $db = Database::connect();
        $db->transStart();

        $orderId = $this->orderModel->insert([
            'external_order_id'  => $payload['order_id'],
            'order_source'       => 'uber_eats',
            'customer_name'      => $payload['customer_name'],
            'phone'              => $payload['phone'] ?? 'unknown',
            'address'            => $payload['address'],
            'status'             => 'pending',
            'total_amount'       => $total,
            'source_raw_payload' => json_encode($payload),
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        if (! $orderId) {
            $db->transRollback();
            log_message('error', 'UberWebhook::uberEatsOrders failed to insert order', ['payload' => $payload]);
            return $this->failServerError('Failed to store Uber Eats order');
        }

        foreach ($normalizedItems as $item) {
            $itemId = $this->orderItemModel->insert([
                'order_id'   => $orderId,
                'item_name'  => $item['item_name'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $itemId) {
                $db->transRollback();
                log_message('error', 'UberWebhook::uberEatsOrders failed to insert order item', [
                    'order_id' => $orderId,
                    'payload'  => $payload,
                ]);
                return $this->failServerError('Failed to store Uber Eats order items');
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', 'UberWebhook::uberEatsOrders transaction failed', ['payload' => $payload]);
            return $this->failServerError('Failed to store Uber Eats order');
        }

        log_message('info', 'UberWebhook::uberEatsOrders order stored', [
            'external_order_id' => $payload['order_id'],
            'order_id'          => $orderId,
        ]);

        return $this->respond(['message' => 'Uber Eats order received']);
    }

    public function uberDirectStatus()
    {
        if (! $this->isValidWebhookSecret('UBER_DIRECT_WEBHOOK_SECRET')) {
            log_message('warning', 'UberWebhook::uberDirectStatus unauthorized webhook');
            return $this->failUnauthorized('Unauthorized webhook');
        }

        $payload = $this->request->getJSON(true);

        if (! $payload) {
            log_message('error', 'UberWebhook::uberDirectStatus invalid JSON payload');
            return $this->failValidationErrors('Invalid JSON payload');
        }

        $deliveryId = $payload['delivery_id'] ?? $payload['id'] ?? null;
        $status     = $payload['status'] ?? null;
        $event      = $payload['event'] ?? null;

        if (! $status && $event) {
            $status = $event;
        }

        if (! $deliveryId || ! $status) {
            log_message('error', 'UberWebhook::uberDirectStatus missing delivery_id or status', ['payload' => $payload]);
            return $this->failValidationErrors('Missing delivery_id or status');
        }

        $allowed = ['courier_assigned', 'courier_picked_up', 'delivered', 'cancelled'];
        if (! in_array($status, $allowed, true)) {
            log_message('info', 'UberWebhook::uberDirectStatus ignored status', [
                'delivery_id' => $deliveryId,
                'status'      => $status,
            ]);
            return $this->respond(['message' => 'Event ignored']);
        }

        $delivery = $this->deliveryModel
            ->where('external_delivery_id', $deliveryId)
            ->first();

        if (! $delivery) {
            log_message('warning', 'Uber Direct status for unknown delivery_id: {id}', ['id' => $deliveryId]);

            return $this->respond(['message' => 'Unknown delivery, ignored']);
        }

        $this->deliveryModel->update($delivery['id'], [
            'delivery_status'   => $status,
            'last_webhook_event'=> $event ?? $status,
            'last_webhook_at'   => date('Y-m-d H:i:s'),
            'raw_response'      => json_encode($payload),
        ]);

        log_message('info', 'UberWebhook::uberDirectStatus updated delivery', [
            'delivery_id' => $deliveryId,
            'status'      => $status,
            'order_id'    => $delivery['order_id'],
        ]);

        if ($status === 'delivered') {
            $this->orderModel->update($delivery['order_id'], [
                'status'     => 'delivered',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif ($status === 'cancelled') {
            $this->orderModel->update($delivery['order_id'], [
                'status'     => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->respond(['message' => 'Delivery status updated']);
    }
}

