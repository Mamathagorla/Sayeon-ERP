<?php

namespace App\Modules\Compliance\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the two fields the "Compliance Docs" reference screen has that
 * this module didn't yet (Regulator, Period) — everything else on that
 * screen already exists here: Title, Owner (responsible_user_id),
 * Submitted (filed_at), Status. Extending this module rather than
 * building a parallel one, since the two cover the same ground.
 * Checked live data first: compliance_items has 1 row; both columns
 * are nullable so it's unaffected.
 */
class AddRegulatorAndPeriodToComplianceItems extends Migration
{
    public function up()
    {
        $this->forge->addColumn('compliance_items', [
            'regulator' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'title'],
            'period'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'regulator'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('compliance_items', ['regulator', 'period']);
    }
}
