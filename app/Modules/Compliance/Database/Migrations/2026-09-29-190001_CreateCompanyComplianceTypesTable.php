<?php

namespace App\Modules\Compliance\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a company opt into a subset of the global compliance_types list —
 * "which compliance requirements actually apply to us". compliance_types
 * itself stays a single shared master list (see the module's existing
 * precedent — leave_types/expense_categories are the same shape); this is
 * purely a junction of "which of those apply to which company", additive
 * and non-destructive: a company with no rows here has simply never
 * configured a subset yet, and the app treats that as "every type still
 * applies" (see ComplianceController::applicableTypesFor()) so nothing
 * about existing compliance_items/compliance_types data or behaviour
 * changes until a company explicitly opts into a subset.
 */
class CreateCompanyComplianceTypesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('company_compliance_types')) {
            return;
        }

        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'          => ['type' => 'INT', 'unsigned' => true],
            'compliance_type_id'  => ['type' => 'INT', 'unsigned' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'compliance_type_id'], 'uniq_company_compliance_type');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('compliance_type_id', 'compliance_types', 'id', '', 'CASCADE');
        $this->forge->createTable('company_compliance_types');
    }

    public function down()
    {
        $this->forge->dropTable('company_compliance_types', true);
    }
}
