<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $userModel = new UserModel();

        // Admin user: Nur Islam Khan / nurislamkhan.dev@gmail.com / admin 123
        $passwordHash = '$2y$10$nFLzV9A3bdLHQyDMMGRSE.wnAyGg0.RCKC0SxWWaXhZbEWaKY1mme'; // password_hash('admin123', PASSWORD_DEFAULT)

        // Upsert by email
        $existing = $userModel->where('email', 'nurislamkhan.dev@gmail.com')->first();

        $data = [
            'name'       => 'Nur Islam Khan',
            'email'      => 'nurislamkhan.dev@gmail.com',
            'password'   => $passwordHash,
            'status'     => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $userModel->update($existing['id'], $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $userModel->insert($data);
        }
    }
}

