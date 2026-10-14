<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDataHashToGeneratedDocuments extends Migration
{
    public function up()
    {
        // Fingerprint of the ERP values a document was built from: while the
        // data is unchanged, the stored file is served as-is, so activating a
        // new template version never rewrites a document already issued.
        $this->forge->addColumn('generated_documents', [
            'data_hash' => ['type' => 'CHAR', 'constraint' => 40, 'null' => true, 'after' => 'record_id'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('generated_documents', 'data_hash');
    }
}
