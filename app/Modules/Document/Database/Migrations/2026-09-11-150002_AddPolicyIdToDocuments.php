<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Optional link to a Policy/Manual record — same pattern already used
 * for `employee_user_id` and `onboarding_record_id` (see those
 * migrations): DocumentController stays the single writer for the
 * `documents` table, and other modules pass a foreign id through to it
 * rather than duplicating upload/storage logic. Checked live data
 * first: `documents` currently has 0 rows, so nothing to backfill.
 */
class AddPolicyIdToDocuments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('documents', [
            'policy_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'onboarding_record_id'],
        ]);
        $this->forge->addForeignKey('policy_id', 'policies', 'id', '', 'SET NULL');
        $this->forge->processIndexes('documents');
    }

    public function down()
    {
        $this->forge->dropForeignKey('documents', 'documents_policy_id_foreign');
        $this->forge->dropColumn('documents', 'policy_id');
    }
}
