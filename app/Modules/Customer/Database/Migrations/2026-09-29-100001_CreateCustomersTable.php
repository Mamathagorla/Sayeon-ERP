<?php

namespace App\Modules\Customer\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A standalone Customer master, mirroring the Purchase module's Vendor
 * table (same company-scoped contact-record shape: name/contact/email/
 * phone/address/status) but with structured country_id/state_id/city
 * like Company and Invoice already use, since customers are GST-relevant
 * the same way an invoice's customer fields are.
 *
 * Deliberately NOT linked to `invoices` yet — invoices keep their own
 * independent customer_* snapshot columns (a point-in-time record that
 * must not silently change if a Customer's address is edited later,
 * which matters for GST/accounting documents already issued). This
 * table exists purely as its own module for now.
 */
class CreateCustomersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'gstin'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'address'     => ['type' => 'TEXT', 'null' => true],
            'country_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'state_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'city'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('country_id', 'countries', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('state_id', 'states', 'id', '', 'SET NULL');
        $this->forge->createTable('customers');
    }

    public function down()
    {
        $this->forge->dropTable('customers', true);
    }
}
