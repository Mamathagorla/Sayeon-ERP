<?php

namespace App\Modules\Policy\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Policies & Manuals — versioned company documents (Employee Handbook,
 * Code of Conduct, IT Security Guidelines, etc.), distinct from the
 * generic Document Repository: these need a version number, last
 * review date, retention period and a draft/published/archived
 * lifecycle, none of which the existing `documents` table models.
 * The actual uploaded file is NOT duplicated here — `document_id`
 * links to the existing Documents module (reused exactly as-is for
 * storage/download), same pattern Onboarding/Offboarding already use.
 */
class CreatePoliciesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'        => ['type' => 'INT', 'unsigned' => true],
            'title'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'type'              => ['type' => 'ENUM', 'constraint' => ['policy', 'manual'], 'default' => 'policy'],
            'owner_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'version'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'last_review_date'  => ['type' => 'DATE', 'null' => true],
            'retention_years'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'            => ['type' => 'ENUM', 'constraint' => ['draft', 'published', 'archived'], 'default' => 'draft'],
            'document_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('owner_id', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('document_id', 'documents', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('policies');
    }

    public function down()
    {
        $this->forge->dropTable('policies', true);
    }
}
