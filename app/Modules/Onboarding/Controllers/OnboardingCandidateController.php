<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Onboarding\Models\EvaluationModel;
use App\Modules\Onboarding\Models\OnboardingRecordModel;
use App\Modules\Onboarding\Services\CandidateFormService;

/**
 * HR's view of what a candidate submitted through the portal, plus the
 * structured evaluation HR records before deciding. The decision itself
 * (approve / request changes / reject) is still
 * OnboardingController::reviewVerification() - this controller only shows
 * the data and stores the assessment, so there is one place that moves a
 * record between statuses.
 *
 * Personal data and resumes are visible to HR / Super Admin only (the
 * same OWNER_ROLES rule the rest of the pipeline uses), always within the
 * viewer's company.
 */
class OnboardingCandidateController extends BaseController
{
    private const OWNER_ROLES = ['hr', 'super_admin'];

    private const TEXT_LIMITS = [
        'technical_assessment'  => 4000,
        'experience_assessment' => 4000,
        'strengths'             => 3000,
        'concerns'              => 3000,
        'hr_comments'           => 3000,
        'decision_notes'        => 3000,
    ];

    protected OnboardingRecordModel $recordModel;
    protected EvaluationModel $evaluationModel;
    protected CandidateFormService $service;

    public function __construct()
    {
        $this->recordModel     = new OnboardingRecordModel();
        $this->evaluationModel = new EvaluationModel();
        $this->service         = new CandidateFormService();
    }

    public function show(int $id)
    {
        $record = $this->authorisedRecord($id);
        if (! is_array($record)) {
            return $record;
        }

        $stored    = $this->service->load($id);
        $group     = $this->service->groupForRecord($record, $stored['profile']);
        $documents = $this->service->documents($id);

        return view('App\Modules\Onboarding\candidate_details', [
            'title'       => 'Candidate Details — ' . $record['candidate_name'],
            'navActive'   => 'hr-onboarding',
            'record'      => $record,
            'reference'   => CandidateFormService::reference($record),
            'stored'      => $stored,
            'group'       => $group,
            'documents'   => $documents,
            'missing'     => $this->service->missingInformation($record, $stored, $group, $documents),
            'evaluation'  => $this->evaluationModel->forRecord($id),
            'hasAssessment' => $this->evaluationModel->hasAssessment($id),
            'criteria'    => $this->service->criteriaFor($group),
            'hasSubmission' => $this->hasSubmission($record, $stored['profile']),
            'canDecide'   => $record['status'] === 'hr_review',
            'stageLabels' => OnboardingRecordModel::STAGE_LABELS,
            'progressSteps' => OnboardingRecordModel::PROGRESS_STEPS,
            'progressIndex' => OnboardingRecordModel::progressIndexFor($record['status']),
        ]);
    }

    /**
     * Saves (creates or updates) HR's evaluation. Scores are optional,
     * 1-5 per configured criterion, and never drive a decision on their
     * own - nothing here changes the record's status.
     */
    public function saveEvaluation(int $id)
    {
        $record = $this->authorisedRecord($id);
        if (! is_array($record)) {
            return $record;
        }

        $back    = '/hr/onboarding/' . $id . '/candidate-details';
        $profile = $this->service->profile($id);

        if (! $this->hasSubmission($record, $profile)) {
            return redirect()->to($back)->with('error', 'There is no submission to evaluate yet.');
        }

        $data   = [];
        $errors = [];
        foreach (self::TEXT_LIMITS as $field => $max) {
            $text = trim(str_replace("\r\n", "\n", (string) $this->request->getPost($field)));
            if (mb_strlen($text) > $max) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be at most ' . $max . ' characters.';
            }
            $data[$field] = $text !== '' ? $text : null;
        }

        $group    = $this->service->groupForRecord($record, $profile);
        $scores   = (array) $this->request->getPost('score');
        $notes    = (array) $this->request->getPost('score_note');
        $criteria = [];
        foreach ($this->service->criteriaFor($group) as $criterion) {
            $cid   = (string) $criterion['id'];
            $score = $scores[$cid] ?? '';
            $note  = trim((string) ($notes[$cid] ?? ''));
            if (is_array($score) || ($score !== '' && (! ctype_digit((string) $score) || (int) $score < 1 || (int) $score > 5))) {
                $errors[] = 'Scores must be between 1 and 5.';
                continue;
            }
            if (mb_strlen($note) > 300) {
                $errors[] = 'Criterion notes must be at most 300 characters.';
                continue;
            }
            if ($score !== '' || $note !== '') {
                $criteria[] = ['label' => $criterion['label'], 'score' => $score === '' ? null : (int) $score, 'note' => $note];
            }
        }

        if ($errors !== []) {
            return redirect()->to($back)->withInput()->with('errors', array_values(array_unique($errors)));
        }

        $existing = $this->evaluationModel->where('onboarding_record_id', $id)->first();
        $row      = $data + [
            'evaluator_id'    => $this->currentUserId(),
            'role_group_name' => $group['name'],
            'criteria_scores' => $criteria === [] ? null : json_encode($criteria, JSON_UNESCAPED_UNICODE),
        ];

        if ($existing === null) {
            $this->evaluationModel->insert($row + ['onboarding_record_id' => $id]);
        } else {
            $this->evaluationModel->update($existing['id'], $row);
        }

        $this->logActivity('onboarding', 'update', $id, 'Saved HR evaluation for ' . $record['candidate_name']);

        return redirect()->to($back)->with('success', 'Evaluation saved.');
    }

    /** A record HR may open: exists, same company, and the viewer is HR / Super Admin. Otherwise a redirect response. */
    private function authorisedRecord(int $id): array|\CodeIgniter\HTTP\RedirectResponse
    {
        $record = $this->recordModel->withRelations($id);

        if ($record === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $record['company_id'])) {
            return redirect()->to('/hr/onboarding')->with('error', 'Onboarding record not found.');
        }
        if (! in_array(session('roleSlug'), self::OWNER_ROLES, true)) {
            return redirect()->to('/hr/onboarding/' . $id)->with('error', 'Only HR can view candidate details.');
        }

        return $record;
    }

    /** True once the candidate has submitted through the portal (new form or the original short one). */
    private function hasSubmission(array $record, ?array $profile): bool
    {
        return ! empty($profile['submitted_at']) || ! empty($record['verification_submitted_at']);
    }
}
