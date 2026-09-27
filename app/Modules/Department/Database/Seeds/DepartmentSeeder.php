<?php

namespace App\Modules\Department\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        $departments = [
            'Admin', 'HR', 'Finance', 'Accounting', 'Marketing',
            'IT', 'Legal', 'Compliance', 'Operations',
        ];

        foreach ($departments as $name) {
            $slug   = strtolower($name);
            $exists = $this->db->table('departments')->where('slug', $slug)->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('departments')->insert([
                    'name'       => $name,
                    'slug'       => $slug,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
