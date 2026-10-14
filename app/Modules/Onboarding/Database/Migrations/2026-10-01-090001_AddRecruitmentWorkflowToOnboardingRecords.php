<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRecruitmentWorkflowToOnboardingRecords extends Migration
{
    private array $statuses = [
        'candidate', 'selected_for_onboarding', 'link_sent', 'details_submitted',
        'hr_review', 'approved', 'rejected', 'selected', 'offer_sent', 'offer_accepted',
        'documents_pending', 'bgv', 'hr_verification', 'ready_to_join', 'joining',
        'joined', 'induction', 'probation', 'confirmed', 'withdrawn',
    ];

    public function up()
    {
        if (! $this->db->fieldExists('candidate_source', 'onboarding_records')) {
            $this->forge->addColumn('onboarding_records', [
                'candidate_source' => ['type' => 'ENUM', 'constraint' => ['job_application', 'referral', 'agency', 'direct_hire'], 'default' => 'direct_hire', 'after' => 'candidate_phone'],
                'source_detail' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'candidate_source'],
                'date_of_birth' => ['type' => 'DATE', 'null' => true, 'after' => 'source_detail'],
                'gender' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'date_of_birth'],
                'highest_qualification' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'gender'],
                'institution' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'highest_qualification'],
                'graduation_year' => ['type' => 'YEAR', 'null' => true, 'after' => 'institution'],
                'address_line1' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'graduation_year'],
                'address_line2' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true, 'after' => 'address_line1'],
                'city' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'address_line2'],
                'state' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'city'],
                'postal_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'state'],
                'country' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'postal_code'],
            ]);
        }

        if ($this->db->fieldExists('status', 'onboarding_records')) {
            $this->forge->modifyColumn('onboarding_records', [
                'status' => ['type' => 'ENUM', 'constraint' => $this->statuses, 'default' => 'candidate'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('status', 'onboarding_records')) {
            $this->db->table('onboarding_records')
                ->whereIn('status', ['candidate', 'selected_for_onboarding', 'link_sent', 'details_submitted', 'hr_review', 'approved'])
                ->update(['status' => 'selected']);
            $this->db->table('onboarding_records')->where('status', 'rejected')->update(['status' => 'withdrawn']);
            $this->db->table('onboarding_records')->where('status', 'joining')->update(['status' => 'ready_to_join']);

            $this->forge->modifyColumn('onboarding_records', [
                'status' => ['type' => 'ENUM', 'constraint' => [
                    'selected', 'offer_sent', 'offer_accepted', 'documents_pending', 'bgv',
                    'hr_verification', 'ready_to_join', 'joined', 'induction', 'probation',
                    'confirmed', 'withdrawn',
                ], 'default' => 'selected'],
            ]);
        }

        if ($this->db->fieldExists('candidate_source', 'onboarding_records')) {
            $this->forge->dropColumn('onboarding_records', [
                'candidate_source', 'source_detail', 'date_of_birth', 'gender',
                'highest_qualification', 'institution', 'graduation_year',
                'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
            ]);
        }
    }
}