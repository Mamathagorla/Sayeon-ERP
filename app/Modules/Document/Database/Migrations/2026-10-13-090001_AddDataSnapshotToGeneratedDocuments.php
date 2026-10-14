<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDataSnapshotToGeneratedDocuments extends Migration
{
    public function up()
    {
        // Exact values the document was built from (audit trail; the stored file itself is what is served).
        $this->forge->addColumn('generated_documents', [
            'data_snapshot' => ['type' => 'MEDIUMTEXT', 'null' => true, 'after' => 'data_hash'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('generated_documents', 'data_snapshot');
    }
}
