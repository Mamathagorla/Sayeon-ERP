<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShortCodeToCompanies extends Migration
{
    /**
     * A short, admin-editable prefix (e.g. 'SAY' for Sayeon) used by
     * EmployeeProfileModel::nextEmployeeCode() to build a per-company
     * employee id like 'ID-SAY07' instead of one shared 'EMP-####'
     * sequence. Nullable so it never blocks anything already working;
     * a company without one set yet still falls back to the plain
     * 'EMP-####' format (see nextEmployeeCode()).
     *
     * The 5 existing companies are backfilled with a short code derived
     * from their own real registered name (first letters), exactly the
     * same "auto-derive a default, let an admin correct it later" spirit
     * CompanySeeder already uses for `slug` — never a fabricated value,
     * always overridable via the Company edit form.
     */
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
