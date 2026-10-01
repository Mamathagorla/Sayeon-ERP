<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes the per-company short_code prefix feature entirely, per an
 * explicit user decision (Company form was cluttered; the alternative of
 * just hiding the field while keeping the column was declined in favour
 * of a full removal). Employee/Task/Project/Invoice numbering all already
 * have a "no short_code" fallback built into their own nextXCode()/
 * nextInvoiceNumber() methods (see company_code_helper.php's
 * company_short_code(), now hardcoded to return null, and
 * EmployeeProfileModel::nextEmployeeCode(), rewritten to drop its
 * per-company branch) — dropping the column simply makes every company
 * take that fallback: plain EMP-#### / INV-{year}-#### global numbering,
 * and no auto-suggested Task/Project code (those fields stay, just
 * without a prefix suggestion).
 *
 * A new migration rather than editing 2026-09-29-090001_
 * AddShortCodeToCompanies.php directly — that migration is already
 * applied, and this project's convention is to never rewrite an
 * already-applied migration.
 */
class DropShortCodeFromCompanies extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('short_code', 'companies')) {
            $this->forge->dropKey('companies', 'short_code', true);
            $this->forge->dropColumn('companies', 'short_code');
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('short_code', 'companies')) {
            $this->forge->addColumn('companies', [
                'short_code' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => true, 'after' => 'slug'],
            ]);
            $this->forge->addUniqueKey('short_code');
            $this->forge->processIndexes('companies');
        }
    }
}
