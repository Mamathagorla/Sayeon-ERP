<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'       => ['type' => 'INT', 'unsigned' => true],
            'category'         => ['type' => 'ENUM', 'constraint' => ['legal', 'finance', 'hr', 'it', 'marketing', 'compliance', 'general']],
            'document_type'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'title'            => ['type' => 'VARCHAR', 'constraint' => 150],
            // Optional link to a specific employee — covers the spec's
            // "Employee Documents" category without a separate table.
            'employee_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'file_path'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'expiry_date'      => ['type' => 'DATE', 'null' => true],
            'uploaded_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('employee_user_id', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('documents');
    }

    public function down()
    {
        $this->forge->dropTable('documents', true);
    }
}
