<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanyEmployeeSequencesTable extends Migration
{
    /**
     * One row per company, holding the last employee number issued to
     * it — read-and-incremented inside a transaction with SELECT ...
     * FOR UPDATE by EmployeeProfileModel::nextEmployeeCode(), so two
     * employees added to the same company at the same moment can never
     * be handed the same number (the old global "take the last-inserted
     * row's code, +1" approach couldn't guarantee that even
     * single-threaded, since it depended on row insertion order).
     *
     * Seeded from each company's CURRENT employee count so newly
     * generated codes never collide with the 30 existing EMP-####
     * codes already issued under the old shared sequence — those are
     * left untouched (see the docblock on nextEmployeeCode()).
     */
    public function up()
    {
        $this->forge->addField([
            'company_id'  => ['type' => 'INT', 'unsigned' => true],
            'last_number' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('company_id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->createTable('company_employee_sequences');

        $this->db->query(
            'INSERT INTO company_employee_sequences (company_id, last_number, updated_at)
             SELECT company_id, COUNT(*), NOW() FROM employee_profiles GROUP BY company_id'
        );
    }

    public function down()
    {
        $this->forge->dropTable('company_employee_sequences', true);
    }
}
