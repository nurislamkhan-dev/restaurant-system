<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterOrderItemsPriceNotNull extends Migration
{
    public function up()
    {
        // Ensure existing rows don't block the NOT NULL constraint.
        $this->db->query("UPDATE `order_items` SET `price` = 0.00 WHERE `price` IS NULL");

        $this->forge->modifyColumn('order_items', [
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => false,
                'default'    => 0.00,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('order_items', [
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
        ]);
    }
}

