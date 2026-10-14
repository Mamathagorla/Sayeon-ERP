<?php

namespace App\Modules\Onboarding\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Structured candidate information for the token-based onboarding portal,
 * plus the HR evaluation of it. Everything hangs off the existing
 * onboarding_records row (nothing is duplicated there; the legacy
 * address/qualification columns stay the source for the single-value
 * fields HR and the employee-conversion already read).
 *
 *  - onboarding_role_groups / _questions / _eval_criteria: the
 *    configurable "which questions does this role get" layer. A group is
 *    matched against the candidate's designation, department and job
 *    description by keyword (see CandidateFormService::roleGroupFor()).
 *    company_id NULL = shared default; a company can clone a group and
 *    customise its own copy. The seeded 'general' group is the fallback.
 *  - onboarding_candidate_profiles: one row per record - candidate type,
 *    links, permanent address and submission bookkeeping.
 *  - onboarding_candidate_entries: the repeatable records (education,
 *    certification, project, internship, experience) as JSON, validated
 *    against a field schema in PHP, ordered per section.
 *  - onboarding_candidate_answers: answers to the role-specific questions,
 *    with the question label snapshotted so the HR page stays correct if
 *    the configuration is edited later.
 *  - onboarding_evaluations: HR's structured assessment, one per record.
 */
