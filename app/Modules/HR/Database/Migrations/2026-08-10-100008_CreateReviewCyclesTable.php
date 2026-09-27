<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReviewCyclesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
            'status'     => ['type' => 'ENUM', 'constraint' => ['open', 'closed'], 'default' => 'open'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('review_cycles');
    }

    public function down()
    {
        $this->forge->dropTable('review_cycles', true);
    }
}
