<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Payslips need the company's logo alongside its already-available
 * registered_address (see PayslipModel::withRelations()) — nullable and
 * additive, an existing company simply prints without a logo until one
 * is uploaded via the Company form.
 */
class AddLogoPathToCompanies extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('logo_path', 'companies')) {
            $this->forge->addColumn('companies', [
                'logo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'registered_address'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('companies', 'logo_path');
    }
}
