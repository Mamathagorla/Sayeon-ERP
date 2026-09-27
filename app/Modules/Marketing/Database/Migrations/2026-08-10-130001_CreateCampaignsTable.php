<?php

namespace App\Modules\Marketing\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCampaignsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'channel'     => ['type' => 'ENUM', 'constraint' => ['google_ads', 'facebook', 'instagram', 'linkedin', 'email', 'whatsapp']],
            'budget'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'start_date'  => ['type' => 'DATE', 'null' => true],
            'end_date'    => ['type' => 'DATE', 'null' => true],
            'leads'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'conversions' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'revenue'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'status'      => ['type' => 'ENUM', 'constraint' => ['draft', 'active', 'paused', 'completed'], 'default' => 'draft'],
            'notes'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('campaigns');
    }

    public function down()
    {
        $this->forge->dropTable('campaigns', true);
    }
}