class CreateCandidateProfileTables extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'description' => ['type' => 'TEXT', 'null' => true],
            'keywords'    => ['type' => 'TEXT', 'null' => true],
            'features'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'priority'    => ['type' => 'INT', 'unsigned' => true, 'default' => 100],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'slug']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->createTable('onboarding_role_groups');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_group_id' => ['type' => 'INT', 'unsigned' => true],
            'question_key'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'label'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'field_type'    => ['type' => 'ENUM', 'constraint' => ['text', 'textarea', 'select', 'tags', 'url', 'number', 'yes_no'], 'default' => 'text'],
            'options'       => ['type' => 'TEXT', 'null' => true],
            'help_text'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_required'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'applies_to'    => ['type' => 'ENUM', 'constraint' => ['all', 'fresher', 'experienced'], 'default' => 'all'],
            'sort_order'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('role_group_id');
        $this->forge->addForeignKey('role_group_id', 'onboarding_role_groups', 'id', '', 'CASCADE');
        $this->forge->createTable('onboarding_questions');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_group_id' => ['type' => 'INT', 'unsigned' => true],
            'label'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sort_order'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('role_group_id');
        $this->forge->addForeignKey('role_group_id', 'onboarding_role_groups', 'id', '', 'CASCADE');
        $this->forge->createTable('onboarding_eval_criteria');

        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onboarding_record_id' => ['type' => 'INT', 'unsigned' => true],
            'candidate_type'       => ['type' => 'ENUM', 'constraint' => ['fresher', 'experienced'], 'null' => true],
            'role_group_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'role_group_name'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'permanent_address'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'permanent_same_as_current' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'github_url'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'portfolio_url'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'linkedin_url'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notice_period'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'profile_summary'      => ['type' => 'TEXT', 'null' => true],
            'candidate_note'       => ['type' => 'TEXT', 'null' => true],
            'last_saved_at'        => ['type' => 'DATETIME', 'null' => true],
            'submitted_at'         => ['type' => 'DATETIME', 'null' => true],
            'revision'             => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('onboarding_record_id');
        $this->forge->addForeignKey('onboarding_record_id', 'onboarding_records', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('role_group_id', 'onboarding_role_groups', 'id', '', 'SET NULL');
        $this->forge->createTable('onboarding_candidate_profiles');

        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onboarding_record_id' => ['type' => 'INT', 'unsigned' => true],
            'section'              => ['type' => 'ENUM', 'constraint' => ['education', 'certification', 'project', 'internship', 'experience']],
            'sort_order'           => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'data'                 => ['type' => 'LONGTEXT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['onboarding_record_id', 'section']);
        $this->forge->addForeignKey('onboarding_record_id', 'onboarding_records', 'id', '', 'CASCADE');
        $this->forge->createTable('onboarding_candidate_entries');

        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onboarding_record_id' => ['type' => 'INT', 'unsigned' => true],
            'question_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'question_key'         => ['type' => 'VARCHAR', 'constraint' => 60],
            'question_label'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'field_type'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'text'],
            'answer'               => ['type' => 'TEXT', 'null' => true],
            'sort_order'           => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['onboarding_record_id', 'question_key']);
        $this->forge->addForeignKey('onboarding_record_id', 'onboarding_records', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('question_id', 'onboarding_questions', 'id', '', 'SET NULL');
        $this->forge->createTable('onboarding_candidate_answers');

        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onboarding_record_id'  => ['type' => 'INT', 'unsigned' => true],
            'evaluator_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'role_group_name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'technical_assessment'  => ['type' => 'TEXT', 'null' => true],
            'experience_assessment' => ['type' => 'TEXT', 'null' => true],
            'strengths'             => ['type' => 'TEXT', 'null' => true],
            'concerns'              => ['type' => 'TEXT', 'null' => true],
            'hr_comments'           => ['type' => 'TEXT', 'null' => true],
            'decision_notes'        => ['type' => 'TEXT', 'null' => true],
            // JSON list of {label, score (1-5 or null), note}; labels are
            // snapshotted so later criteria edits never rewrite history.
            'criteria_scores'       => ['type' => 'LONGTEXT', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('onboarding_record_id');
        $this->forge->addForeignKey('onboarding_record_id', 'onboarding_records', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('evaluator_id', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('onboarding_evaluations');

        $this->seedDefaults($now);
    }

    public function down()
    {
        foreach (['onboarding_evaluations', 'onboarding_candidate_answers', 'onboarding_candidate_entries', 'onboarding_candidate_profiles', 'onboarding_eval_criteria', 'onboarding_questions', 'onboarding_role_groups'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    /**
     * Shared default role groups (company_id NULL). Keywords are matched
     * against designation (strongest), department and job description;
     * the lowest priority number wins a tie, so the more specific
     * Frontend group beats the broader Software one for "Frontend Developer".
     */
    private function seedDefaults(string $now): void
    {
        $groups = [
            [
                'slug' => 'frontend', 'name' => 'Frontend / UI Development', 'priority' => 10, 'features' => 'projects,links',
                'keywords' => 'frontend, front-end, front end, ui developer, ui engineer, web developer, react, angular, vue, ux',
                'description' => 'Builds and maintains the user-facing parts of web applications: responsive interfaces, reusable components and integration with APIs.',
                'questions' => [
                    ['Proficiency in HTML, CSS and JavaScript', 'select', "Beginner\nIntermediate\nAdvanced\nExpert", 'Be honest - we assess this in the technical round.', 1, 'all'],
                    ['Frameworks, libraries and build tools', 'tags', null, 'e.g. React, Vue, Angular, Tailwind, Bootstrap, Webpack/Vite', 1, 'all'],
                    ['Responsive design and cross-browser experience', 'textarea', null, 'Describe how you build layouts that work across devices (flex/grid, media queries, mobile-first).', 0, 'all'],
                    ['Accessibility, testing and performance practices', 'textarea', null, 'e.g. ARIA, Lighthouse, Jest/Cypress, lazy loading.', 0, 'all'],
                    ['Most challenging UI problem you solved', 'textarea', null, 'What was the problem, and how did you approach it?', 0, 'experienced'],
                ],
                'criteria' => [['UI / front-end technical depth', 'HTML, CSS, JavaScript and framework fundamentals'], ['Responsive & accessible design', null], ['Portfolio / project quality', 'Quality and relevance of the UI work shown'], ['Problem solving', null], ['Communication', null]],
            ],
            [
                'slug' => 'data', 'name' => 'Data Analytics / Data Science', 'priority' => 15, 'features' => 'projects,links',
                'keywords' => 'data analyst, data scientist, data engineer, analytics, business analyst, business intelligence, bi developer, statistician, machine learning engineer, mlops',
                'description' => 'Collects, cleans and analyses data to produce insight and models that support business decisions.',
                'questions' => [
                    ['Languages and query tools', 'tags', null, 'e.g. SQL, Python, R, Excel', 1, 'all'],
                    ['Statistics and machine-learning techniques', 'textarea', null, 'Methods you have applied (regression, classification, A/B testing, forecasting, ...).', 0, 'all'],
                    ['Visualization and BI tools', 'tags', null, 'e.g. Power BI, Tableau, Looker, matplotlib', 0, 'all'],
                    ['Data volumes and platforms worked with', 'text', null, 'e.g. MySQL, BigQuery, Snowflake, Spark', 0, 'all'],
                    ['An analysis that changed a decision', 'textarea', null, 'Describe the question, the data, your method and the outcome.', 0, 'experienced'],
                ],
                'criteria' => [['Analytical & statistical ability', null], ['SQL / programming proficiency', null], ['Visualization & communication of insight', null], ['Project / case-study quality', null]],
            ],
            [
                'slug' => 'software', 'name' => 'Software / AI-ML Engineering', 'priority' => 20, 'features' => 'projects,links',
                'keywords' => 'software, developer, programmer, engineer, backend, back-end, full stack, fullstack, sde, java, python, php, .net, devops, ai, ml, machine learning, artificial intelligence, deep learning, qa, tester',
                'description' => 'Designs, builds, tests and maintains software systems and, where relevant, machine-learning features.',
                'questions' => [
                    ['Programming languages', 'tags', null, 'e.g. Python, Java, JavaScript, PHP, C#', 1, 'all'],
                    ['Frameworks and technologies', 'tags', null, 'e.g. CodeIgniter, Django, Spring, React, Docker', 0, 'all'],
                    ['Databases, version control and tools', 'tags', null, 'e.g. MySQL, PostgreSQL, Git, Jira, AWS', 0, 'all'],
                    ['Technical skills relevant to this position', 'textarea', null, 'APIs, architecture, testing, algorithms, cloud, security - whatever applies to the role.', 1, 'all'],
                    ['AI / machine-learning experience (if any)', 'textarea', null, 'Models, libraries (scikit-learn, TensorFlow, PyTorch), datasets and results.', 0, 'all'],
                    ['Most challenging technical problem you solved', 'textarea', null, 'What was the problem, your approach and the outcome?', 0, 'experienced'],
                ],
                'criteria' => [['Core technical skills', 'Languages, frameworks and fundamentals relevant to the role'], ['Problem solving', null], ['Project / practical experience', 'Depth and relevance of projects and internships'], ['Code & design practices', null], ['Communication', null]],
            ],
            [
                'slug' => 'hr', 'name' => 'Human Resources / Recruitment', 'priority' => 30, 'features' => '',
                'keywords' => 'hr, human resource, human resources, recruiter, recruitment, talent acquisition, people operations, payroll executive, hrbp',
                'description' => 'Supports hiring, employee relations, HR operations and compliance.',
                'questions' => [
                    ['HR areas you have worked in', 'tags', null, 'e.g. recruitment, onboarding, payroll, employee relations, compliance', 1, 'all'],
                    ['HRIS / tools used', 'tags', null, 'e.g. Zoho People, Keka, Naukri, LinkedIn Recruiter, Excel', 0, 'all'],
                    ['Relevant certifications or statutory knowledge', 'textarea', null, 'e.g. labour law, PF/ESI, POSH, SHRM/HRCI.', 0, 'all'],
                    ['Examples of previous work', 'textarea', null, 'Describe a hiring drive, policy or process you handled and the result.', 1, 'experienced'],
                ],
                'criteria' => [['HR domain knowledge', null], ['Process & compliance awareness', null], ['Interpersonal & communication skills', null], ['Relevant experience / examples', null]],
            ],
            [
                'slug' => 'finance', 'name' => 'Finance / Accounts', 'priority' => 30, 'features' => '',
                'keywords' => 'finance, accountant, accounts, accounting, auditor, audit, tax, taxation, gst, bookkeeper, treasury, financial analyst, cfo, controller',
                'description' => 'Maintains books of account, reporting, taxation and financial controls.',
                'questions' => [
                    ['Accounting software and tools', 'tags', null, 'e.g. Tally, SAP, QuickBooks, Zoho Books, Excel', 1, 'all'],
                    ['Finance areas handled', 'tags', null, 'e.g. GST, TDS, accounts payable/receivable, reconciliation, MIS, audit', 0, 'all'],
                    ['Relevant certifications / qualifications', 'textarea', null, 'e.g. CA, CMA, CFA, B.Com - add details in the certification section too.', 0, 'all'],
                    ['Examples of previous work', 'textarea', null, 'Describe a close, audit, filing or reporting task you owned.', 1, 'experienced'],
                ],
                'criteria' => [['Accounting & tax knowledge', null], ['Tool proficiency', null], ['Accuracy & attention to detail', null], ['Relevant experience / examples', null]],
            ],
            [
                'slug' => 'operations', 'name' => 'Operations / Administration', 'priority' => 30, 'features' => '',
                'keywords' => 'operations, operation, ops, logistics, supply chain, procurement, administration, admin, coordinator, support, customer service, office manager',
                'description' => 'Keeps day-to-day business processes, vendors and facilities running smoothly.',
                'questions' => [
                    ['Operational areas handled', 'tags', null, 'e.g. vendor management, inventory, logistics, scheduling, facilities', 1, 'all'],
                    ['Tools and systems used', 'tags', null, 'e.g. ERP, Excel, ticketing tools, inventory software', 0, 'all'],
                    ['A process you improved', 'textarea', null, 'What was the problem, what did you change and what was the result?', 0, 'experienced'],
                    ['Team or vendor coordination experience', 'textarea', null, null, 0, 'all'],
                ],
                'criteria' => [['Process & operational knowledge', null], ['Tool proficiency', null], ['Organisation & follow-through', null], ['Relevant experience / examples', null]],
            ],
            [
                'slug' => 'general', 'name' => 'General (all other roles)', 'priority' => 900, 'features' => '',
                'keywords' => null,
                'description' => 'Fallback question set used when no other role group matches the position.',
                'questions' => [
                    ['Key skills for this role', 'tags', null, 'List the skills you would bring to this position.', 1, 'all'],
                    ['Tools and software you are proficient in', 'tags', null, null, 0, 'all'],
                    ['Relevant responsibilities or examples of previous work', 'textarea', null, 'Describe work you have done that is relevant to this position.', 0, 'all'],
                ],
                'criteria' => [['Role-relevant skills', null], ['Relevant experience & qualifications', null], ['Communication', null], ['Overall suitability', null]],
            ],
        ];

        foreach ($groups as $group) {
            $this->db->table('onboarding_role_groups')->insert([
                'company_id' => null, 'name' => $group['name'], 'slug' => $group['slug'], 'description' => $group['description'],
                'keywords' => $group['keywords'], 'features' => $group['features'], 'priority' => $group['priority'],
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $groupId = (int) $this->db->insertID();

            foreach ($group['questions'] as $i => [$label, $type, $options, $help, $required, $appliesTo]) {
                $this->db->table('onboarding_questions')->insert([
                    'role_group_id' => $groupId, 'question_key' => 'q_' . $group['slug'] . '_' . ($i + 1), 'label' => $label,
                    'field_type' => $type, 'options' => $options, 'help_text' => $help, 'is_required' => $required,
                    'applies_to' => $appliesTo, 'sort_order' => ($i + 1) * 10, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($group['criteria'] as $i => [$label, $description]) {
                $this->db->table('onboarding_eval_criteria')->insert([
                    'role_group_id' => $groupId, 'label' => $label, 'description' => $description,
                    'sort_order' => ($i + 1) * 10, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
}
