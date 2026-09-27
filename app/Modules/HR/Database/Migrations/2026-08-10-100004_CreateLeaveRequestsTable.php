<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLeaveRequestsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'leave_type_id' => ['type' => 'INT', 'unsigned' => true],
            'start_date'    => ['type' => 'DATE'],
            'end_date'      => ['type' => 'DATE'],
            'days'          => ['type' => 'DECIMAL', 'constraint' => '5,1'],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected', 'cancelled'], 'default' => 'pending'],
            'approved_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'approved_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('leave_type_id', 'leave_types', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('approved_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('leave_requests');
    }

    public function down()
    {
        $this->forge->dropTable('leave_requests', true);
    }
}
