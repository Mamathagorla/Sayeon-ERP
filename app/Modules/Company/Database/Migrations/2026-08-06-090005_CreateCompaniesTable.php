<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompaniesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'                  => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug'                  => ['type' => 'VARCHAR', 'constraint' => 160],
            'status'                => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'owner_id'              => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'country'               => ['type' => 'VARCHAR', 'constraint' => 60],
            'cin'                   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'gst'                   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'pan'                   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'registered_address'    => ['type' => 'TEXT', 'null' => true],
            'incorporation_date'    => ['type' => 'DATE', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addForeignKey('owner_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('companies');
    }

    public function down()
    {
        $this->forge->dropTable('companies', true);
    }
}
