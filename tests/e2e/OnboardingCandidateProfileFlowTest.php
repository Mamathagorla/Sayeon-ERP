<?php

namespace CodeIgniter\HTTP\Files {
    // UploadedFile::isValid() insists on is_uploaded_file(), which is only true for a real HTTP upload.
    // The feature-test client cannot make one, so (exactly like CodeIgniter's own test suite) resolve the
    // call inside this namespace to a plain "file exists" check. Test process only.
    if (! function_exists(__NAMESPACE__ . '\is_uploaded_file')) {
        function is_uploaded_file(string $filename): bool
        {
            return is_file($filename);
        }
    }
}

namespace Tests\E2E {

use App\Modules\Onboarding\Services\CandidateFormService;
use Tests\Support\E2ETestCase;

/**
 * WF-08b  Candidate information form + HR evaluation:
 * role-specific questions, draft/resume, server-side validation, multi-row persistence,
 * secure uploads, HR details page + structured evaluation, request-changes loop,
 * role-group administration and access control.
 */
final class OnboardingCandidateProfileFlowTest extends E2ETestCase
{
    private array $w;

    /** Upload folders created by a test; removed again in tearDown(). */
    private array $uploadDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    protected function tearDown(): void
    {
        service('superglobals')->setFilesArray([]);
        foreach ($this->uploadDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    /** A candidate whose portal link has been sent; returns [id, token]. */
    private function linkedCandidate(string $designation = 'Software Engineer', string $email = 'asha.verma@candidates.test', ?int $companyKey = null, string $company = 'A'): array
    {
        $X  = $this->w[$company];
        $hr = $X['hr'];
        $this->actAs($hr)->submit('/hr/onboarding', [
            'company_id' => $X['company']['id'], 'candidate_name' => 'Asha Verma', 'candidate_email' => $email, 'candidate_phone' => '9876543210',
            'candidate_source' => 'referral', 'department_id' => $this->w['dept'], 'designation' => $designation,
            'job_description' => 'Build and maintain our ERP.', 'reporting_manager_id' => $X['manager']['user_id'],
        ]);
        $id = (int) ($this->row('onboarding_records', ['candidate_email' => $email])['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'candidate was not created');
        $this->actAs($hr)->submit("/hr/onboarding/$id/select");
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-portal-link");

        return [$id, $this->row('onboarding_records', ['id' => $id])['candidate_token']];
    }

    private function form(array $over = []): array
    {
        return $over + [
            'candidate_type'  => 'fresher',
            'candidate_phone' => '9876543210',
            'date_of_birth'   => '2000-05-17',
            'address_line1'   => '12 MG Road',
            'city'            => 'Pune',
            'state'           => 'Maharashtra',
            'permanent_same_as_current' => '1',
            'education'       => [['degree' => 'B.Tech', 'institution' => 'COEP', 'specialization' => 'Computer', 'year' => '2024', 'grade_type' => 'CGPA', 'grade' => '8.4']],
            'answers'         => ['q_software_1' => 'PHP, Python', 'q_software_4' => 'REST APIs and databases'],
            'confirm_accuracy' => '1',
        ];
    }

    private function resume(int $id, string $original = 'resume.pdf'): void
    {
        $rec = $this->row('onboarding_records', ['id' => $id]);
        db_connect()->table('documents')->insert([
            'company_id' => $rec['company_id'], 'category' => 'hr', 'document_type' => 'Resume', 'title' => 'Resume',
            'onboarding_record_id' => $id, 'file_path' => 'onboarding_candidates/' . $id . '/seed.pdf', 'original_name' => $original,
            'file_size' => 2048, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function submitted(string $designation = 'Software Engineer', array $over = [], string $company = 'A'): array
    {
        [$id, $token] = $this->linkedCandidate($designation, 'asha.verma@candidates.test', null, $company);
        $this->resume($id);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form($over));

        return [$id, $token];
    }

    private function tmpFile(string $content, string $suffix = '.pdf'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'onb') . $suffix;
        file_put_contents($path, $content);

        return $path;
    }

    /** Simulates a browser file upload field for the next request. */
    private function attach(string $path, string $clientName, ?int $size = null): void
    {
        service('superglobals')->setFilesArray(['candidate_document' => [
            'name' => $clientName, 'type' => 'application/octet-stream', 'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK, 'size' => $size ?? filesize($path),
        ]]);
    }

    private function upload(int $id, string $token, string $type, string $content, string $clientName, ?int $size = null): array
    {
        $dir = WRITEPATH . 'uploads/onboarding_candidates/' . $id;
        $this->uploadDirs[$dir] = $dir;
        $this->attach($this->tmpFile($content), $clientName, $size);
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/documents", ['document_type' => $type]);
        service('superglobals')->setFilesArray([]);

        return json_decode($this->body(), true) ?: [];
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    /** The download endpoint answers "not found" (a thrown 404 in the feature client, a 404 response otherwise). */
    private function assertNotFound(array $as, string $url, string $message): void
    {
        try {
            $this->actAs($as)->visit($url);
            $this->assertSame(404, $this->res->response()->getStatusCode(), $message);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    private function details(int $id, array $as): string
    {
        $this->actAs($as)->visit("/hr/onboarding/$id/candidate-details");

        return $this->body();
    }

    // ------------------------------------------------------------------ role-specific questions

    public function testRoleGroupIsChosenFromTitleDepartmentAndDescription(): void
    {
        $svc = new CandidateFormService();
        $co  = $this->w['A']['company']['id'];
        $pick = fn (array $r) => $svc->roleGroupFor($r + ['company_id' => $co])['slug'];

        $this->assertSame('frontend', $pick(['designation' => 'Frontend Developer']), 'the more specific group beats Software on a tie');
        $this->assertSame('software', $pick(['designation' => 'Software Developer']));
        $this->assertSame('software', $pick(['designation' => 'AI/ML Engineer']));
        $this->assertSame('data', $pick(['designation' => 'Senior Data Analyst']));
        $this->assertSame('data', $pick(['designation' => 'Data Engineer']), 'Data beats the broader Software group');
        $this->assertSame('hr', $pick(['designation' => 'HR Executive']));
        $this->assertSame('finance', $pick(['designation' => 'Accounts Manager']));
        $this->assertSame('operations', $pick(['designation' => 'Warehouse Operations Lead']));
        $this->assertSame('general', $pick(['designation' => 'Chef']), 'unknown roles get the general question set');
        $this->assertSame('general', $pick(['designation' => '']));

        // The department and the job description are used too, but count for less than the title.
        $this->assertSame('frontend', $pick(['designation' => 'Associate', 'job_description' => 'Builds React single page apps']));
        $this->assertSame('finance', $pick(['designation' => 'Associate', 'department_name' => 'Finance']));
        $this->assertSame('software', $pick(['designation' => 'Software Engineer', 'department_name' => 'Finance']));

        // A word is matched whole: "ai" must not match inside "mail".
        $this->assertSame('general', $pick(['designation' => 'Mail Clerk']));
    }

    public function testCandidateFormAdaptsToTheRoleAndNeverShowsOtherRolesQuestions(): void
    {
        [$swId, $swToken] = $this->linkedCandidate('Software Engineer', 'sw@candidates.test');
        $this->anonymous()->visit("/onboarding/portal/$swId/$swToken");
        $html = $this->body();
        $this->assertStringContainsString('Programming languages', $html);
        $this->assertStringContainsString('GitHub profile', $html, 'technical roles get the links section');
        $this->assertStringNotContainsString('Accounting software and tools', $html);
        $this->assertStringContainsString('ONB-' . str_pad((string) $swId, 6, '0', STR_PAD_LEFT), $html);
        $this->assertStringContainsString('Build and maintain our ERP.', $html, 'the job description is shown to the candidate');

        [$finId, $finToken] = $this->linkedCandidate('Accounts Manager', 'fin@candidates.test');
        $this->anonymous()->visit("/onboarding/portal/$finId/$finToken");
        $html = $this->body();
        $this->assertStringContainsString('Accounting software and tools', $html);
        $this->assertStringNotContainsString('Programming languages', $html);
        $this->assertStringNotContainsString('GitHub profile', $html);

        [$genId, $genToken] = $this->linkedCandidate('Chef', 'chef@candidates.test');
        $this->anonymous()->visit("/onboarding/portal/$genId/$genToken");
        $this->assertStringContainsString('Key skills for this role', $this->body());
    }

    // ------------------------------------------------------------------ draft / resume

    public function testProgressCanBeSavedAndResumedWithoutSubmitting(): void
    {
        [$id, $token] = $this->linkedCandidate();

        $this->anonymous()->submit("/onboarding/portal/$id/$token/save", [
            'candidate_type' => 'experienced', 'candidate_phone' => '9123456780', 'city' => 'Mumbai',
            'education' => [['degree' => 'M.Sc', 'institution' => 'IIT Bombay', 'year' => '2019']],
            'experience' => [['employer' => 'Acme Pvt Ltd', 'designation' => 'Developer', 'from' => '2019-07-01', 'current' => '1', 'responsibilities' => 'Built billing APIs.']],
            'answers' => ['q_software_1' => 'Go, Rust'], 'notes' => 'Available from November',
        ]);
        $this->assertFlash('success', 'Progress saved');

        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('link_sent', $rec['status'], 'a draft never submits');
        $this->assertNotSame('submitted', $rec['verification_status']);
        $this->assertNull($rec['verification_submitted_at']);
        $this->assertSame('9123456780', $rec['candidate_phone']);
        $profile = $this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $id]);
        $this->assertSame('experienced', $profile['candidate_type']);
        $this->assertNull($profile['submitted_at']);
        $this->assertNotNull($profile['last_saved_at']);
        $this->assertSame(1, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'experience']));

        // Returning to the same link shows what was saved.
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $html = $this->body();
        foreach (['IIT Bombay', 'Acme Pvt Ltd', 'Go, Rust', 'Available from November', 'Last saved'] as $needle) {
            $this->assertStringContainsString($needle, $html, "saved draft value '$needle' is shown again");
        }

        // Saving again replaces rather than duplicates.
        $this->anonymous()->submit("/onboarding/portal/$id/$token/save", ['candidate_type' => 'experienced', 'education' => [['degree' => 'M.Sc', 'institution' => 'IIT Bombay', 'year' => '2019']]]);
        $this->assertSame(1, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'education']));
        $this->assertSame(1, $this->countRows('onboarding_candidate_profiles', ['onboarding_record_id' => $id]));
    }

    public function testSavingADraftDoesNotWipeHrsChangeRequest(): void
    {
        [$id, $token] = $this->submitted();
        $hr = $this->w['A']['hr'];
        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'correction_needed', 'notes' => 'Please add your GitHub link']);

        $this->anonymous()->submit("/onboarding/portal/$id/$token/save", ['candidate_type' => 'fresher', 'notes' => 'working on it']);
        $this->assertSame('Please add your GitHub link', $this->row('onboarding_records', ['id' => $id])['verification_notes']);

        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $this->assertStringContainsString('Please add your GitHub link', $this->body(), 'the candidate still sees what HR asked for');
    }

