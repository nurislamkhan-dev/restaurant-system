<?php

namespace App\Models;

use CodeIgniter\Model;

class DeliveryJobModel extends Model
{
    protected $table            = 'delivery_jobs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'order_id',
        'job_type',
        'status',
        'attempts',
        'available_at',
        'locked_at',
        'last_error',
        'created_at',
        'updated_at',
    ];
}

