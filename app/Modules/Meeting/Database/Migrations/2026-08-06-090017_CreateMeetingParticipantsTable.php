<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMeetingParticipantsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'meeting_id'     => ['type' => 'INT', 'unsigned' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'external_name'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'external_email' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('meeting_id', 'meetings', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('meeting_participants');
    }

    public function down()
    {
        $this->forge->dropTable('meeting_participants', true);
    }
}
