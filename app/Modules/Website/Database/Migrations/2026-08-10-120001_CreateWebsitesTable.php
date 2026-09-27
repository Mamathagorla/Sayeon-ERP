<?php

namespace App\Modules\Website\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWebsitesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'                => ['type' => 'INT', 'unsigned' => true],
            'domain'                    => ['type' => 'VARCHAR', 'constraint' => 190],
            'registrar'                 => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'hosting_provider'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'server_ip'                 => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'dns_details'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ssl_expiry'                => ['type' => 'DATE', 'null' => true],
            'renewal_date'              => ['type' => 'DATE', 'null' => true],
            'control_panel'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'control_panel_url'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'git_repository'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ftp_host'                  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'ftp_username'              => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            // Credential fields are encrypted at rest — same pattern and
            // the same .env encryption key as BankAccountModel.
            'admin_login_username'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'admin_login_password_cipher' => ['type' => 'TEXT', 'null' => true],
            'ftp_password_cipher'       => ['type' => 'TEXT', 'null' => true],
            'status'                    => ['type' => 'ENUM', 'constraint' => ['active', 'inactive', 'expired'], 'default' => 'active'],
            'notes'                     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'                => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('websites');
    }

    public function down()
    {
        $this->forge->dropTable('websites', true);
    }
}