    // ------------------------------------------------------------------ validation

    public function testFinalSubmissionIsValidatedOnTheServer(): void
    {
        [$id, $token] = $this->linkedCandidate();
        $submit = fn (array $over, bool $resume = true) => (function () use ($id, $token, $over, $resume) {
            if ($resume && $this->countRows('documents', ['onboarding_record_id' => $id]) === 0) {
                $this->resume($id);
            }
            $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form($over));

            return (array) ($this->flash('form_errors') ?? []);
        })();
        $stillOpen = fn () => $this->assertSame('link_sent', $this->row('onboarding_records', ['id' => $id])['status']);

        // No resume uploaded yet.
        $errors = $submit([], false);
        $this->assertArrayHasKey('documents', $errors);
        $stillOpen();
        $this->resume($id);

        $this->assertArrayHasKey('education', $submit(['education' => []]));
        $this->assertArrayHasKey('candidate_type', $submit(['candidate_type' => '']));
        $this->assertArrayHasKey('candidate_type', $submit(['candidate_type' => 'wizard']));
        $this->assertArrayHasKey('candidate_phone', $submit(['candidate_phone' => '12345']));
        $this->assertArrayHasKey('date_of_birth', $submit(['date_of_birth' => date('Y-m-d', strtotime('+1 day'))]));
        $this->assertArrayHasKey('date_of_birth', $submit(['date_of_birth' => '2031-02-30']));
        $this->assertArrayHasKey('confirm_accuracy', $submit(['confirm_accuracy' => '']));
        $this->assertArrayHasKey('address_line1', $submit(['address_line1' => '']));
        $this->assertArrayHasKey('permanent_address', $submit(['permanent_same_as_current' => '', 'permanent_address' => '']));
        $this->assertArrayHasKey('education.0.year', $submit(['education' => [['degree' => 'B.Tech', 'institution' => 'X', 'year' => '1899']]]));
        $this->assertArrayHasKey('education.0.institution', $submit(['education' => [['degree' => 'B.Tech', 'institution' => '', 'year' => '2020']]]));
        $this->assertArrayHasKey('answers.q_software_1', $submit(['answers' => ['q_software_1' => '', 'q_software_4' => 'x']]), 'required role-specific question');
        $this->assertArrayHasKey('answers.q_software_4', $submit(['answers' => ['q_software_1' => 'PHP', 'q_software_4' => '']]));
        $this->assertArrayHasKey('github_url', $submit(['github_url' => 'javascript:alert(1)']));
        $this->assertArrayHasKey('linkedin_url', $submit(['linkedin_url' => 'not a url']));
        $this->assertArrayHasKey('project.0.contribution', $submit(['project' => [['title' => 'ERP', 'description' => 'd', 'technologies' => 'PHP', 'contribution' => '']]]));
        $this->assertArrayHasKey('project.0.url', $submit(['project' => [['title' => 'ERP', 'description' => 'd', 'technologies' => 'PHP', 'contribution' => 'all', 'url' => 'ftp://x']]]));
        $this->assertArrayHasKey('education', $submit(['education' => array_fill(0, 9, ['degree' => 'B', 'institution' => 'C', 'year' => '2020'])]), 'row limit');
        $this->assertArrayHasKey('education.0.degree', $submit(['education' => [['degree' => str_repeat('x', 151), 'institution' => 'C', 'year' => '2020']]]), 'length limit');

