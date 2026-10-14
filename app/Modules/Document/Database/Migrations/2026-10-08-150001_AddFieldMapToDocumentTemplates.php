<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldMapToDocumentTemplates extends Migration
{
    public function up()
    {
        // JSON list of regions (PDF templates only): where each sample value
        // sits on the original PDF and which ERP field replaces it.
        $this->forge->addColumn('document_templates', [
            'field_map' => ['type' => 'MEDIUMTEXT', 'null' => true, 'after' => 'notes'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('document_templates', 'field_map');
    }
}
