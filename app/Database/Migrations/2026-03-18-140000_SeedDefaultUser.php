<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class SeedDefaultUser extends Migration
{
    public function up()
    {
        $db = Database::connect();

        $email = 'nurislamkhan.dev@gmail.com';

        $userData = [
            'name'       => 'Nur Islam Khan',
            'email'      => $email,
            'password'   => '$2y$10$nFLzV9A3bdLHQyDMMGRSE.wnAyGg0.RCKC0SxWWaXhZbEWaKY1mme',
            'status'     => 'active',
            'created_at' => '2026-03-17 19:33:53',
            'updated_at' => '2026-03-18 04:34:58',
        ];

        if (! method_exists($db, 'tableExists') || ! $db->tableExists('users')) {
            throw new \RuntimeException('SeedDefaultUser: users table does not exist');
        }

        // Idempotent seed: if email exists, ensure it matches this data.
        $existingByEmail = $db->table('users')->where('email', $email)->get()->getRowArray();
        if ($existingByEmail) {
            $ok = $db->table('users')->where('email', $email)->update([
                'name'       => $userData['name'],
                'password'   => $userData['password'],
                'status'     => $userData['status'],
                'updated_at' => $userData['updated_at'],
            ]);
            if (! $ok) {
                throw new \RuntimeException('SeedDefaultUser: update failed: ' . json_encode($db->error()));
            }
            return;
        }

        // Insert new row (let AUTO_INCREMENT assign id).
        $ok = $db->table('users')->insert($userData);
        if (! $ok) {
            throw new \RuntimeException('SeedDefaultUser: insert failed: ' . json_encode($db->error()));
        }
    }

    public function down()
    {
        $db = Database::connect();
        $db->table('users')->where('email', 'nurislamkhan.dev@gmail.com')->delete();
    }
}

