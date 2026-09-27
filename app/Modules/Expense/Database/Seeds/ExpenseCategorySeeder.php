<?php

namespace App\Modules\Expense\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Starter list — carried over from the hardcoded <datalist> that used
 * to live in Expense/Views/form.php, now real rows instead of baked-in
 * HTML. Covers every category value already in use by existing expense
 * records (checked live data before writing this: "AWS", "Office Rent",
 * "Internet" — all three are already in this list).
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run()
    {
        $names = [
            'Internet', 'Office 365', 'Google Workspace', 'Shopify', 'AWS', 'Hosting',
            'Domain Names', 'VOIP', 'Office Rent', 'Electricity', 'Salaries',
        ];

        foreach ($names as $name) {
            $exists = $this->db->table('expense_categories')->where('name', $name)->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('expense_categories')->insert([
                    'name'       => $name,
                    'status'     => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
