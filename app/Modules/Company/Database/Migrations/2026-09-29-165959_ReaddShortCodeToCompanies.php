<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Re-introduces the per-company short-code prefix that
 * 2026-09-29-150001_DropShortCodeFromCompanies removed — at the time,
 * removing it was an explicit user decision to declutter the Company
 * form; this migration is a later, separate explicit request for a
 * proper short, sequential, company-scoped ID system across Employees/
 * Projects/Tasks/Invoices, which needs exactly this column back. A new
 * migration rather than reverting/editing the drop migration, per this
 * project's "never rewrite an already-applied migration" convention.
 * Same 5 real-name-derived defaults as the original addition.
 */
class ReaddShortCodeToCompanies extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('short_code', 'companies')) {
            $this->forge->addColumn('companies', [
                'short_code' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => true, 'after' => 'slug'],
            ]);
            $this->forge->addUniqueKey('short_code');
            $this->forge->processIndexes('companies');
        }

        $defaults = [
            'sanvima-solutions'          => 'SAN',
            'sayeon'                     => 'SAY',
            'festive-retail-pvt-ltd'     => 'FES',
            'nihira-power-infra-pvt-ltd' => 'NIH',
            'intrepid-professionals'     => 'INT',
        ];

        foreach ($defaults as $slug => $code) {
            $this->db->table('companies')
                ->where('slug', $slug)
                ->where('short_code IS NULL')
                ->update(['short_code' => $code]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('companies', 'short_code');
    }
}
