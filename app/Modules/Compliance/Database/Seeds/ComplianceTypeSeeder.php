<?php

namespace App\Modules\Compliance\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ComplianceTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            'GST Filing', 'TDS', 'Income Tax', 'PF', 'ESI', 'ROC Filing',
            'MCA Annual Return', 'Payroll', 'Insurance Renewal', 'Professional Tax',
        ];

        foreach ($types as $name) {
            $slug   = url_title($name, '-', true);
            $exists = $this->db->table('compliance_types')->where('slug', $slug)->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('compliance_types')->insert(['name' => $name, 'slug' => $slug]);
            }
        }
    }
}
