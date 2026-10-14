<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldReviewToDocumentTemplates extends Migration
{
    public function up()
    {
        // Findings from automatic field detection on upload: JSON list of
        // {field,label,reason,blocking}. Blocking items stop the template
        // from generating until an admin reviews or accepts them.
        $this->forge->addColumn('document_templates', [
            'field_review' => ['type' => 'TEXT', 'null' => true, 'after' => 'field_map'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('document_templates', 'field_review');
    }
}
