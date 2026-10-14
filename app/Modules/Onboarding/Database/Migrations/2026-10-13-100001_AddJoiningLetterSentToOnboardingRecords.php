<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Send Joining Letter" step: after the candidate accepts the offer, HR sends
 * the joining letter through the same candidate link. The timestamp is what
 * makes the letter visible in the candidate portal (and is the first send;
 * resends do not move it). Additive and nullable only.
 */
class AddJoiningLetterSentToOnboardingRecords extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('joining_letter_sent_at', 'onboarding_records')) {
            return;
        }

        $this->forge->addColumn('onboarding_records', [
            'joining_letter_sent_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'offer_accepted_at'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('joining_letter_sent_at', 'onboarding_records')) {
            $this->forge->dropColumn('onboarding_records', 'joining_letter_sent_at');
        }
    }
}