        // Experienced candidates must list their employment, with a valid period.
        $this->assertArrayHasKey('experience', $submit(['candidate_type' => 'experienced']));
        $this->assertArrayHasKey('experience.0.to', $submit(['candidate_type' => 'experienced', 'experience' => [['employer' => 'A', 'designation' => 'B', 'from' => '2020-01-01', 'responsibilities' => 'r']]]), 'end date or "current" required');
        $this->assertArrayHasKey('experience.0.to', $submit(['candidate_type' => 'experienced', 'experience' => [['employer' => 'A', 'designation' => 'B', 'from' => '2021-01-01', 'to' => '2020-01-01', 'responsibilities' => 'r']]]));

        $stillOpen();
        $this->assertSame(0, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id]), 'a rejected submission stores nothing');
        $this->assertSame('B.Tech', $this->form()['education'][0]['degree']); // sanity: the baseline payload itself is valid
        $this->assertSame([], $submit([]), 'the baseline payload passes');
        $this->assertSame('details_submitted', $this->row('onboarding_records', ['id' => $id])['status']);
    }

    // ------------------------------------------------------------------ persistence + HR details page

    public function testExperiencedSubmissionIsStoredInFullAndShownToHr(): void
    {
        [$id, $token] = $this->linkedCandidate();
        $this->resume($id);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form([
            'candidate_type' => 'experienced', 'notice_period' => '30 days', 'github_url' => 'https://github.com/asha', 'portfolio_url' => 'https://asha.dev',
            'education' => [
                ['degree' => 'M.Tech', 'institution' => 'IIT Pune', 'specialization' => 'AI', 'year' => '2022', 'grade_type' => 'Percentage', 'grade' => '81'],
                ['degree' => 'B.E.', 'institution' => 'Mumbai University', 'year' => '2020'],
            ],
            'certification' => [['name' => 'AWS Cloud Practitioner', 'issuer' => 'Amazon', 'year' => '2023', 'url' => 'https://aws.example/cert/1']],
            'experience' => [
                ['employer' => 'Globex <script>alert(1)</script>', 'designation' => 'Senior Developer', 'from' => '2022-08-01', 'current' => '1', 'responsibilities' => 'Led the billing rewrite.', 'achievements' => 'Cut costs 30%', 'skills' => 'Laravel, MySQL'],
                ['employer' => 'Initech', 'designation' => 'Junior Developer', 'from' => '2020-07-01', 'to' => '2022-07-15', 'responsibilities' => 'Maintained reports.', 'reason_for_leaving' => 'Career growth'],
            ],
            'project' => [['title' => 'Invoice OCR', 'description' => 'Extracts invoice fields.', 'technologies' => 'Python, Tesseract', 'contribution' => 'Built the pipeline end to end', 'url' => 'https://github.com/asha/ocr']],
            'internship' => [['organization' => 'Ignored Inc', 'role' => 'Intern', 'description' => 'Not applicable to experienced candidates']],
            'answers' => ['q_software_1' => 'PHP, Python, Go', 'q_software_4' => 'APIs, testing', 'q_software_6' => 'A race condition in the queue worker', 'q_not_a_question' => 'ignored'],
            // Browser-supplied values that must never be trusted:
            'status' => 'approved', 'company_id' => $this->w['B']['company']['id'], 'verification_status' => 'verified', 'id' => 999999,
            'candidate_email' => 'evil@x.test', 'candidate_name' => 'Mallory', 'role_group_id' => 1, 'offered_ctc' => '99999999',
        ]));
        $this->assertFlash('success', 'submitted for HR review');

        // --- protected fields untouched
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('details_submitted', $rec['status']);
        $this->assertSame('submitted', $rec['verification_status']);
        $this->assertSame((string) $this->w['A']['company']['id'], (string) $rec['company_id']);
        $this->assertSame('asha.verma@candidates.test', $rec['candidate_email']);
        $this->assertSame('Asha Verma', $rec['candidate_name']);
        $this->assertNull($rec['offered_ctc']);
        $this->assertNull($this->row('onboarding_records', ['id' => 999999]));

        // --- legacy columns follow the first (highest) education row, so employee creation keeps working
        $this->assertSame('M.Tech', $rec['highest_qualification']);
        $this->assertSame('IIT Pune', $rec['institution']);
        $this->assertSame('2022', (string) $rec['graduation_year']);
        $this->assertSame('2000-05-17', $rec['date_of_birth']);

        // --- repeatable records + answers
        $count = fn (string $section) => $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => $section]);
        $this->assertSame(2, $count('education'));
        $this->assertSame(1, $count('certification'));
        $this->assertSame(2, $count('experience'));
        $this->assertSame(1, $count('project'));
        $this->assertSame(0, $count('internship'), 'internships do not apply to experienced candidates');
        $answers = array_column($this->rows('onboarding_candidate_answers', ['onboarding_record_id' => $id]), 'answer', 'question_key');
        $this->assertSame('PHP, Python, Go', $answers['q_software_1']);
        $this->assertArrayHasKey('q_software_6', $answers);
        $this->assertArrayNotHasKey('q_not_a_question', $answers);
        $profile = $this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $id]);
        $this->assertSame('experienced', $profile['candidate_type']);
        $this->assertSame('1', (string) $profile['revision']);
        $this->assertSame('Software / AI-ML Engineering', $profile['role_group_name']);
        $this->assertSame('30 days', $profile['notice_period']);

        // --- HR sees everything, escaped
        $html = $this->details($id, $this->w['A']['hr']);
        foreach (['Asha Verma', 'ONB-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'Experienced', 'M.Tech', 'IIT Pune', 'Mumbai University', 'AWS Cloud Practitioner',
                  'Programming languages', '>Python</span>', '>Go</span>', 'race condition in the queue worker', 'Invoice OCR', 'Built the pipeline end to end',
                  'Senior Developer', 'Led the billing rewrite.', 'Initech', 'Career growth', 'Present', 'https://github.com/asha', '30 days', 'resume.pdf', 'Resume'] as $needle) {
            $this->assertStringContainsString($needle, $html, "HR details page shows '$needle'");
        }
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'candidate text is escaped');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('Ignored Inc', $html);
        $this->assertStringContainsString('Start HR Review', $html);
    }

    public function testFresherGetsInternshipsAndProjectsAndNoEmployment(): void
    {
        [$id] = $this->submitted('Software Engineer', [
            'candidate_type' => 'fresher',
            'internship' => [['organization' => 'Startup Labs', 'role' => 'Backend Intern', 'from' => '2023-06-01', 'to' => '2023-08-31', 'description' => 'Built an admin dashboard.', 'skills' => 'Node, MySQL']],
            'project' => [['title' => 'Library App', 'description' => 'Book tracker.', 'technologies' => 'PHP', 'contribution' => 'Everything']],
            'experience' => [['employer' => 'Should Be Dropped', 'designation' => 'x', 'from' => '2020-01-01', 'current' => '1', 'responsibilities' => 'x']],
        ]);

        $this->assertSame(0, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'experience']));
        $this->assertSame(1, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'internship']));
        $html = $this->details($id, $this->w['A']['hr']);
        $this->assertStringContainsString('Startup Labs', $html);
        $this->assertStringContainsString('Library App', $html);
        $this->assertStringContainsString('Not applicable', $html, 'employment history is marked not applicable for freshers');
        $this->assertStringNotContainsString('Should Be Dropped', $html);
    }

    // ------------------------------------------------------------------ duplicate submit / links

    public function testASecondSubmissionIsRefusedAndKeepsTheFirst(): void
    {
        [$id, $token] = $this->submitted();
        $first = $this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $id]);

        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form(['education' => [['degree' => 'Hacked', 'institution' => 'Z', 'year' => '2020']], 'candidate_phone' => '9000000000']));
        $this->assertFlash('error', 'already been submitted');
        $this->anonymous()->submit("/onboarding/portal/$id/$token/save", $this->form(['candidate_phone' => '9000000000']));
        $this->assertFlash('error', 'already been submitted');

        $after = $this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $id]);
        $this->assertSame($first['revision'], $after['revision']);
        $this->assertSame($first['submitted_at'], $after['submitted_at']);
        $this->assertSame('B.Tech', json_decode($this->row('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'education'])['data'], true)['degree']);
        $this->assertSame('9876543210', $this->row('onboarding_records', ['id' => $id])['candidate_phone']);

        // While it is with HR the candidate sees a read-only confirmation, not the form.
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $html = $this->body();
        $this->assertStringContainsString('Submitted', $html);
        $this->assertStringNotContainsString('id="cf"', $html);
    }

    public function testExpiredWrongAndForeignTokensCannotReadOrWrite(): void
    {
        [$id, $token] = $this->linkedCandidate('Software Engineer', 'one@candidates.test');
        [$otherId, $otherToken] = $this->linkedCandidate('Software Engineer', 'two@candidates.test');
        $this->resume($id);

        // Another candidate's token does not open this record, and a bad token is not an oracle for the id.
        foreach ([[$id, $otherToken], [$id, str_repeat('a', 48)], [$otherId, $token]] as [$i, $t]) {
            $this->anonymous()->submit("/onboarding/portal/$i/$t/verify", $this->form());
            $this->assertSame('link_sent', $this->row('onboarding_records', ['id' => $i])['status']);
            $this->anonymous()->submit("/onboarding/portal/$i/$t/save", $this->form());
            $this->assertNull($this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $i]));
            $this->anonymous()->visit("/onboarding/portal/$i/$t");
            $this->assertStringNotContainsString('two@candidates.test', $this->body());
        }

        // Expired link: nothing can be saved, submitted or uploaded.
        db_connect()->table('onboarding_records')->where('id', $id)->update(['token_expires_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form());
        $this->assertSame('link_sent', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/save", $this->form());
        $this->assertNull($this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $id]));
        $reply = $this->upload($id, $token, 'Resume', $this->pdf(), 'cv.pdf');
        $this->assertFalse($reply['ok'] ?? false);
        $this->assertSame(1, $this->countRows('documents', ['onboarding_record_id' => $id]), 'only the seeded resume exists');
    }

    // ------------------------------------------------------------------ uploads

    public function testUploadsAreValidatedStoredPrivatelyAndReplaceTheResume(): void
    {
        [$id, $token] = $this->linkedCandidate();

        // Valid PDF resume.
        $reply = $this->upload($id, $token, 'Resume', $this->pdf(), 'My CV.pdf');
        $this->assertTrue($reply['ok'] ?? false, json_encode($reply));
        $doc = $this->row('documents', ['onboarding_record_id' => $id, 'document_type' => 'Resume']);
        $this->assertNotNull($doc);
        $this->assertStringStartsWith('onboarding_candidates/' . $id . '/', $doc['file_path'], 'stored in the HR-only folder');
        $this->assertNotSame('My CV.pdf', basename($doc['file_path']), 'stored under a random name');
        $this->assertSame('My CV.pdf', $doc['original_name']);
        $this->assertSame((string) $this->w['A']['company']['id'], (string) $doc['company_id']);
        $this->assertNull($doc['uploaded_by']);
        $this->assertFileExists(WRITEPATH . 'uploads/' . $doc['file_path']);

        // A new resume replaces the old one (file and row).
        $oldPath = $doc['file_path'];
        $reply = $this->upload($id, $token, 'Resume', $this->pdf() . "\n%newer", 'CV v2.pdf');
        $this->assertTrue($reply['ok'] ?? false);
        $this->assertSame(1, $this->countRows('documents', ['onboarding_record_id' => $id, 'document_type' => 'Resume']));
        $this->assertFileDoesNotExist(WRITEPATH . 'uploads/' . $oldPath);

        // Rejected uploads: wrong type, disguised files, oversize, empty, unknown document type.
        $bad = [
            'executable'       => ['Resume', '<?php echo 1;', 'shell.php'],
            'exe renamed'      => ['Resume', "MZ\x90\x00 fake exe", 'cv.pdf'],
            'png as pdf'       => ['Resume', "\x89PNG\r\n\x1a\n....", 'cv.pdf'],
            'text as docx'     => ['Resume', 'just text', 'cv.docx'],
            'docx not allowed' => ['Identity proof', 'PK', 'id.docx'],
            'unknown type'     => ['Passport photo', $this->pdf(), 'p.pdf'],
            'empty file'       => ['Certification', '', 'c.pdf'],
        ];
        foreach ($bad as $label => [$type, $content, $name]) {
            $reply = $this->upload($id, $token, $type, $content, $name);
            $this->assertFalse($reply['ok'] ?? true, "$label must be refused");
            $this->assertNotEmpty($reply['message'] ?? '', "$label gets a clear message");
        }
        $reply = $this->upload($id, $token, 'Certification', $this->pdf(), 'big.pdf', 11 * 1024 * 1024);
        $this->assertFalse($reply['ok'] ?? true, 'oversize file is refused');
        $this->assertStringContainsString('too large', $reply['message']);
        $this->assertSame(1, $this->countRows('documents', ['onboarding_record_id' => $id]), 'no rejected file was stored');

        // Supporting document + the candidate can remove their own, but nobody else's.
        $reply = $this->upload($id, $token, 'Education certificate', $this->pdf(), 'degree.pdf');
        $this->assertTrue($reply['ok'] ?? false);
        $certId = (int) $this->row('documents', ['onboarding_record_id' => $id, 'document_type' => 'Education certificate'])['id'];

        [$otherId, $otherToken] = $this->linkedCandidate('Software Engineer', 'other@candidates.test');
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        $this->anonymous()->submit("/onboarding/portal/$otherId/$otherToken/documents/$certId/delete");
        $this->assertNotNull($this->row('documents', ['id' => $certId]), "another candidate cannot delete this candidate's document");

        $this->anonymous()->submit("/onboarding/portal/$id/$token/documents/$certId/delete");
        $this->assertNull($this->row('documents', ['id' => $certId]));

        // Once submitted, the file set is frozen.
        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form());
        $this->assertSame('details_submitted', $this->row('onboarding_records', ['id' => $id])['status']);
        $reply = $this->upload($id, $token, 'Certification', $this->pdf(), 'late.pdf');
        $this->assertFalse($reply['ok'] ?? true, 'no uploads while HR is reviewing');
    }

    public function testCandidateDocumentsAreDownloadableByHrOfThatCompanyOnly(): void
    {
        [$id, $token] = $this->linkedCandidate();
        $reply = $this->upload($id, $token, 'Resume', $this->pdf(), 'cv.pdf');
        $path  = $this->row('documents', ['onboarding_record_id' => $id])['file_path'];
        $url   = '/files/download?path=' . urlencode($path);

        $this->actAs($this->w['A']['hr'])->visit($url);
        $this->assertSame(200, $this->res->response()->getStatusCode(), 'HR of the same company can download');

        $this->actAs($this->w['A']['hr'])->visit($url . '&inline=1');
        $this->assertSame(200, $this->res->response()->getStatusCode(), 'and preview inline');

        $this->actAs($this->w['super'])->visit($url);
        $this->assertSame(200, $this->res->response()->getStatusCode(), 'Super Admin can download');

        foreach (['employee', 'manager', 'accountant', 'admin', 'compliance'] as $role) {
            $this->assertNotFound($this->w['A'][$role], $url, "$role of the same company is refused");
        }
        $this->assertNotFound($this->w['B']['hr'], $url, 'HR of another company is refused');

        $this->anonymous()->visit($url);
        $this->assertTrue($this->res->isRedirect() || in_array($this->res->response()->getStatusCode(), [401, 403], true), 'anonymous visitors are turned away (the AJAX header from the upload helper makes the auth filter answer 401 instead of redirecting)');
        $this->assertNotFound($this->w['A']['hr'], '/files/download?path=' . urlencode('onboarding_candidates/' . $id . '/../../../.env'), 'path traversal is refused');
    }

    public function testDeletingARecordRemovesTheCandidatesPersonalDataAndFiles(): void
    {
        [$id, $token] = $this->linkedCandidate();
        $this->assertTrue($this->upload($id, $token, 'Resume', $this->pdf(), 'cv.pdf')['ok'] ?? false);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/save", $this->form());
        $file = WRITEPATH . 'uploads/' . $this->row('documents', ['onboarding_record_id' => $id])['file_path'];
        $this->assertFileExists($file);

        $this->actAs($this->w['A']['hr'])->submit("/hr/onboarding/$id/delete");

        $this->assertNull($this->row('onboarding_records', ['id' => $id]));
        $this->assertFileDoesNotExist($file, 'the resume file is deleted with the record');
        $this->assertSame(0, $this->countRows('documents', ['onboarding_record_id' => $id]));
        foreach (['onboarding_candidate_profiles', 'onboarding_candidate_entries', 'onboarding_candidate_answers', 'onboarding_evaluations'] as $table) {
            $this->assertSame(0, $this->countRows($table, ['onboarding_record_id' => $id]), "$table rows are removed with the record");
        }
    }

    // ------------------------------------------------------------------ HR details, evaluation, decisions

    public function testOnlyHrOfTheCompanyCanOpenCandidateDetailsAndEvaluate(): void
    {
        [$id] = $this->submitted();

        $this->actAs($this->w['A']['hr'])->visit("/hr/onboarding/$id/candidate-details");
        $this->assertTrue($this->res->isOK());
        $this->actAs($this->w['super'])->visit("/hr/onboarding/$id/candidate-details");
        $this->assertTrue($this->res->isOK());

        foreach (['employee', 'manager', 'accountant', 'admin', 'compliance'] as $role) {
            $this->actAs($this->w['A'][$role])->visit("/hr/onboarding/$id/candidate-details");
            $this->assertFalse($this->res->isOK() && str_contains($this->body(), 'Asha Verma'), "$role must not see candidate details");
            $this->actAs($this->w['A'][$role])->submit("/hr/onboarding/$id/evaluation", ['technical_assessment' => 'x']);
            $this->assertSame(0, $this->countRows('onboarding_evaluations'), "$role must not write an evaluation");
        }

        $this->actAs($this->w['B']['hr'])->visit("/hr/onboarding/$id/candidate-details");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'not found');
        $this->actAs($this->w['B']['hr'])->submit("/hr/onboarding/$id/evaluation", ['technical_assessment' => 'x']);
        $this->assertSame(0, $this->countRows('onboarding_evaluations'));

        $this->anonymous()->visit("/hr/onboarding/$id/candidate-details");
        $this->assertTrue($this->res->isRedirect());
        $this->assertStringContainsString('auth/login', $this->res->getRedirectUrl());
    }

    public function testStructuredEvaluationIsSavedValidatedAndNeverDecidesOnItsOwn(): void
    {
        [$id, $token] = $this->submitted();
        $hr = $this->w['A']['hr'];
        $criteria = $this->rows('onboarding_eval_criteria', ['role_group_id' => $this->row('onboarding_role_groups', ['slug' => 'software', 'company_id' => null])['id'], 'is_active' => 1]);
        $this->assertGreaterThanOrEqual(3, count($criteria));

        // Nothing to evaluate before a submission exists.
        [$freshId] = $this->linkedCandidate('Chef', 'chef2@candidates.test');
        $this->actAs($hr)->submit("/hr/onboarding/$freshId/evaluation", ['technical_assessment' => 'x']);
        $this->assertFlash('error', 'no submission');
        $this->assertSame(0, $this->countRows('onboarding_evaluations'));

        // Invalid scores / over-long text are rejected without saving.
        $this->actAs($hr)->submit("/hr/onboarding/$id/evaluation", ['score' => [$criteria[0]['id'] => '9']]);
        $this->assertSame(0, $this->countRows('onboarding_evaluations'));
        $this->actAs($hr)->submit("/hr/onboarding/$id/evaluation", ['strengths' => str_repeat('x', 3001)]);
        $this->assertSame(0, $this->countRows('onboarding_evaluations'));

        $scores = [];
        foreach ($criteria as $c) {
            $scores[$c['id']] = '1'; // deliberately the worst possible scores
        }
        $this->actAs($hr)->submit("/hr/onboarding/$id/evaluation", [
            'score' => $scores, 'score_note' => [$criteria[0]['id'] => 'Weak on algorithms'],
            'technical_assessment' => 'Needs mentoring on data structures.', 'experience_assessment' => 'One internship.',
            'strengths' => 'Eager to learn', 'concerns' => 'No tests in the project', 'hr_comments' => 'Call the referee', 'decision_notes' => 'Lean towards hiring',
        ]);
        $this->assertFlash('success', 'Evaluation saved');
        $ev = $this->row('onboarding_evaluations', ['onboarding_record_id' => $id]);
        $this->assertSame((string) $hr['user_id'], (string) $ev['evaluator_id']);
        $this->assertSame('Software / AI-ML Engineering', $ev['role_group_name']);
        $saved = json_decode($ev['criteria_scores'], true);
        $this->assertCount(count($criteria), $saved);
        $this->assertSame($criteria[0]['label'], $saved[0]['label'], 'criterion labels are snapshotted');
        $this->assertSame('Weak on algorithms', $saved[0]['note']);

        // Updating keeps a single row.
        $this->actAs($hr)->submit("/hr/onboarding/$id/evaluation", ['strengths' => 'Eager to learn and curious']);
        $this->assertSame(1, $this->countRows('onboarding_evaluations', ['onboarding_record_id' => $id]));
        $this->assertSame('Eager to learn and curious', $this->row('onboarding_evaluations', ['onboarding_record_id' => $id])['strengths']);

        // Saving an evaluation never changes the status, and the worst scores do not block or auto-reject.
        $this->assertSame('details_submitted', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/evaluation", ['score' => $scores, 'score_note' => [$criteria[0]['id'] => 'Weak on algorithms'], 'technical_assessment' => 'Needs mentoring on data structures.']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        $this->assertTrue($this->res->isRedirect());
        $this->assertStringContainsString("/hr/onboarding/$id/candidate-details", $this->res->getRedirectUrl(), 'starting review lands on the details page');
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'approved', 'notes' => 'Hire with mentoring']);
        $this->assertSame('approved', $this->row('onboarding_records', ['id' => $id])['status'], 'a low score does not stop HR approving');

        // The saved evaluation is visible on the details page.
        $html = $this->details($id, $hr);
        $this->assertStringContainsString('Needs mentoring on data structures.', $html);
        $this->assertStringContainsString('Weak on algorithms', $html);
    }

    public function testRequestChangesLoopsThroughTheCandidateLinkAndBumpsTheRevision(): void
    {
        [$id, $token] = $this->submitted();
        $hr = $this->w['A']['hr'];

        // Decisions are only possible during HR review.
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'correction_needed', 'notes' => 'too early']);
        $this->assertSame('details_submitted', $this->row('onboarding_records', ['id' => $id])['status']);

        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        // Note required (and bounded).
        foreach ([['decision' => 'correction_needed'], ['decision' => 'correction_needed', 'notes' => 'no'], ['decision' => 'correction_needed', 'notes' => str_repeat('x', 2001)], ['decision' => 'rejected'], ['decision' => 'maybe', 'notes' => 'whatever it is']] as $bad) {
            $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", $bad);
            $this->assertSame('hr_review', $this->row('onboarding_records', ['id' => $id])['status']);
        }

        // The link is about to expire; requesting changes keeps it usable for the candidate.
        db_connect()->table('onboarding_records')->where('id', $id)->update(['token_expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))]);
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'correction_needed', 'notes' => 'Please add a GitHub link and a project']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('link_sent', $rec['status']);
        $this->assertSame('correction_needed', $rec['verification_status']);
        $this->assertSame($token, $rec['candidate_token'], 'the same link keeps working');
        $this->assertGreaterThan(date('Y-m-d H:i:s', strtotime('+6 days')), $rec['token_expires_at'], 'and is extended');
        $this->assertNotNull($this->row('activity_logs', ['module' => 'onboarding', 'record_id' => $id, 'description' => 'Requested changes on candidate-submitted information for Asha Verma: Please add a GitHub link and a project']));

        // HR's page reflects the wait; the candidate's page shows what to fix with their earlier answers.
        $this->assertStringContainsString('Please add a GitHub link and a project', $this->details($id, $hr));
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $html = $this->body();
        $this->assertStringContainsString('Please add a GitHub link and a project', $html);
        $this->assertStringContainsString('COEP', $html, 'earlier answers are kept for editing');

        // Resubmit with the change; the stored data is replaced, not duplicated.
        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->form([
            'github_url' => 'https://github.com/asha',
            'project' => [['title' => 'ERP add-on', 'description' => 'Reports', 'technologies' => 'PHP', 'contribution' => 'All of it']],
        ]));
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('details_submitted', $rec['status']);
        $profile = $this->row('onboarding_candidate_profiles', ['onboarding_record_id' => $id]);
        $this->assertSame('2', (string) $profile['revision']);
        $this->assertSame('https://github.com/asha', $profile['github_url']);
        $this->assertSame(1, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'education']));
        $this->assertSame(1, $this->countRows('onboarding_candidate_entries', ['onboarding_record_id' => $id, 'section' => 'project']));
        $this->assertStringContainsString('submission #2', $this->details($id, $hr));

        // Reject needs a reason and closes the process.
        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'rejected', 'notes' => 'Position filled internally']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('rejected', $rec['status']);
        $this->assertSame('Position filled internally', $rec['verification_notes']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'approved']);
        $this->assertSame('rejected', $this->row('onboarding_records', ['id' => $id])['status'], 'a closed record cannot be approved');
    }

    public function testLegacyShortSubmissionsStillWorkEndToEnd(): void
    {
        $hr = $this->w['A']['hr'];
        [$id, $token] = $this->linkedCandidate();
        // A record submitted through the original short form: legacy columns only, no profile rows.
        db_connect()->table('onboarding_records')->where('id', $id)->update([
            'status' => 'hr_review', 'verification_status' => 'submitted', 'verification_submitted_at' => date('Y-m-d H:i:s'),
            'highest_qualification' => 'B.Com', 'institution' => 'Old College', 'graduation_year' => 2015, 'address_line1' => '1 Old Street', 'gender' => 'Female',
        ]);

        $html = $this->details($id, $hr);
        $this->assertStringContainsString('B.Com', $html);
        $this->assertStringContainsString('Old College', $html);
        $this->assertStringContainsString('1 Old Street', $html);
        $this->assertStringContainsString('before the detailed form', $html, 'HR is told only basic details exist');

        // No evaluation is demanded for a legacy record, so HR can still approve it.
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'approved']);
        $this->assertSame('approved', $this->row('onboarding_records', ['id' => $id])['status']);

        // The record page still opens and links to the details.
        $this->actAs($hr)->visit("/hr/onboarding/$id");
        $this->assertStringContainsString('View Candidate Details', $this->body());
    }

    public function testLegacyDetailsSeedTheFormWhenAnOldLinkIsOpened(): void
    {
        [$id, $token] = $this->linkedCandidate();
        db_connect()->table('onboarding_records')->where('id', $id)->update(['highest_qualification' => 'B.Sc', 'institution' => 'Pune Univ', 'graduation_year' => 2018]);

        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $html = $this->body();
        $this->assertStringContainsString('B.Sc', $html);
        $this->assertStringContainsString('Pune Univ', $html);
    }

    // ------------------------------------------------------------------ role-group administration

    public function testHrCustomisesASharedRoleGroupForTheirCompanyOnly(): void
    {
        $hrA = $this->w['A']['hr'];
        $hrB = $this->w['B']['hr'];
        $shared = $this->row('onboarding_role_groups', ['slug' => 'software', 'company_id' => null]);
        $sharedCount = $this->countRows('onboarding_questions', ['role_group_id' => $shared['id']]);

        $this->actAs($hrA)->visit('/hr/onboarding/role-groups');
        $this->assertTrue($this->res->isOK());
        $this->assertStringContainsString('Software / AI-ML Engineering', $this->body());

        // The shared default is read-only to HR.
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$shared['id']}/questions", ['label' => 'Injected into everyone', 'field_type' => 'text', 'applies_to' => 'all']);
        $this->assertFlash('error', 'shared default');
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$shared['id']}", ['name' => 'Renamed', 'priority' => '1']);
        $this->assertSame('Software / AI-ML Engineering', $this->row('onboarding_role_groups', ['id' => $shared['id']])['name']);
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$shared['id']}/delete");
        $this->assertNotNull($this->row('onboarding_role_groups', ['id' => $shared['id']]));
        $this->assertSame($sharedCount, $this->countRows('onboarding_questions', ['role_group_id' => $shared['id']]));

        // Customise -> an editable company copy with the same questions.
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$shared['id']}/clone");
        $copy = $this->row('onboarding_role_groups', ['slug' => 'software', 'company_id' => $this->w['A']['company']['id']]);
        $this->assertNotNull($copy);
        $this->assertSame($sharedCount, $this->countRows('onboarding_questions', ['role_group_id' => $copy['id']]));
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$shared['id']}/clone");
        $this->assertFlash('error', 'already has its own copy');
        $this->assertSame(1, $this->countRows('onboarding_role_groups', ['slug' => 'software', 'company_id' => $this->w['A']['company']['id']]));

        // Add a required dropdown question + a criterion to the copy.
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$copy['id']}/questions", ['label' => 'Preferred work mode', 'field_type' => 'select', 'options' => "Remote\nOffice", 'applies_to' => 'all', 'is_required' => '1', 'sort_order' => '5']);
        $q = $this->row('onboarding_questions', ['role_group_id' => $copy['id'], 'label' => 'Preferred work mode']);
        $this->assertNotNull($q);
        $this->assertSame('1', (string) $q['is_required']);
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$copy['id']}/questions", ['label' => 'Bad dropdown', 'field_type' => 'select', 'options' => 'OnlyOne', 'applies_to' => 'all']);
        $this->assertNull($this->row('onboarding_questions', ['label' => 'Bad dropdown']), 'a dropdown needs at least two options');
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$copy['id']}/criteria", ['label' => 'Culture fit', 'sort_order' => '99']);
        $this->assertNotNull($this->row('onboarding_eval_criteria', ['role_group_id' => $copy['id'], 'label' => 'Culture fit']));

        // Company A's candidates get the new question (and it is enforced); company B's do not.
        [$aId, $aToken] = $this->linkedCandidate('Software Engineer', 'a1@candidates.test');
        $this->anonymous()->visit("/onboarding/portal/$aId/$aToken");
        $this->assertStringContainsString('Preferred work mode', $this->body());
        $this->resume($aId);
        $this->anonymous()->submit("/onboarding/portal/$aId/$aToken/verify", $this->form());
        $this->assertArrayHasKey('answers.' . $q['question_key'], (array) $this->flash('form_errors'));
        $this->anonymous()->submit("/onboarding/portal/$aId/$aToken/verify", $this->form(['answers' => ['q_software_1' => 'PHP', 'q_software_4' => 'APIs', $q['question_key'] => 'Hybrid']]));
        $this->assertArrayHasKey('answers.' . $q['question_key'], (array) $this->flash('form_errors'), 'a value outside the options is refused');
        $this->anonymous()->submit("/onboarding/portal/$aId/$aToken/verify", $this->form(['answers' => ['q_software_1' => 'PHP', 'q_software_4' => 'APIs', $q['question_key'] => 'Remote']]));
        $this->assertSame('details_submitted', $this->row('onboarding_records', ['id' => $aId])['status']);
        $this->assertStringContainsString('Preferred work mode', $this->details($aId, $hrA));
        $this->assertStringContainsString('Culture fit', $this->details($aId, $hrA), 'the new criterion is offered for evaluation');

        [$bId, $bToken] = $this->linkedCandidate('Software Engineer', 'b1@candidates.test', null, 'B');
        $this->anonymous()->visit("/onboarding/portal/$bId/$bToken");
        $this->assertStringNotContainsString('Preferred work mode', $this->body());

        // HR of company B cannot see or touch company A's copy.
        $this->actAs($hrB)->visit("/hr/onboarding/role-groups/{$copy['id']}/edit");
        $this->assertFlash('error', 'not found');
        $this->actAs($hrB)->submit("/hr/onboarding/role-groups/{$copy['id']}/questions", ['label' => 'Cross company', 'field_type' => 'text', 'applies_to' => 'all']);
        $this->assertNull($this->row('onboarding_questions', ['label' => 'Cross company']));

        // Deleting the copy hands the company back to the shared default; answers already given are kept.
        $this->actAs($hrA)->submit("/hr/onboarding/role-groups/{$copy['id']}/delete");
        $this->assertNull($this->row('onboarding_role_groups', ['id' => $copy['id']]));
        $this->assertSame(1, $this->countRows('onboarding_candidate_answers', ['onboarding_record_id' => $aId, 'question_label' => 'Preferred work mode']));
        [$a2Id, $a2Token] = $this->linkedCandidate('Software Engineer', 'a2@candidates.test');
        $this->anonymous()->visit("/onboarding/portal/$a2Id/$a2Token");
        $this->assertStringNotContainsString('Preferred work mode', $this->body());
    }

    public function testRoleGroupAdministrationIsRestrictedAndTheFallbackIsProtected(): void
    {
        $shared = $this->row('onboarding_role_groups', ['slug' => 'general', 'company_id' => null]);

        foreach (['employee', 'manager', 'accountant', 'admin', 'compliance'] as $role) {
            $this->actAs($this->w['A'][$role])->submit('/hr/onboarding/role-groups', ['name' => 'Sneaky']);
            $this->assertNull($this->row('onboarding_role_groups', ['name' => 'Sneaky']), "$role cannot create role groups");
            $this->actAs($this->w['A'][$role])->visit('/hr/onboarding/role-groups');
            $this->assertFalse($this->res->isOK() && str_contains($this->body(), 'Candidate Questions'), "$role cannot open the configuration");
        }

        // HR creates their own group; it is bound to their company regardless of what is posted.
        $this->actAs($this->w['A']['hr'])->submit('/hr/onboarding/role-groups', [
            'name' => 'Sales & Business Development', 'keywords' => 'sales, business development', 'priority' => '50', 'is_active' => '1',
            'company_id' => $this->w['B']['company']['id'],
        ]);
        $group = $this->row('onboarding_role_groups', ['name' => 'Sales & Business Development']);
        $this->assertNotNull($group);
        $this->assertSame((string) $this->w['A']['company']['id'], (string) $group['company_id']);

        // ...and candidates with a matching title are routed to it.
        $this->actAs($this->w['A']['hr'])->submit("/hr/onboarding/role-groups/{$group['id']}/questions", ['label' => 'Largest deal you closed', 'field_type' => 'textarea', 'applies_to' => 'experienced']);
        $svc = new CandidateFormService();
        $this->assertSame('sales-business-development', $svc->roleGroupFor(['company_id' => $this->w['A']['company']['id'], 'designation' => 'Inside Sales Executive'])['slug']);
        $this->assertSame('general', $svc->roleGroupFor(['company_id' => $this->w['B']['company']['id'], 'designation' => 'Inside Sales Executive'])['slug'], "another company's group never applies");

        // Nobody deletes the shared fallback.
        $this->actAs($this->w['super'])->submit("/hr/onboarding/role-groups/{$shared['id']}/delete");
        $this->assertNotNull($this->row('onboarding_role_groups', ['id' => $shared['id']]));
        $this->assertFlash('error', 'fallback');

        // Even with every group removed, the form still works (built-in fallback questions).
        // The seeded reference rows are restored afterwards so later tests are unaffected.
        $db        = db_connect();
        $snapshot  = ['onboarding_role_groups' => $db->table('onboarding_role_groups')->get()->getResultArray(),
                      'onboarding_questions'   => $db->table('onboarding_questions')->get()->getResultArray(),
                      'onboarding_eval_criteria' => $db->table('onboarding_eval_criteria')->get()->getResultArray()];
        try {
            $db->query('SET FOREIGN_KEY_CHECKS = 0');
            foreach (array_keys($snapshot) as $table) {
                $db->query('DELETE FROM `' . $table . '`');
            }
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
            $fallback = $svc->roleGroupFor(['company_id' => $this->w['A']['company']['id'], 'designation' => 'Software Engineer']);
            $this->assertSame('general', $fallback['slug']);
            $this->assertNull($fallback['id']);
            $hydrated = $svc->groupForRecord(['company_id' => $this->w['A']['company']['id'], 'designation' => 'Software Engineer']);
            $this->assertNotEmpty($hydrated['questions'], 'the built-in fallback still asks something');
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($snapshot as $table => $rows) {
                $db->query('DELETE FROM `' . $table . '`');
                foreach (array_chunk($rows, 50) as $chunk) {
                    $db->table($table)->insertBatch($chunk);
                }
            }
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}

}
