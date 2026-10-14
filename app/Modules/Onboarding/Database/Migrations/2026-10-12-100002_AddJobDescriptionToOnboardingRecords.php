<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The app has no recruitment/job-posting table, so the role's description
 * and key responsibilities live on the onboarding record itself (optional,
 * entered by HR). The candidate form shows it, and role-group matching
 * (CandidateFormService::roleGroupFor()) reads it alongside the job title.
 */
class AddJobDescriptionToOnboardingRecords extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('job_description', 'onboarding_records')) {
            return;
        }

        $this->forge->addColumn('onboarding_records', [
            'job_description' => ['type' => 'TEXT', 'null' => true, 'after' => 'designation'],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('job_description', 'onboarding_records')) {
            $this->forge->dropColumn('onboarding_records', 'job_description');
        }
    }
}
