<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePerformanceReviewsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'cycle_id'        => ['type' => 'INT', 'unsigned' => true],
            'user_id'         => ['type' => 'INT', 'unsigned' => true],
            'reviewer_id'     => ['type' => 'INT', 'unsigned' => true],
            'rating'          => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'strengths'       => ['type' => 'TEXT', 'null' => true],
            'improvements'    => ['type' => 'TEXT', 'null' => true],
            'goals_next'      => ['type' => 'TEXT', 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['draft', 'submitted', 'acknowledged'], 'default' => 'draft'],
            'submitted_at'    => ['type' => 'DATETIME', 'null' => true],
            'acknowledged_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['cycle_id', 'user_id']);
        $this->forge->addForeignKey('cycle_id', 'review_cycles', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('reviewer_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('performance_reviews');
    }

    public function down()
    {
        $this->forge->dropTable('performance_reviews', true);
    }
}
