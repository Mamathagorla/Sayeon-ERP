<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes the payslip-company-logo feature entirely, per an explicit user
 * decision made alongside dropping short_code (see
 * DropShortCodeFromCompanies) — the Company form was cluttered and the
 * logo upload/display was reverted rather than just hidden. A new
 * migration rather than editing 2026-09-29-140001_AddLogoPathToCompanies.
 * php directly, since that one is already applied.
 */
class DropLogoPathFromCompanies extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('logo_path', 'companies')) {
            $this->forge->dropColumn('companies', 'logo_path');
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('logo_path', 'companies')) {
            $this->forge->addColumn('companies', [
                'logo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'registered_address'],
            ]);
        }
    }
}
