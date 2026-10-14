<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentTemplatesTables extends Migration
{
    public function up()
    {
        // One row per uploaded template file. (company_id, document_type,
        // version) is unique; at most one row per (company_id,
        // document_type) is 'active' — enforced in DocumentTemplateModel::
        // activate(), which is the only code path that flips status.
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'    => ['type' => 'INT', 'unsigned' => true],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'template_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'version'       => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'status'        => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'inactive'],
            'file_path'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'notes'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'document_type', 'version']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('document_templates');

        // Immutable record of every generated PDF. The stored PDF is the
        // source of truth, so later template changes never alter it;
        // template_version is a snapshot for audit.
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'unsigned' => true],
            'template_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'template_version' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'document_type'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'record_id'        => ['type' => 'INT', 'unsigned' => true],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 200],
            'file_path'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'generated_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'emailed_to'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'emailed_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'document_type', 'record_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('template_id', 'document_templates', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('generated_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('generated_documents');
    }

    public function down()
    {
        $this->forge->dropTable('generated_documents', true);
        $this->forge->dropTable('document_templates', true);
    }
}
