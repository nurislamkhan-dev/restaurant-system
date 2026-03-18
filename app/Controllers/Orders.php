<?php

namespace App\Controllers;

use App\Models\DeliveryJobModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Services\UberDirectService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class Orders extends BaseController
{
    use ResponseTrait;

    protected OrderModel $orderModel;
    protected OrderItemModel $orderItemModel;
    protected UberDirectService $uberDirectService;
    protected DeliveryJobModel $deliveryJobModel;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->orderModel       = new OrderModel();
        $this->orderItemModel   = new OrderItemModel();
        $this->uberDirectService= new UberDirectService();
        $this->deliveryJobModel = new DeliveryJobModel();
        $this->db               = Database::connect();
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (! $data) {
            log_message('error', 'Orders::create invalid JSON payload');
            return $this->failValidationErrors('Invalid JSON payload');
        }

        if (
            empty($data['customer_name']) ||
            empty($data['phone']) ||
            empty($data['address']) ||
            empty($data['items']) ||
            ! is_array($data['items'])
        ) {
            log_message('error', 'Orders::create missing required fields', ['payload' => $data]);
            return $this->failValidationErrors('Missing required fields');
        }

        $items = [];
        $total = 0;

        foreach ($data['items'] as $item) {
            if (! is_array($item) || empty($item['name']) || ! array_key_exists('qty', $item)) {
                return $this->failValidationErrors('Each item must have name and qty');
            }

            $qty   = (int) $item['qty'];
            if ($qty < 1) {
                return $this->failValidationErrors('Each item qty must be at least 1');
            }

            $price = null;
            if (array_key_exists('price', $item) && $item['price'] !== null && $item['price'] !== '') {
                if (! is_numeric($item['price'])) {
                    return $this->failValidationErrors('Item price must be numeric when provided');
                }
                $price = (float) $item['price'];
                if ($price < 0) {
                    return $this->failValidationErrors('Item price cannot be negative');
                }
            }

            $items[] = [
                'item_name' => $item['name'],
                'quantity'  => $qty,
                'price'     => $price,
            ];

            if ($price !== null) {
                $total += $qty * $price;
            }
        }

        $this->db->transStart();

        $orderId = $this->orderModel->insert([
            'external_order_id'  => null,
            'order_source'       => 'website',
            'customer_name'      => $data['customer_name'],
            'phone'              => $data['phone'],
            'address'            => $data['address'],
            'status'             => 'pending',
            'total_amount'       => $total > 0 ? $total : null,
            'source_raw_payload' => json_encode($data),
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        if (! $orderId) {
            $this->db->transRollback();
            log_message('error', 'Orders::create failed to insert order', ['payload' => $data]);
            return $this->failServerError('Failed to create order');
        }

        foreach ($items as $item) {
            $item['order_id']   = $orderId;
            $item['created_at'] = date('Y-m-d H:i:s');

            $itemId = $this->orderItemModel->insert($item);
            if (! $itemId) {
                $this->db->transRollback();
                log_message('error', 'Orders::create failed to insert order item', [
                    'order_id' => $orderId,
                    'payload'  => $data,
                ]);
                return $this->failServerError('Failed to create order items');
            }
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            log_message('error', 'Orders::create transaction failed', ['payload' => $data]);
            return $this->failServerError('Failed to create order');
        }

        $order = $this->orderModel->find($orderId);
        $createdItems = $this->orderItemModel
            ->where('order_id', $orderId)
            ->findAll();

        log_message('info', 'Orders::create order created', ['order_id' => $orderId, 'source' => 'website']);

        return $this->respondCreated([
            'order' => $order,
            'items' => array_map(static function ($item) {
                return [
                    'name'  => $item['item_name'],
                    'qty'   => $item['quantity'],
                    'price' => $item['price'],
                ];
            }, $createdItems),
        ]);
    }

    public function index()
    {
        $orders = $this->orderModel
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $result = [];

        foreach ($orders as $order) {
            $items = $this->orderItemModel
                ->where('order_id', $order['id'])
                ->findAll();

            $delivery = model('App\Models\DeliveryModel')
                ->where('order_id', $order['id'])
                ->orderBy('id', 'DESC')
                ->first();

            $result[] = [
                'id'             => $order['id'],
                'external_order_id' => $order['external_order_id'],
                'order_source'   => $order['order_source'],
                'customer_name'  => $order['customer_name'],
                'phone'          => $order['phone'],
                'address'        => $order['address'],
                'status'         => $order['status'],
                'total_amount'   => $order['total_amount'],
                'created_at'     => $order['created_at'],
                'delivery'       => $delivery ? [
                    'provider'            => $delivery['provider'],
                    'external_delivery_id'=> $delivery['external_delivery_id'],
                    'delivery_status'     => $delivery['delivery_status'],
                ] : null,
                'items'          => array_map(static function ($item) {
                    return [
                        'name'  => $item['item_name'],
                        'qty'   => $item['quantity'],
                        'price' => $item['price'],
                    ];
                }, $items),
            ];
        }

        return $this->respond($result);
    }

    public function updateStatus($id = null)
    {
        if (! $id) {
            return $this->failValidationErrors('Order ID is required');
        }

        $order = $this->orderModel->find($id);

        if (! $order) {
            log_message('warning', 'Orders::updateStatus order not found', ['order_id' => $id]);
            return $this->failNotFound('Order not found');
        }

        $data = $this->request->getJSON(true);

        if (empty($data['status'])) {
            return $this->failValidationErrors('Status is required');
        }

        $newStatus = $data['status'];

        $this->orderModel->update($id, [
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        log_message('info', 'Orders::updateStatus updated', ['order_id' => $id, 'status' => $newStatus]);

        if ($newStatus === 'READY_FOR_PICKUP' || $newStatus === 'ready_for_pickup') {
            if (($order['order_source'] ?? null) !== 'website') {
                return $this->respond([
                    'message' => 'Order status updated',
                ]);
            }

            $existingDelivery = model('App\Models\DeliveryModel')
                ->where('order_id', $order['id'])
                ->where('provider', 'uber_direct')
                ->orderBy('id', 'DESC')
                ->first();

            if ($existingDelivery) {
                return $this->respond([
                    'message' => 'Order status updated',
                ]);
            }

            $existingJob = $this->deliveryJobModel
                ->where('order_id', $order['id'])
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('id', 'DESC')
                ->first();

            if (! $existingJob) {
                $this->deliveryJobModel->insert([
                    'order_id'    => $order['id'],
                    'job_type'    => 'uber_direct',
                    'status'      => 'pending',
                    'attempts'    => 0,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                log_message('info', 'Orders::updateStatus queued uber direct delivery', ['order_id' => $id]);
            } else {
                log_message('info', 'Orders::updateStatus uber direct job already queued', [
                    'order_id' => $id,
                    'job_id'   => $existingJob['id'],
                ]);
            }
        }

        return $this->respond([
            'message' => 'Order status updated',
        ]);
    }
}

