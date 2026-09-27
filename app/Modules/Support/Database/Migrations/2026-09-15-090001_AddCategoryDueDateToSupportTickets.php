<?php

namespace App\Modules\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCategoryDueDateToSupportTickets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('support_tickets', [
            'category' => [
                'type'       => 'ENUM',
                'constraint' => ['general', 'finance_billing', 'technical', 'hr_payroll', 'account_access'],
                'default'    => 'general',
                'after'      => 'company_id',
            ],
            'due_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'priority',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('support_tickets', ['category', 'due_date']);
    }
}
