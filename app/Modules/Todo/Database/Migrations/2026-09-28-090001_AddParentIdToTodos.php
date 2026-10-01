<?php

namespace App\Modules\Todo\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParentIdToTodos extends Migration
{
    /**
     * Self-referencing parent_id — a Parent To-Do's Sub-To-Dos are just
     * ordinary rows in the same table with parent_id set to the parent's
     * id. One level deep only (TodoController enforces a sub-to-do can't
     * itself become a parent), matching the "Birthday -> Buy cake /
     * Order decorations / ..." example. Every existing row already has
     * parent_id NULL, so nothing already saved changes meaning.
     *
     * ON DELETE CASCADE: deleting a parent removes its children too,
     * so a hard delete can never leave an orphaned sub-to-do pointing at
     * a parent_id that no longer exists (TodoController's trash()/
     * restore() also cascade the soft-trash state the same way, since
     * that's a plain UPDATE, not something a DB constraint can do).
     */
    public function up()
    {
        if (! $this->db->fieldExists('parent_id', 'todos')) {
            $this->forge->addColumn('todos', [
                'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'user_id'],
            ]);
            $this->forge->addForeignKey('parent_id', 'todos', 'id', '', 'CASCADE');
            $this->forge->processIndexes('todos');
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('todos', 'todos_parent_id_foreign');
        $this->forge->dropColumn('todos', 'parent_id');
    }
}
