<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttendanceTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'date'       => ['type' => 'DATE'],
            'check_in'   => ['type' => 'DATETIME', 'null' => true],
            'check_out'  => ['type' => 'DATETIME', 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['present', 'absent', 'half_day', 'on_leave', 'holiday', 'week_off'], 'default' => 'present'],
            'notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'date']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('attendance');
    }

    public function down()
    {
        $this->forge->dropTable('attendance', true);
    }
}
