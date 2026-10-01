<?php

namespace App\Modules\Customer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Additive-only: a named contact at the customer's business, and their
 * PAN (India's business/individual tax identifier) alongside the
 * existing GSTIN — both nullable, no existing row or relationship
 * affected.
 */
class AddContactPersonAndPanToCustomers extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('contact_person', 'customers')) {
            return;
        }

        $this->forge->addColumn('customers', [
            'contact_person' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'name'],
            'pan'            => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'gstin'],
        ]);
    }

    public function down()
    {
        if (! $this->db->fieldExists('contact_person', 'customers')) {
            return;
        }

        $this->forge->dropColumn('customers', ['contact_person', 'pan']);
    }
}
