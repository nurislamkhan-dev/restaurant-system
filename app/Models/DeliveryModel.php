<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryModel extends Model
{
    protected $table            = 'deliveries';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'order_id',
        'provider',
        'external_delivery_id',
        'delivery_status',
        'pickup_address',
        'dropoff_address',
        'fee',
        'last_webhook_event',
        'last_webhook_at',
        'raw_request',
        'raw_response',
        'created_at',
        'updated_at',
    ];
}

