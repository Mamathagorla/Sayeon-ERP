<?php

namespace App\Modules\Onboarding\Services;

use App\Modules\Onboarding\Models\EvalCriterionModel;
use App\Modules\Onboarding\Models\OnboardingRecordModel;
use App\Modules\Onboarding\Models\QuestionModel;
use App\Modules\Onboarding\Models\RoleGroupModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Everything the candidate form and the HR details page share:
 *  - which role group (and therefore which questions / evaluation
 *    criteria) a record gets, resolved from its job title, department and
 *    job description against admin-configurable keywords;
 *  - the field schemas of the repeatable sections (education, ...);
 *  - turning raw POST data into clean, validated values;
 *  - persisting them across onboarding_records + the candidate tables.
 *
 * The browser never supplies company, record, role group or status: the
 * caller passes a record loaded by (id + token) and everything else is
 * derived here.
 */
class CandidateFormService
{
    public const TYPES = ['fresher' => 'Fresher', 'experienced' => 'Experienced'];

    public const GRADE_TYPES = ['Percentage', 'CGPA', 'Grade'];

    public const DOCUMENT_TYPES = [
        'Resume'                => ['label' => 'Resume / CV', 'formats' => ['pdf', 'docx']],
        'Education certificate' => ['label' => 'Education certificate / marksheet', 'formats' => ['pdf', 'jpg', 'jpeg', 'png']],
        'Experience letter'     => ['label' => 'Experience / relieving letter', 'formats' => ['pdf', 'jpg', 'jpeg', 'png']],
        'Certification'         => ['label' => 'Certification', 'formats' => ['pdf', 'jpg', 'jpeg', 'png']],
        'Identity proof'        => ['label' => 'Identity proof', 'formats' => ['pdf', 'jpg', 'jpeg', 'png']],
        'Address proof'         => ['label' => 'Address proof', 'formats' => ['pdf', 'jpg', 'jpeg', 'png']],
        'Other'                 => ['label' => 'Other supporting document', 'formats' => ['pdf', 'jpg', 'jpeg', 'png']],
    ];
    public const MAX_UPLOAD_BYTES  = 10 * 1024 * 1024;
    public const MAX_DOCUMENTS     = 12;
    public const STORAGE_FOLDER    = 'onboarding_candidates';
    private const MIME_BY_EXT      = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];

    /**
     * Field schema of every repeatable section. type: text | textarea | year
     * | date | select | url | check. HR page + candidate form + validation
     * all read this one definition.
     */
    public const SECTIONS = [
        'education' => [
            'label' => 'Education', 'item' => 'Qualification', 'max' => 8, 'fields' => [
                'degree'         => ['label' => 'Qualification / degree', 'type' => 'text', 'required' => true, 'max' => 150, 'placeholder' => 'e.g. B.Tech, MBA, B.Com'],
                'institution'    => ['label' => 'College / university', 'type' => 'text', 'required' => true, 'max' => 190],
                'specialization' => ['label' => 'Specialization / branch', 'type' => 'text', 'max' => 150],
                'year'           => ['label' => 'Graduation year', 'type' => 'year', 'required' => true],
                'grade_type'     => ['label' => 'Grade type', 'type' => 'select', 'options' => self::GRADE_TYPES],
                'grade'          => ['label' => 'Percentage / CGPA / grade', 'type' => 'text', 'max' => 20],
            ],
        ],
        'certification' => [
            'label' => 'Certifications', 'item' => 'Certification', 'max' => 15, 'fields' => [
                'name'   => ['label' => 'Certification', 'type' => 'text', 'required' => true, 'max' => 190],
                'issuer' => ['label' => 'Issued by', 'type' => 'text', 'max' => 150],
                'year'   => ['label' => 'Year', 'type' => 'year'],
                'url'    => ['label' => 'Credential link', 'type' => 'url', 'max' => 255],
            ],
        ],
        'project' => [
            'label' => 'Projects', 'item' => 'Project', 'max' => 15, 'fields' => [
                'title'        => ['label' => 'Project title', 'type' => 'text', 'required' => true, 'max' => 150],
                'description'  => ['label' => 'Description', 'type' => 'textarea', 'required' => true, 'max' => 1500],
                'technologies' => ['label' => 'Technologies / tools used', 'type' => 'text', 'required' => true, 'max' => 255],
                'contribution' => ['label' => 'Your contribution', 'type' => 'textarea', 'required' => true, 'max' => 1000],
                'url'          => ['label' => 'Link (repository / demo)', 'type' => 'url', 'max' => 255],
            ],
        ],
        'internship' => [
            'label' => 'Internships & practical experience', 'item' => 'Internship', 'max' => 10, 'fields' => [
                'organization' => ['label' => 'Organization', 'type' => 'text', 'required' => true, 'max' => 150],
                'role'         => ['label' => 'Role', 'type' => 'text', 'required' => true, 'max' => 150],
                'from'         => ['label' => 'From', 'type' => 'date'],
                'to'           => ['label' => 'To', 'type' => 'date'],
                'description'  => ['label' => 'What you worked on', 'type' => 'textarea', 'required' => true, 'max' => 1500],
                'skills'       => ['label' => 'Skills / tools used', 'type' => 'text', 'max' => 255],
            ],
        ],
        'experience' => [
            'label' => 'Employment history', 'item' => 'Employer', 'max' => 15, 'fields' => [
                'employer'         => ['label' => 'Previous employer', 'type' => 'text', 'required' => true, 'max' => 150],
                'designation'      => ['label' => 'Designation', 'type' => 'text', 'required' => true, 'max' => 150],
                'from'             => ['label' => 'From', 'type' => 'date', 'required' => true],
                'to'               => ['label' => 'To', 'type' => 'date'],
                'current'          => ['label' => 'I currently work here', 'type' => 'check'],
                'responsibilities' => ['label' => 'Key responsibilities', 'type' => 'textarea', 'required' => true, 'max' => 2000],
                'achievements'     => ['label' => 'Relevant achievements', 'type' => 'textarea', 'max' => 1500],
                'skills'           => ['label' => 'Skills / tools used', 'type' => 'text', 'max' => 255],
                'reason_for_leaving' => ['label' => 'Reason for leaving', 'type' => 'text', 'max' => 255],
            ],
        ],
    ];

    /** Used only if no 'general' group exists at all (e.g. HR deleted every copy). */
    private const FALLBACK_QUESTIONS = [
        ['id' => null, 'question_key' => 'q_general_1', 'label' => 'Key skills for this role', 'field_type' => 'tags', 'options' => null,
         'help_text' => 'List the skills you would bring to this position.', 'is_required' => 1, 'applies_to' => 'all', 'sort_order' => 10],
        ['id' => null, 'question_key' => 'q_general_2', 'label' => 'Relevant responsibilities or examples of previous work', 'field_type' => 'textarea', 'options' => null,
         'help_text' => null, 'is_required' => 0, 'applies_to' => 'all', 'sort_order' => 20],
    ];

    protected BaseConnection $db;
    protected OnboardingRecordModel $recordModel;
    protected RoleGroupModel $groupModel;
    protected QuestionModel $questionModel;
    protected EvalCriterionModel $criterionModel;

    public function __construct()
    {
        $this->db             = db_connect();
        $this->recordModel    = new OnboardingRecordModel();
        $this->groupModel     = new RoleGroupModel();
        $this->questionModel  = new QuestionModel();
        $this->criterionModel = new EvalCriterionModel();
    }

    // ------------------------------------------------------------------
    // Role group resolution
    // ------------------------------------------------------------------

    /**
     * The role group (with 'feature_list' and 'questions') for a record.
     * Once a candidate has submitted, the group stored on their profile is
     * kept (so the HR page matches what they answered); before that it is
     * re-resolved on every load so HR fixing a wrong job title is picked up.
     */
    public function groupForRecord(array $record, ?array $profile = null): array
    {
        if ($profile !== null && ! empty($profile['submitted_at']) && ! empty($profile['role_group_id'])) {
            $stored = $this->groupModel->find((int) $profile['role_group_id']);
            if ($stored !== null) {
                return $this->hydrateGroup($stored);
            }
        }

        return $this->hydrateGroup($this->roleGroupFor($record));
    }

    public function roleGroupFor(array $record): array
    {
        $companyId = (int) $record['company_id'];

        // A company's own group overrides the shared default with the same slug.
        $bySlug = [];
        foreach ($this->groupModel->visibleTo($companyId, true) as $group) {
            if (! isset($bySlug[$group['slug']]) || $group['company_id'] !== null) {
                $bySlug[$group['slug']] = $group;
            }
        }

        $designation = mb_strtolower((string) ($record['designation'] ?? ''));
        $department  = mb_strtolower((string) ($record['department_name'] ?? ''));
        $description = mb_strtolower((string) ($record['job_description'] ?? ''));

        $best      = null;
        $bestScore = 0;
        foreach ($bySlug as $slug => $group) {
            if ($slug === 'general') {
                continue;
            }
            $score = 0;
            foreach ($this->keywordList((string) $group['keywords']) as $keyword) {
                $score += $this->mentions($designation, $keyword) ? 3 : 0;
                $score += $this->mentions($department, $keyword) ? 1 : 0;
                $score += $this->mentions($description, $keyword) ? 1 : 0;
            }
            if ($score === 0) {
                continue;
            }
            // Higher score wins; on a tie the lower priority number (more specific group) wins.
            if ($best === null || $score > $bestScore || ($score === $bestScore && (int) $group['priority'] < (int) $best['priority'])) {
                $best      = $group;
                $bestScore = $score;
            }
        }

        if ($best !== null) {
            return $best;
        }

        return $bySlug['general'] ?? [
            'id' => null, 'company_id' => null, 'name' => 'General', 'slug' => 'general', 'description' => null,
            'keywords' => null, 'features' => '', 'priority' => 900, 'is_active' => 1,
        ];
    }

    /** @return string[] */
    private function keywordList(string $keywords): array
    {
        $list = preg_split('/[,\n]+/', mb_strtolower($keywords)) ?: [];

        return array_values(array_filter(array_map('trim', $list), static fn (string $k) => $k !== ''));
    }

    private function mentions(string $haystack, string $keyword): bool
    {
        if ($haystack === '') {
            return false;
        }

        return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($keyword, '/') . '(?![a-z0-9])/u', $haystack);
    }

    private function hydrateGroup(array $group): array
    {
        $group['feature_list'] = array_values(array_filter(array_map('trim', explode(',', (string) ($group['features'] ?? '')))));
        $group['questions']    = $group['id'] !== null ? $this->questionModel->forGroup((int) $group['id'], true) : self::FALLBACK_QUESTIONS;

        return $group;
    }

    public function criteriaFor(array $group): array
    {
        return $group['id'] !== null ? $this->criterionModel->forGroup((int) $group['id'], true) : [];
    }

    /** Questions that apply to a candidate type (all of them while the type is still unknown). */
    public function questionsFor(array $group, ?string $type): array
    {
        return array_values(array_filter(
            $group['questions'],
            static fn (array $q) => $type === null || $q['applies_to'] === 'all' || $q['applies_to'] === $type
        ));
    }

    // ------------------------------------------------------------------
    // Loading what is stored
    // ------------------------------------------------------------------

    public function profile(int $recordId): ?array
    {
        return $this->db->table('onboarding_candidate_profiles')->where('onboarding_record_id', $recordId)->get()->getRowArray();
    }

    /** @return array<string, list<array<string,mixed>>> */
    public function entries(int $recordId): array
    {
        $out = array_fill_keys(array_keys(self::SECTIONS), []);
        $rows = $this->db->table('onboarding_candidate_entries')
            ->where('onboarding_record_id', $recordId)
            ->orderBy('section')->orderBy('sort_order')->orderBy('id')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $data = json_decode((string) $row['data'], true);
            if (is_array($data) && isset($out[$row['section']])) {
                $out[$row['section']][] = $data;
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function answers(int $recordId): array
    {
        return $this->db->table('onboarding_candidate_answers')
            ->where('onboarding_record_id', $recordId)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->getResultArray();
    }

    /** Everything stored for a record, in one array (profile may be null for legacy records). */
    public function load(int $recordId): array
    {
        return [
            'profile' => $this->profile($recordId),
            'entries' => $this->entries($recordId),
            'answers' => $this->answers($recordId),
        ];
    }

    /** Resume / supporting documents of a record, newest first. */
    public function documents(int $recordId): array
    {
        return $this->db->table('documents')
            ->where('onboarding_record_id', $recordId)
            ->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')
            ->get()->getResultArray();
    }

    public function hasResume(int $recordId): bool
    {
        return $this->db->table('documents')
            ->where('onboarding_record_id', $recordId)->where('document_type', 'Resume')
            ->countAllResults() > 0;
    }

    /**
     * The values the form should show: what was just posted (after a
     * validation failure) wins, otherwise what is stored, otherwise the
     * legacy columns on the record.
     *
     * @param array|null $old  the previous POST (session old input) or null
     */
    public function formValues(array $record, array $stored, ?array $old): array
    {
        $profile = $stored['profile'] ?? [];
        $scalar  = static fn (string $key, mixed $fallback = '') => $old !== null && array_key_exists($key, $old) && ! is_array($old[$key])
            ? (string) $old[$key]
            : (string) ($fallback ?? '');

        $values = [
            'candidate_type'  => $scalar('candidate_type', $profile['candidate_type'] ?? ''),
            'candidate_phone' => $scalar('candidate_phone', $record['candidate_phone'] ?? ''),
            'date_of_birth'   => $scalar('date_of_birth', $record['date_of_birth'] ?? ''),
            'address_line1'   => $scalar('address_line1', $record['address_line1'] ?? ''),
            'address_line2'   => $scalar('address_line2', $record['address_line2'] ?? ''),
            'city'            => $scalar('city', $record['city'] ?? ''),
            'state'           => $scalar('state', $record['state'] ?? ''),
            'postal_code'     => $scalar('postal_code', $record['postal_code'] ?? ''),
            'country'         => $scalar('country', $record['country'] ?? ''),
            'permanent_same_as_current' => $old !== null ? (! empty($old['permanent_same_as_current']) ? '1' : '') : (! empty($profile['permanent_same_as_current']) ? '1' : ''),
            'permanent_address' => $scalar('permanent_address', $profile['permanent_address'] ?? ''),
            'github_url'      => $scalar('github_url', $profile['github_url'] ?? ''),
            'portfolio_url'   => $scalar('portfolio_url', $profile['portfolio_url'] ?? ''),
            'linkedin_url'    => $scalar('linkedin_url', $profile['linkedin_url'] ?? ''),
            'notice_period'   => $scalar('notice_period', $profile['notice_period'] ?? ''),
            'profile_summary' => $scalar('profile_summary', $profile['profile_summary'] ?? ''),
            'notes'           => $scalar('notes', $profile['candidate_note'] ?? ''),
            'entries'         => [],
            'answers'         => [],
        ];

        foreach (array_keys(self::SECTIONS) as $section) {
            $rows = $old !== null && isset($old[$section]) && is_array($old[$section]) ? array_values($old[$section]) : ($stored['entries'][$section] ?? []);
            $values['entries'][$section] = array_values(array_filter($rows, 'is_array'));
        }

        if ($old !== null && isset($old['answers']) && is_array($old['answers'])) {
            foreach ($old['answers'] as $key => $answer) {
                $values['answers'][(string) $key] = is_array($answer) ? '' : (string) $answer;
            }
        } else {
            foreach ($stored['answers'] as $answer) {
                $values['answers'][$answer['question_key']] = (string) $answer['answer'];
            }
        }

        // The legacy single-value qualification fields seed the first education row for
        // records that were filled in before this form existed.
        if ($values['entries']['education'] === [] && ! empty($record['highest_qualification'])) {
            $values['entries']['education'][] = [
                'degree' => (string) $record['highest_qualification'], 'institution' => (string) ($record['institution'] ?? ''),
                'year' => (string) ($record['graduation_year'] ?? ''),
            ];
        }

        return $values;
    }

    // ------------------------------------------------------------------
    // Validation / normalisation
    // ------------------------------------------------------------------

    /**
     * Turns a raw POST into clean values plus errors keyed by field path
     * (e.g. 'education.0.year', 'answers.q_x_1', 'candidate_phone').
     * $strict adds the "must be filled in" checks (final submission);
     * format/length checks always apply so nothing malformed is stored.
     *
     * @return array{clean: array, errors: array<string,string>}
     */
    public function extract(array $post, array $record, array $group, bool $strict, bool $hasResume): array
    {
        $errors = [];
        $clean  = ['entries' => [], 'answers' => []];

        $type = $this->str($post['candidate_type'] ?? '', 20);
        if ($type !== '' && ! isset(self::TYPES[$type])) {
            $errors['candidate_type'] = 'Choose Fresher or Experienced.';
            $type = '';
        }
        if ($strict && $type === '') {
            $errors['candidate_type'] ??= 'Choose whether you are a fresher or experienced.';
        }
        $clean['candidate_type'] = $type !== '' ? $type : null;

        // --- Personal & contact -------------------------------------------------
        $phone = $this->str($post['candidate_phone'] ?? '', 20);
        if ($phone !== '' && ! preg_match('/^[0-9]{10}$/', $phone)) {
            $errors['candidate_phone'] = 'Phone number must be exactly 10 digits.';
        } elseif ($strict && $phone === '') {
            $errors['candidate_phone'] = 'Enter your contact number.';
        }
        $clean['candidate_phone'] = $phone;

        $dob = $this->str($post['date_of_birth'] ?? '', 10);
        if ($dob !== '') {
            $stamp = \DateTime::createFromFormat('Y-m-d', $dob);
            if ($stamp === false || $stamp->format('Y-m-d') !== $dob) {
                $errors['date_of_birth'] = 'Enter a valid date of birth.';
            } elseif ($dob > date('Y-m-d', strtotime('-16 years')) || $dob < '1940-01-01') {
                $errors['date_of_birth'] = 'Date of birth is outside the acceptable range.';
            }
        }
        $clean['date_of_birth'] = $dob !== '' && ! isset($errors['date_of_birth']) ? $dob : null;

        foreach (['address_line1' => 190, 'address_line2' => 190, 'city' => 100, 'state' => 100, 'postal_code' => 20, 'country' => 100] as $field => $max) {
            $clean[$field] = $this->str($post[$field] ?? '', $max, $field, $errors);
        }
        if ($strict) {
            foreach (['address_line1' => 'Enter your current address.', 'city' => 'Enter your city.'] as $field => $message) {
                if ($clean[$field] === '') {
                    $errors[$field] = $message;
                }
            }
        }

        $same = ! empty($post['permanent_same_as_current']);
        $clean['permanent_same_as_current'] = $same ? 1 : 0;
        $clean['permanent_address'] = $same ? null : ($this->str($post['permanent_address'] ?? '', 500, 'permanent_address', $errors) ?: null);
        if ($strict && ! $same && $clean['permanent_address'] === null) {
            $errors['permanent_address'] = 'Enter your permanent address or tick "same as current address".';
        }

        // --- Links, summary, notice period -----------------------------------------
        $features = $group['feature_list'] ?? [];
        $clean['linkedin_url'] = $this->url($post['linkedin_url'] ?? '', 'linkedin_url', $errors);
        foreach (['github_url', 'portfolio_url'] as $field) {
            $clean[$field] = in_array('links', $features, true) ? $this->url($post[$field] ?? '', $field, $errors) : null;
        }
        $clean['profile_summary'] = $this->str($post['profile_summary'] ?? '', 1500, 'profile_summary', $errors) ?: null;
        $clean['notice_period']   = $type === 'experienced' ? ($this->str($post['notice_period'] ?? '', 60, 'notice_period', $errors) ?: null) : null;
        $clean['notes']           = $this->str($post['notes'] ?? '', 2000, 'notes', $errors) ?: null;

        // --- Repeatable sections ----------------------------------------------------
        foreach (self::SECTIONS as $section => $def) {
            $enabled = match ($section) {
                'education', 'certification' => true,
                'internship'                  => $type !== 'experienced',
                'experience'                  => $type === 'experienced',
                'project'                     => $type !== 'experienced' || in_array('projects', $features, true),
            };
            $rows = $enabled ? $this->rows($section, $post[$section] ?? [], $strict, $errors) : [];
            $clean['entries'][$section] = $rows;
        }
        if ($strict) {
            if ($clean['entries']['education'] === []) {
                $errors['education'] = 'Add at least one education record.';
            }
            if ($type === 'experienced' && $clean['entries']['experience'] === []) {
                $errors['experience'] = 'Add your previous employment, or switch to "Fresher" if you have none.';
            }
        }

        // --- Role-specific answers ----------------------------------------------------
        $rawAnswers = is_array($post['answers'] ?? null) ? $post['answers'] : [];
        foreach ($this->questionsFor($group, $type !== '' ? $type : null) as $i => $question) {
            $key    = $question['question_key'];
            $answer = $this->answer($question, $rawAnswers[$key] ?? '', $errors);
            if ($strict && $question['is_required'] && $answer === '' && ! isset($errors["answers.$key"])) {
                $errors["answers.$key"] = 'This question is required.';
            }
            $clean['answers'][$key] = ['question' => $question, 'answer' => $answer, 'sort' => $i];
        }

        // --- Documents & declaration (final submission only) ---------------------------------
        if ($strict) {
            if (! $hasResume) {
                $errors['documents'] = 'Upload your resume before submitting.';
            }
            if (empty($post['confirm_accuracy'])) {
                $errors['confirm_accuracy'] = 'Please confirm that the information is accurate.';
            }
        }

        return ['clean' => $clean, 'errors' => $errors];
    }

    /** Sections shown in the error summary, in form order. */
    public static function errorSection(string $path): string
    {
        $head = explode('.', $path)[0];

        return match (true) {
            in_array($head, ['candidate_type'], true) => 'Application',
            in_array($head, ['candidate_phone', 'date_of_birth', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country', 'permanent_address'], true) => 'Personal & contact',
            in_array($head, ['education', 'certification'], true) => 'Education',
            in_array($head, ['answers', 'github_url', 'portfolio_url', 'linkedin_url', 'profile_summary'], true) => 'Skills & assessment',
            in_array($head, ['experience', 'internship', 'project', 'notice_period'], true) => 'Experience & projects',
            $head === 'documents' => 'Documents',
            default => 'Review & submit',
        };
    }

    /** @param array<string,string> $errors */
    private function rows(string $section, mixed $raw, bool $strict, array &$errors): array
    {
        $def  = self::SECTIONS[$section];
        $raw  = is_array($raw) ? array_values($raw) : [];
        $out  = [];

        if (count($raw) > $def['max']) {
            $errors[$section] = 'You can add at most ' . $def['max'] . ' ' . mb_strtolower($def['label']) . ' entries.';
            $raw = array_slice($raw, 0, $def['max']);
        }

        foreach ($raw as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            $any   = false;
            foreach ($def['fields'] as $field => $spec) {
                $path  = "$section.$i.$field";
                $value = $row[$field] ?? '';
                $clean[$field] = match ($spec['type']) {
                    'year'   => $this->year($value, $path, $errors),
                    'date'   => $this->date($value, $path, $errors),
                    'url'    => $this->url($value, $path, $errors) ?? '',
                    'check'  => ! empty($value) ? '1' : '',
                    'select' => $this->choice($value, $spec['options'] ?? [], $path, $errors),
                    default  => $this->str($value, $spec['max'] ?? 255, $path, $errors),
                };
                if ($clean[$field] !== '' && $spec['type'] !== 'select' && $spec['type'] !== 'check') {
                    $any = true;
                }
            }
            if (! $any) {
                continue; // a completely empty row is just dropped
            }
            if (($clean['current'] ?? '') === '1') {
                $clean['to'] = '';
            }
            foreach ($def['fields'] as $field => $spec) {
                if ($strict && ! empty($spec['required']) && $clean[$field] === '') {
                    $errors["$section.$i.$field"] = $spec['label'] . ' is required.';
                }
            }
            if (isset($clean['from'], $clean['to'])) {
                if ($clean['from'] !== '' && $clean['to'] !== '' && $clean['to'] < $clean['from']) {
                    $errors["$section.$i.to"] = '"To" cannot be before "From".';
                }
                if ($strict && $section === 'experience' && ($clean['current'] ?? '') !== '1' && $clean['to'] === '' && ! isset($errors["$section.$i.to"])) {
                    $errors["$section.$i.to"] = 'Enter the end date, or tick "I currently work here".';
                }
            }
            $out[] = $clean;
        }

        return $out;
    }

    private function answer(array $question, mixed $raw, array &$errors): string
    {
        $path = 'answers.' . $question['question_key'];
        $type = $question['field_type'];

        if (is_array($raw)) {
            return '';
        }

        switch ($type) {
            case 'textarea':
                return $this->str($raw, 2000, $path, $errors);
            case 'url':
                return $this->url($raw, $path, $errors) ?? '';
            case 'number':
                $value = $this->str($raw, 20, $path, $errors);
                if ($value !== '' && (! is_numeric($value) || (float) $value < 0 || (float) $value > 1000000000)) {
                    $errors[$path] = 'Enter a valid number.';

                    return '';
                }

                return $value;
            case 'yes_no':
                $value = $this->str($raw, 3, $path, $errors);

                return in_array($value, ['Yes', 'No'], true) ? $value : '';
            case 'select':
                $options = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $question['options']) ?: [])));

                return $this->choice($raw, $options, $path, $errors);
            case 'tags':
                $items = preg_split('/[,\n]+/', (string) $raw) ?: [];
                $items = array_values(array_unique(array_filter(array_map(fn ($t) => $this->str($t, 80), $items), static fn (string $t) => $t !== '')));
                if (count($items) > 30) {
                    $errors[$path] = 'List at most 30 items.';
                    $items = array_slice($items, 0, 30);
                }
                foreach ($items as $item) {
                    if (mb_strlen($item) > 60) {
                        $errors[$path] = 'Each item must be at most 60 characters.';
                        break;
                    }
                }

                return implode(', ', $items);
            default:
                return $this->str($raw, 255, $path, $errors);
        }
    }

    /** Trimmed scalar string with control characters removed; flags an error (instead of truncating) when too long. */
    private function str(mixed $value, int $max, ?string $path = null, ?array &$errors = null): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);
        // Only control characters (Cc) are stripped; format characters such as ZWJ/ZWNJ are legitimate in Indic scripts and emoji.
        $text = preg_replace('/[^\P{Cc}\n\t]+/u', '', $text) ?? '';
        $text = trim($text);

        if (mb_strlen($text) > $max) {
            if ($path !== null && $errors !== null) {
                $errors[$path] = 'Must be at most ' . $max . ' characters.';
            }

            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    private function year(mixed $value, string $path, array &$errors): string
    {
        $text = $this->str($value, 4);
        if ($text === '') {
            return '';
        }
        if (! preg_match('/^[0-9]{4}$/', $text) || (int) $text < 1950 || (int) $text > (int) date('Y') + 8) {
            $errors[$path] = 'Enter a valid 4-digit year.';

            return '';
        }

        return $text;
    }

    private function date(mixed $value, string $path, array &$errors): string
    {
        $text = $this->str($value, 10);
        if ($text === '') {
            return '';
        }
        $stamp = \DateTime::createFromFormat('Y-m-d', $text);
        if ($stamp === false || $stamp->format('Y-m-d') !== $text || $text < '1950-01-01' || $text > date('Y-m-d')) {
            $errors[$path] = 'Enter a valid date that is not in the future.';

            return '';
        }

        return $text;
    }

    private function url(mixed $value, string $path, array &$errors): ?string
    {
        $text = $this->str($value, 255, $path, $errors);
        if ($text === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($text, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true) || filter_var($text, FILTER_VALIDATE_URL) === false) {
            $errors[$path] = 'Enter a full web link starting with http:// or https://.';

            return null;
        }

        return $text;
    }

    private function choice(mixed $value, array $options, string $path, array &$errors): string
    {
        $text = $this->str($value, 120);
        if ($text === '') {
            return '';
        }
        if (! in_array($text, $options, true)) {
            $errors[$path] = 'Choose one of the listed options.';

            return '';
        }

        return $text;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    public const SAVE_OK     = 'ok';
    public const SAVE_LOCKED = 'locked';
    public const SAVE_FAILED = 'failed';

    /**
     * Writes clean values from extract(). Runs in one transaction with the
     * onboarding row locked, so two concurrent submits (double click,
     * second tab) cannot both pass the "still editable" check.
     *
     * @return string one of the SAVE_* constants
     */
    public function persist(int $recordId, array $group, array $clean, bool $submit): string
    {
        $now = date('Y-m-d H:i:s');
        $db  = $this->db;

        $db->transStart();

        $locked = $db->query('SELECT status, verification_status FROM onboarding_records WHERE id = ? FOR UPDATE', [$recordId])->getRowArray();
        if ($locked === null || $locked['status'] !== 'link_sent' || ! in_array($locked['verification_status'], [null, 'pending', 'correction_needed'], true)) {
            $db->transComplete();

            return self::SAVE_LOCKED;
        }

        // Legacy single-value columns (read by HR screens and employee creation).
        $first = $clean['entries']['education'][0] ?? null;
        $update = [
            'date_of_birth' => $clean['date_of_birth'],
            'address_line1' => $clean['address_line1'] ?: null,
            'address_line2' => $clean['address_line2'] ?: null,
            'city'          => $clean['city'] ?: null,
            'state'         => $clean['state'] ?: null,
            'postal_code'   => $clean['postal_code'] ?: null,
            'country'       => $clean['country'] ?: null,
        ];
        if ($clean['candidate_phone'] !== '') {
            $update['candidate_phone'] = $clean['candidate_phone'];
        }
        if ($first !== null) {
            $update['highest_qualification'] = $first['degree'] !== '' ? $first['degree'] : null;
            $update['institution']           = $first['institution'] !== '' ? $first['institution'] : null;
            $update['graduation_year']       = $first['year'] !== '' ? (int) $first['year'] : null;
        }
        if ($submit) {
            // The candidate's note to HR replaces HR's correction message only now, on
            // submission - saving a draft must not wipe what HR asked them to fix.
            $update['verification_notes']        = $clean['notes'];
            $update['verification_status']       = 'submitted';
            $update['verification_submitted_at'] = $now;
            $update['status']                    = 'details_submitted';
        }
        $this->recordModel->skipValidation(true)->update($recordId, $update);

        // Profile (one row per record).
        $existing = $this->profile($recordId);
        $profile  = [
            'candidate_type' => $clean['candidate_type'], 'role_group_id' => $group['id'], 'role_group_name' => $group['name'],
            'permanent_address' => $clean['permanent_address'], 'permanent_same_as_current' => $clean['permanent_same_as_current'],
            'github_url' => $clean['github_url'], 'portfolio_url' => $clean['portfolio_url'], 'linkedin_url' => $clean['linkedin_url'],
            'notice_period' => $clean['notice_period'], 'profile_summary' => $clean['profile_summary'], 'candidate_note' => $clean['notes'],
            'last_saved_at' => $now, 'updated_at' => $now,
        ];
        if ($submit) {
            $profile['submitted_at'] = $now;
            $profile['revision']     = (int) ($existing['revision'] ?? 0) + 1;
        }
        if ($existing === null) {
            $db->table('onboarding_candidate_profiles')->insert($profile + ['onboarding_record_id' => $recordId, 'created_at' => $now]);
        } else {
            $db->table('onboarding_candidate_profiles')->where('id', $existing['id'])->update($profile);
        }

        // Entries: replaced wholesale (the form always posts the full list per section).
        $db->table('onboarding_candidate_entries')->where('onboarding_record_id', $recordId)->delete();
        foreach ($clean['entries'] as $section => $rows) {
            foreach ($rows as $position => $row) {
                $db->table('onboarding_candidate_entries')->insert([
                    'onboarding_record_id' => $recordId, 'section' => $section, 'sort_order' => $position,
                    'data' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Answers: keep exactly the questions that apply now, with their label snapshotted.
        $db->table('onboarding_candidate_answers')->where('onboarding_record_id', $recordId)->delete();
        foreach ($clean['answers'] as $key => $item) {
            if ($item['answer'] === '') {
                continue;
            }
            $db->table('onboarding_candidate_answers')->insert([
                'onboarding_record_id' => $recordId, 'question_id' => $item['question']['id'] ?? null, 'question_key' => $key,
                'question_label' => $item['question']['label'], 'field_type' => $item['question']['field_type'],
                'answer' => $item['answer'], 'sort_order' => $item['sort'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $db->transComplete();

        return $db->transStatus() ? self::SAVE_OK : self::SAVE_FAILED;
    }

    // ------------------------------------------------------------------
    // Uploads
    // ------------------------------------------------------------------

    /** Returns an error message, or null when the file may be stored as $documentType. */
    public function uploadError(?\CodeIgniter\HTTP\Files\UploadedFile $file, string $documentType): ?string
    {
        if (! isset(self::DOCUMENT_TYPES[$documentType])) {
            return 'Choose a valid document type.';
        }
        $formats = self::DOCUMENT_TYPES[$documentType]['formats'];
        $allowed = strtoupper(implode(', ', array_unique(array_map(static fn (string $f) => $f === 'jpeg' ? 'jpg' : $f, $formats))));

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return 'Choose a file to upload.';
        }
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            return 'The file is too large (maximum 10 MB).';
        }
        if (! $file->isValid()) {
            return 'The upload failed. Please try again.';
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return 'The file is too large (maximum 10 MB).';
        }
        if ($file->getSize() < 1) {
            return 'The file is empty.';
        }

        $extension = strtolower($file->getClientExtension());
        if (! in_array($extension, $formats, true)) {
            return "This document type accepts $allowed files only.";
        }

        // The type is judged from the file's content, never the browser-supplied header.
        $mime = $file->getMimeType();
        if (! in_array($mime, self::MIME_BY_EXT[$extension] ?? [], true)) {
            return "The file does not look like a real $allowed document.";
        }
        $path = $file->getRealPath() ?: $file->getPathname();
        $head = is_file($path) ? (string) file_get_contents($path, false, null, 0, 8) : '';
        if ($extension === 'pdf' && ! str_starts_with($head, '%PDF')) {
            return 'The file is not a valid PDF.';
        }
        if ($extension === 'png' && ! str_starts_with($head, "\x89PNG")) {
            return 'The file is not a valid PNG image.';
        }
        if (in_array($extension, ['jpg', 'jpeg'], true) && ! str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'The file is not a valid JPG image.';
        }
        if ($extension === 'docx') {
            if (! str_starts_with($head, 'PK') || (class_exists(\ZipArchive::class) && ! $this->isWordDocument($path))) {
                return 'The file is not a valid Word (.docx) document.';
            }
        }

        return null;
    }

    private function isWordDocument(string $path): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $ok;
    }

    // ------------------------------------------------------------------
    // HR-side helpers
    // ------------------------------------------------------------------

    /**
     * Plain-language list of what is missing or thin in a submission, for
     * the HR details page ("Concerns or missing information").
     *
     * @return list<string>
     */
    public function missingInformation(array $record, array $stored, array $group, array $documents): array
    {
        $missing = [];
        $profile = $stored['profile'];
        $type    = $profile['candidate_type'] ?? null;

        if ($profile === null) {
            $missing[] = 'This candidate submitted before the detailed form was introduced, so only basic details are on file.';
        }
        if (empty($record['candidate_phone'])) {
            $missing[] = 'Contact number';
        }
        if (empty($record['address_line1'])) {
            $missing[] = 'Current address';
        }
        if (empty($record['date_of_birth'])) {
            $missing[] = 'Date of birth (optional on the form)';
        }
        if ($profile !== null && $stored['entries']['education'] === []) {
            $missing[] = 'Education records';
        }
        if ($type === 'experienced' && $stored['entries']['experience'] === []) {
            $missing[] = 'Employment history (candidate is marked experienced)';
        }
        if ($type === 'fresher' && $stored['entries']['project'] === [] && $stored['entries']['internship'] === [] && in_array('projects', $group['feature_list'] ?? [], true)) {
            $missing[] = 'No projects or internships listed for a role where practical work matters';
        }
        $answered = array_column($stored['answers'], 'question_key');
        foreach ($this->questionsFor($group, $type) as $question) {
            if ($question['is_required'] && ! in_array($question['question_key'], $answered, true) && $profile !== null) {
                $missing[] = 'Answer to "' . $question['label'] . '"';
            }
        }
        if (! in_array('Resume', array_column($documents, 'document_type'), true)) {
            $missing[] = 'Resume';
        }

        return $missing;
    }

    /** Ready-to-use document reference shown to the candidate and HR. */
    public static function reference(array $record): string
    {
        return 'ONB-' . str_pad((string) $record['id'], 6, '0', STR_PAD_LEFT);
    }
}
