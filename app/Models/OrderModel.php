<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'orders';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'external_order_id',
        'order_source',
        'customer_name',
        'phone',
        'address',
        'status',
        'total_amount',
        'notes',
        'source_raw_payload',
        'created_at',
        'updated_at',
    ];
}

