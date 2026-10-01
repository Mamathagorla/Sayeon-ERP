<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds a secure, tokenized candidate-facing portal on top of the existing
 * onboarding pipeline — additive/nullable only, no existing column
 * touched. A candidate has no ERP login (see OnboardingRecordModel's own
 * docblock: "most of the pipeline... happens before the person has any
 * system login"), so access is entirely by possession of candidate_token
 * (random, unique, never guessable) plus token_expires_at, checked with
 * hash_equals() in the new public controller — the same pattern this app
 * already uses for meeting join links (meetings.join_token).
 *
 * verification_status / verification_* columns track the candidate's own
 * "please confirm your details" step (separate from HR's existing
 * hr_verified_by/hr_verified_at, which is a byproduct of HR advancing the
 * pipeline stage, not of anything the candidate submitted).
 *
 * offer_response* columns track the candidate's own accept/reject of the
 * offer letter — distinct from the existing offer_accepted_at, which
 * today is set manually by HR, not by the candidate.
 */
class AddCandidatePortalToOnboardingRecords extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('candidate_token', 'onboarding_records')) {
            return;
        }

        $this->forge->addColumn('onboarding_records', [
            'candidate_token'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'employee_profile_id'],
            'token_expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'candidate_token'],

            'verification_status'       => ['type' => 'ENUM', 'constraint' => ['pending', 'submitted', 'verified', 'correction_needed'], 'null' => true, 'after' => 'token_expires_at'],
            'verification_submitted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'verification_status'],
            'verification_reviewed_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'verification_submitted_at'],
            'verification_reviewed_at'  => ['type' => 'DATETIME', 'null' => true, 'after' => 'verification_reviewed_by'],
            'verification_notes'        => ['type' => 'TEXT', 'null' => true, 'after' => 'verification_reviewed_at'],

            'offer_response'    => ['type' => 'ENUM', 'constraint' => ['accepted', 'rejected'], 'null' => true, 'after' => 'verification_notes'],
            'offer_response_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'offer_response'],
            'offer_response_note' => ['type' => 'TEXT', 'null' => true, 'after' => 'offer_response_at'],
        ]);

        $this->forge->addUniqueKey('candidate_token');
        $this->forge->addForeignKey('verification_reviewed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->processIndexes('onboarding_records');
    }

    public function down()
    {
        if (! $this->db->fieldExists('candidate_token', 'onboarding_records')) {
            return;
        }

        $this->forge->dropForeignKey('onboarding_records', 'onboarding_records_verification_reviewed_by_foreign');
        $this->forge->dropColumn('onboarding_records', [
            'candidate_token', 'token_expires_at',
            'verification_status', 'verification_submitted_at', 'verification_reviewed_by', 'verification_reviewed_at', 'verification_notes',
            'offer_response', 'offer_response_at', 'offer_response_note',
        ]);
    }
}
