<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCloneDraftSupportToDocumentTemplates extends Migration
{
    public function up()
    {
        // 'draft' = cloned / replaced / edited and not yet previewed + activated.
        $this->forge->modifyColumn('document_templates', [
            'status' => ['type' => 'ENUM', 'constraint' => ['active', 'inactive', 'draft'], 'default' => 'inactive'],
        ]);

        $this->forge->addColumn('document_templates', [
            // Traceability: the template this one was cloned from (never edited through the clone).
            'source_template_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'field_review'],
            // Set when the CURRENT file was successfully previewed; cleared by every edit/replace.
            'previewed_at'       => ['type' => 'DATETIME', 'null' => true, 'after' => 'source_template_id'],
        ]);

        $this->db->query('ALTER TABLE document_templates ADD CONSTRAINT fk_doctpl_source FOREIGN KEY (source_template_id) REFERENCES document_templates(id) ON DELETE SET NULL');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE document_templates DROP FOREIGN KEY fk_doctpl_source');
        $this->forge->dropColumn('document_templates', ['source_template_id', 'previewed_at']);
        $this->db->query("UPDATE document_templates SET status = 'inactive' WHERE status = 'draft'");
        $this->forge->modifyColumn('document_templates', [
            'status' => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'inactive'],
        ]);
    }
}
