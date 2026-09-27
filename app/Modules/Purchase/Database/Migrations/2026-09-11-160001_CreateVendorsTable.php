<?php

namespace App\Modules\Purchase\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * No vendor/supplier master list exists anywhere in the app —
 * BillModel.vendor_name is free text. Company-scoped (unlike the
 * global leave_types/compliance_types lists) since suppliers are
 * specific to each company's own procurement relationships.
 */
class CreateVendorsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'unsigned' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'contact_person'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'address'         => ['type' => 'TEXT', 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->createTable('vendors');
    }

    public function down()
    {
        $this->forge->dropTable('vendors', true);
    }
}
