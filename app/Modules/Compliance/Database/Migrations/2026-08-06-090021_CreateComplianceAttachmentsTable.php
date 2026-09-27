<?php

namespace App\Modules\Compliance\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateComplianceAttachmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'compliance_item_id' => ['type' => 'INT', 'unsigned' => true],
            'file_path'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'uploaded_by'        => ['type' => 'INT', 'unsigned' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('compliance_item_id', 'compliance_items', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('compliance_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('compliance_attachments', true);
    }
}
