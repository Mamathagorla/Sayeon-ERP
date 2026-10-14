<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFileTypeToDocumentTemplates extends Migration
{
    public function up()
    {
        // Existing rows are all HTML/PDF-output, hence the defaults.
        $this->forge->addColumn('document_templates', [
            'file_type' => ['type' => 'ENUM', 'constraint' => ['html', 'docx', 'pdf'], 'default' => 'html', 'after' => 'status'],
        ]);
        $this->forge->addColumn('generated_documents', [
            'file_type' => ['type' => 'ENUM', 'constraint' => ['pdf', 'docx'], 'default' => 'pdf', 'after' => 'document_type'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('generated_documents', 'file_type');
        $this->forge->dropColumn('document_templates', 'file_type');
    }
}
