<?php

namespace App\Modules\Expense\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Real backing table for the Expense form's Category dropdown — replaces
 * the hardcoded <datalist> that used to live in Views/form.php (11
 * category names baked into the HTML) with genuine DB-driven data,
 * mirroring the existing leave_types/compliance_types "master list"
 * tables: a single global list (no company_id — same categories make
 * sense across every company), no separate management UI needed since
 * none of those precedent tables have one either.
 */
class CreateExpenseCategoriesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            // 'active'/'inactive' — a category already referenced by
            // existing expenses can be retired without breaking those
            // rows or losing the historical label (see
            // ExpenseCategoryModel::optionsListIncluding()).
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('expense_categories');
    }

    public function down()
    {
        $this->forge->dropTable('expense_categories', true);
    }
}
