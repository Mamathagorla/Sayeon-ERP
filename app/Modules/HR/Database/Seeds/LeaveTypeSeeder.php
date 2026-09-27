<?php

namespace App\Modules\HR\Database\Seeds;

use CodeIgniter\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            'Casual Leave'  => 12,
            'Sick Leave'    => 12,
            'Earned Leave'  => 15,
            'Unpaid Leave'  => 0,
        ];

        foreach ($types as $name => $quota) {
            $exists = $this->db->table('leave_types')->where('name', $name)->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('leave_types')->insert([
                    'name'         => $name,
                    'annual_quota' => $quota,
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
