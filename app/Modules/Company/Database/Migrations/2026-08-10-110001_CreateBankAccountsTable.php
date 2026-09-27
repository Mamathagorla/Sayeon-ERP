<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBankAccountsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'            => ['type' => 'INT', 'unsigned' => true],
            'bank_name'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'branch'                => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'account_holder'        => ['type' => 'VARCHAR', 'constraint' => 150],
            // Full account number is encrypted at rest (see BankAccountModel);
            // last4 is kept in the clear only so lists can render a masked
            // "•••• 1234" without decrypting on every page load.
            'account_number_cipher' => ['type' => 'TEXT'],
            'account_number_last4'  => ['type' => 'VARCHAR', 'constraint' => 4],
            'ifsc'                  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'authorized_signatories' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'                => ['type' => 'ENUM', 'constraint' => ['active', 'inactive', 'closed'], 'default' => 'active'],
            // One optional supporting document per account (e.g. a cancelled
            // cheque or statement) — a single nullable file field, not a
            // separate attachments table; a deliberate scope simplification.
            'document_path'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'document_name'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->createTable('bank_accounts');
    }

    public function down()
    {
        $this->forge->dropTable('bank_accounts', true);
    }
}
