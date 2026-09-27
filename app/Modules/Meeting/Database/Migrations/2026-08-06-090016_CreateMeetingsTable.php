<?php

namespace App\Modules\Meeting\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMeetingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'   => ['type' => 'INT', 'unsigned' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 200],
            'agenda'       => ['type' => 'TEXT', 'null' => true],
            'meeting_date' => ['type' => 'DATE'],
            'start_time'   => ['type' => 'TIME', 'null' => true],
            'end_time'     => ['type' => 'TIME', 'null' => true],
            'location'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], // room, or a meeting link
            'mom'          => ['type' => 'TEXT', 'null' => true], // Minutes of Meeting
            'created_by'   => ['type' => 'INT', 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'meeting_date']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('meetings');
    }

    public function down()
    {
        $this->forge->dropTable('meetings', true);
    }
}
