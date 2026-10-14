<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWorkflowToDocumentTemplates extends Migration
{
    public function up()
    {
        // NULL (every existing row) = "automatic": the template is offered wherever its document TYPE is
        // offered (DocumentTypeRegistry / Config\DocumentWorkflows). A value pins this one template to a
        // single workflow, for a template that is explicitly meant for another purpose.
        $this->forge->addColumn('document_templates', [
            'workflow' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'document_type'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('document_templates', 'workflow');
    }
}
