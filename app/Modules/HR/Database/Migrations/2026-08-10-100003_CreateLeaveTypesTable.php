<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLeaveTypesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 60],
            'annual_quota'  => ['type' => 'DECIMAL', 'constraint' => '5,1', 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('leave_types');
    }

    public function down()
    {
        $this->forge->dropTable('leave_types', true);
    }
}
