<?php

namespace App\Modules\Document\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Optional link to an onboarding record — same "optional link to a
 * specific employee" pattern `employee_user_id` already established
 * (see CreateDocumentsTable), so pre-hire onboarding documents (offer
 * letter, appointment letter, ID proofs collected before the candidate
 * has a `users` row) can be stored in the existing Documents module
 * instead of a second file-upload table. Checked live data before
 * writing this: `documents` has zero rows, so there's nothing to
 * backfill — the column is nullable purely so every existing (and
 * every non-onboarding) document keeps working unchanged.
 */
class AddOnboardingRecordIdToDocuments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('documents', [
            'onboarding_record_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'employee_user_id'],
        ]);
        // addForeignKey() only queues the definition — processIndexes()
        // is what actually issues the ALTER TABLE ... ADD CONSTRAINT
        // against the already-existing `documents` table (addForeignKey
        // + createTable is only for a brand-new table).
        $this->forge->addForeignKey('onboarding_record_id', 'onboarding_records', 'id', '', 'SET NULL');
        $this->forge->processIndexes('documents');
    }

    public function down()
    {
        $this->forge->dropForeignKey('documents', 'documents_onboarding_record_id_foreign');
        $this->forge->dropColumn('documents', 'onboarding_record_id');
    }
}
