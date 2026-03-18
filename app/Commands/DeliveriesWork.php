<?php

namespace App\Commands;

use App\Models\DeliveryJobModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Services\UberDirectService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class DeliveriesWork extends BaseCommand
{
    protected $group       = 'Deliveries';
    protected $name        = 'deliveries:work';
    protected $description = 'Process pending delivery jobs';

    protected $options = [
        '--once'  => 'Process one job and exit',
        '--limit' => 'Max jobs to process (default: 50)',
    ];

    public function run(array $params)
    {
        $once  = array_key_exists('once', $params);
        $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
        $limit = max(1, min($limit, 500));

        $db = Database::connect();

        $jobModel   = new DeliveryJobModel();
        $orderModel = new OrderModel();
        $itemModel  = new OrderItemModel();
        $uberDirect = new UberDirectService();

        $processed = 0;

        while ($processed < $limit) {
            $now = date('Y-m-d H:i:s');
            $job = null;

            $db->transStart();

            // Pick one available pending job.
            $job = $jobModel
                ->where('status', 'pending')
                ->groupStart()
                    ->where('available_at IS NULL', null, false)
                    ->orWhere('available_at <=', $now)
                ->groupEnd()
                ->orderBy('id', 'ASC')
                ->first();

            if (! $job) {
                $db->transComplete();
                break;
            }

            $jobModel->update($job['id'], [
                'status'     => 'processing',
                'locked_at'  => $now,
                'updated_at' => $now,
            ]);

            $db->transComplete();

            $processed++;

            if (($job['job_type'] ?? '') !== 'uber_direct') {
                $jobModel->update($job['id'], [
                    'status'     => 'failed',
                    'last_error' => 'Unsupported job_type: ' . ($job['job_type'] ?? ''),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                CLI::write("Job #{$job['id']} failed (unsupported job_type)", 'red');
                if ($once) {
                    break;
                }
                continue;
            }

            $order = $orderModel->find($job['order_id']);
            if (! $order) {
                $jobModel->update($job['id'], [
                    'status'     => 'failed',
                    'last_error' => 'Order not found',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                CLI::write("Job #{$job['id']} failed (order not found)", 'red');
                if ($once) {
                    break;
                }
                continue;
            }

            // Only website orders should create Uber Direct deliveries.
            if (($order['order_source'] ?? null) !== 'website') {
                $jobModel->update($job['id'], [
                    'status'     => 'succeeded',
                    'updated_at' => date('Y-m-d H:i:s'),
                    'last_error' => null,
                ]);
                CLI::write("Job #{$job['id']} skipped (order_source not website)", 'yellow');
                if ($once) {
                    break;
                }
                continue;
            }

            $items = $itemModel->where('order_id', $order['id'])->findAll();
            $descriptionParts = [];
            foreach ($items as $item) {
                $descriptionParts[] = $item['quantity'] . 'x ' . $item['item_name'];
            }
            $order['items_description'] = implode(', ', $descriptionParts);

            $result = $uberDirect->requestDelivery($order);
            $attempts = ((int) ($job['attempts'] ?? 0)) + 1;

            if ($result === null) {
                $maxJobAttempts = (int) (getenv('DELIVERY_JOB_MAX_ATTEMPTS') ?: 5);
                $maxJobAttempts = max(1, min($maxJobAttempts, 20));

                if ($attempts >= $maxJobAttempts) {
                    $jobModel->update($job['id'], [
                        'status'     => 'failed',
                        'attempts'   => $attempts,
                        'last_error' => 'Uber Direct request failed (max attempts reached)',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    CLI::write("Job #{$job['id']} failed (max attempts reached)", 'red');
                } else {
                    // Simple requeue delay (seconds).
                    $requeueDelay = (int) (getenv('DELIVERY_JOB_RETRY_DELAY_SECONDS') ?: 30);
                    $requeueDelay = max(0, min($requeueDelay, 3600));
                    $availableAt  = date('Y-m-d H:i:s', time() + $requeueDelay);

                    $jobModel->update($job['id'], [
                        'status'       => 'pending',
                        'attempts'     => $attempts,
                        'available_at' => $availableAt,
                        'locked_at'    => null,
                        'last_error'   => 'Uber Direct request failed (will retry)',
                        'updated_at'   => date('Y-m-d H:i:s'),
                    ]);
                    CLI::write("Job #{$job['id']} requeued (attempt {$attempts})", 'yellow');
                }
            } else {
                $jobModel->update($job['id'], [
                    'status'     => 'succeeded',
                    'attempts'   => $attempts,
                    'locked_at'  => null,
                    'last_error' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                CLI::write("Job #{$job['id']} succeeded", 'green');
            }

            if ($once) {
                break;
            }
        }

        CLI::write("Processed {$processed} job(s).", 'white');
    }
}

