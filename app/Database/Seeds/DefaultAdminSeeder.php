<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Creates the first Super Admin login. Change this password immediately
 * after first login — see Setup Instructions §7.
 */
class DefaultAdminSeeder extends Seeder
{
    public function run()
    {
        $email = env('app.superAdminEmail', 'admin@sanvima.com');

        $exists = $this->db->table('users')->where('email', $email)->countAllResults() > 0;

        if ($exists) {
            return;
        }

        $role = $this->db->table('roles')->select('id')->where('slug', 'super_admin')->get()->getRowArray();

        $this->db->table('users')->insert([
            'name'          => 'Phani',
            'email'         => $email,
            'phone'         => null,
            'password_hash' => password_hash('ChangeMe@123', PASSWORD_DEFAULT),
            'role_id'       => $role['id'],
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
