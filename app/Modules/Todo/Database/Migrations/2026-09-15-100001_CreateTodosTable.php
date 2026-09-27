<?php

namespace App\Modules\Todo\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Personal quick reminders — deliberately NOT company-scoped (no
 * company_id), same precedent as `notifications`: every row belongs to
 * exactly one user via `user_id`, visible only to that user regardless
 * of role or company. See TodoModel::findForUser()/forUser(), which
 * every controller action filters through so a user can never reach
 * another user's row by guessing an id in the URL.
 */
class CreateTodosTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'due_date'     => ['type' => 'DATE', 'null' => true],
            'priority'     => ['type' => 'ENUM', 'constraint' => ['normal', 'important'], 'default' => 'normal'],
            'is_starred'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_completed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'is_trashed'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'trashed_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('todos');
    }

    public function down()
    {
        $this->forge->dropTable('todos', true);
    }
}
