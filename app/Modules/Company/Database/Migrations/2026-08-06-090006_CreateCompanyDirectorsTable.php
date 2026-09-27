<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanyDirectorsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'unsigned' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'din'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'designation'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'email'           => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'appointed_date'  => ['type' => 'DATE', 'null' => true],
            'resigned_date'   => ['type' => 'DATE', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->createTable('company_directors');
    }

    public function down()
    {
        $this->forge->dropTable('company_directors', true);
    }
}
