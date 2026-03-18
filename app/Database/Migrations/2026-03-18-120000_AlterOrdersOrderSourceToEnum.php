<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterOrdersOrderSourceToEnum extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('orders', [
            'order_source' => [
                'type'       => 'ENUM',
                'constraint' => ['website', 'uber_eats'],
                'null'       => false,
                'default'    => 'website',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('orders', [
            'order_source' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
                'default'    => 'website',
            ],
        ]);
    }
}

