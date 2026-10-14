<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Document\Models\DocumentModel;
use App\Modules\Onboarding\Models\OnboardingRecordModel;
use App\Modules\Onboarding\Models\OnboardingTaskModel;
use App\Modules\Onboarding\Services\CandidateFormService;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The candidate-facing side of onboarding — no ERP login involved at all
 * (see OnboardingRecordModel's own docblock: most of the pipeline happens
 * before the person has any system login). Every action here is gated
 * purely by possession of a random, expiring candidate_token (see
 * record() below) — never by session/auth/permission, since none of
 * those exist for an anonymous candidate. Routes for this controller are
 * explicitly excluded from the global 'auth' filter in
 * app/Config/Filters.php, mirroring how MeetingController::join() already
 * handles an access-controlled-but-unauthenticated link.
 */
class OnboardingPortalController extends BaseController
{
    protected OnboardingRecordModel $recordModel;
    protected CompanyModel $companyModel;
    protected OnboardingTaskModel $taskModel;
    protected DocumentModel $documentModel;

    public function __construct()
    {
        $this->recordModel = new OnboardingRecordModel();
        $this->companyModel = new CompanyModel();
        $this->taskModel = new OnboardingTaskModel();
        $this->documentModel = new DocumentModel();
    }

    public function show(int $id, string $token)
    {
        $record = $this->record($id, $token);

        if ($record === null) {
            return $this->invalidLink();
        }

        $service = new CandidateFormService();
        $stored  = $service->load($id);
        $group   = $service->groupForRecord($record, $stored['profile']);
        $old     = session('_ci_old_input')['post'] ?? null;
        $errors  = session()->getFlashdata('form_errors') ?? [];

        return view('App\Modules\Onboarding\portal\show', [
            'title'         => 'Your Onboarding — ' . $record['candidate_name'],
            'record'        => $record,
            'token'         => $token,
            'documents'     => $service->documents($id),
            'editable'      => $this->isEditable($record),
            'reference'     => CandidateFormService::reference($record),
            'stored'        => $stored,
            'group'         => $group,
            'questions'     => $service->questionsFor($group, null),
            'values'        => $service->formValues($record, $stored, is_array($old) ? $old : null),
            'errors'        => is_array($errors) ? $errors : [],
            'startStep'     => (int) session()->getFlashdata('form_step'),
            'sections'      => CandidateFormService::SECTIONS,
            'types'         => CandidateFormService::TYPES,
            'documentTypes' => CandidateFormService::DOCUMENT_TYPES,
        ]);
    }

    /**
     * Final submission of the candidate form (STEP 3). Same route and
     * status transition as before (link_sent -> details_submitted,
     * verification_status 'submitted'), but with the full structured form
     * validated server-side. Re-submittable only after HR asked for a
     * correction (link_sent again); once submitted, a repeat POST is
     * refused - the record row is locked while the check and the write
     * happen, so a double click / second tab cannot submit twice.
     */
    public function submitVerification(int $id, string $token)
    {
        return $this->saveForm($id, $token, true);
    }

    /** Saves progress without submitting - same link, same validation of what was typed, no "required" checks. */
    public function saveDraft(int $id, string $token)
    {
        return $this->saveForm($id, $token, false);
    }

    private function saveForm(int $id, string $token, bool $submit)
    {
        $record = $this->record($id, $token);

        if ($record === null) {
            return $this->invalidLink();
        }

        $back = "/onboarding/portal/{$id}/{$token}";

        if (! $this->isEditable($record)) {
            return redirect()->to($back)->with('error', 'This has already been submitted for review.');
        }

        $service = new CandidateFormService();
        $group   = $service->groupForRecord($record, $service->profile($id));
        $result  = $service->extract((array) $this->request->getPost(), $record, $group, $submit, $service->hasResume($id));
        $step    = (int) $this->request->getPost('current_step');

        if ($result['errors'] !== []) {
            return redirect()->to($back)->withInput()
                ->with('form_errors', $result['errors'])
                ->with('form_step', $step)
                ->with('error', $submit
                    ? 'Please fix the highlighted items and submit again. Nothing has been submitted yet.'
                    : 'Some entries need attention before your progress can be saved.');
        }

        $outcome = $service->persist($id, $group, $result['clean'], $submit);

        if ($outcome === CandidateFormService::SAVE_LOCKED) {
            return redirect()->to($back)->with('error', 'This has already been submitted for review.');
        }
        if ($outcome !== CandidateFormService::SAVE_OK) {
            return redirect()->to($back)->withInput()->with('form_step', $step)->with('error', 'We could not save your details. Please try again.');
        }

        if ($submit) {
            $this->logActivity('onboarding', 'update', $id, 'Candidate submitted verification details for review');

            return redirect()->to($back)->with('success', 'Thanks — your information has been submitted for HR review.');
        }

        return redirect()->to($back)->with('form_step', $step)->with('success', 'Progress saved. You can return to this link any time before it expires to continue.');
    }

    /**
     * Resume / supporting document upload. Type, size and content are
     * checked on the server (CandidateFormService::uploadError()); the file
     * goes through the shared fileStorage service into a per-record folder
     * that only HR / Super Admin can download from (see FileController).
     * Answers JSON when called by the form's script, a redirect otherwise.
     */
    public function uploadDocument(int $id, string $token)
    {
        $record = $this->record($id, $token);
        if ($record === null) {
            return $this->wantsJson() ? $this->jsonError('This link is no longer valid.', 403) : $this->invalidLink();
        }
        if (! $this->isEditable($record)) {
            return $this->documentResult($id, $token, false, 'Documents can no longer be added at this stage.');
        }

        $service = new CandidateFormService();
        $type    = trim((string) $this->request->getPost('document_type'));
        $file    = $this->request->getFile('candidate_document');

        if (count($service->documents($id)) >= CandidateFormService::MAX_DOCUMENTS) {
            return $this->documentResult($id, $token, false, 'You have reached the limit of ' . CandidateFormService::MAX_DOCUMENTS . ' documents. Remove one to add another.');
        }
        if (($error = $service->uploadError($file, $type)) !== null) {
            return $this->documentResult($id, $token, false, $error);
        }

        $storage = service('fileStorage');
        $path    = $storage->store($file, CandidateFormService::STORAGE_FOLDER . '/' . $id);

        // A candidate has one resume: uploading a new one replaces the old.
        $replaced = [];
        if ($type === 'Resume') {
            $replaced = $this->documentModel->where(['onboarding_record_id' => $id, 'document_type' => 'Resume'])->findAll();
        }

        $this->documentModel->insert([
            'company_id'           => $record['company_id'],
            'category'             => 'hr',
            'document_type'        => $type,
            'title'                => $record['candidate_name'] . ' - ' . $type,
            'onboarding_record_id' => $id,
            'file_path'            => $path,
            'original_name'        => mb_substr(basename(str_replace('\\', '/', $file->getClientName())), 0, 190),
            'file_size'            => $file->getSize(),
            'uploaded_by'          => null,
        ]);

        foreach ($replaced as $old) {
            $storage->delete($old['file_path']);
            $this->documentModel->delete($old['id']);
        }
        $this->logActivity('onboarding', 'update', $id, 'Candidate uploaded ' . $type . ' document');

        return $this->documentResult($id, $token, true, $type === 'Resume' ? 'Resume uploaded.' : 'Document uploaded securely.');
    }

    /** A candidate may remove only what they uploaded themselves, and only while the form is still editable. */
    public function deleteDocument(int $id, string $token, int $documentId)
    {
        $record = $this->record($id, $token);
        if ($record === null) {
            return $this->wantsJson() ? $this->jsonError('This link is no longer valid.', 403) : $this->invalidLink();
        }
        if (! $this->isEditable($record)) {
            return $this->documentResult($id, $token, false, 'Documents can no longer be changed at this stage.');
        }

        $document = $this->documentModel->where('id', $documentId)->where('onboarding_record_id', $id)->where('uploaded_by', null)->first();
        if ($document === null) {
            return $this->documentResult($id, $token, false, 'That document could not be found.');
        }

        service('fileStorage')->delete($document['file_path']);
        $this->documentModel->delete($documentId);
        $this->logActivity('onboarding', 'update', $id, 'Candidate removed a ' . ($document['document_type'] ?: 'document'));

        return $this->documentResult($id, $token, true, 'Document removed.');
    }

    /** The form is open to the candidate only while the link is live and HR has not got it under review. */
    private function isEditable(array $record): bool
    {
        return $record['status'] === 'link_sent' && in_array($record['verification_status'], [null, 'pending', 'correction_needed'], true);
    }

    private function wantsJson(): bool
    {
        return $this->request->isAJAX();
    }

    private function jsonError(string $message, int $status = 422)
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $message, 'csrf' => csrf_hash()]);
    }

    private function documentResult(int $id, string $token, bool $ok, string $message)
    {
        if ($this->wantsJson()) {
            $documents = array_map(static fn (array $d) => [
                'id' => (int) $d['id'], 'type' => (string) $d['document_type'], 'name' => (string) $d['original_name'],
                'size' => (int) $d['file_size'],
            ], (new CandidateFormService())->documents($id));

            return $this->response->setStatusCode($ok ? 200 : 422)->setJSON([
                'ok' => $ok, 'message' => $message, 'documents' => $documents, 'csrf' => csrf_hash(),
            ]);
        }

        return redirect()->to("/onboarding/portal/{$id}/{$token}")->with($ok ? 'success' : 'error', $message);
    }

    /**
     * STEP 6 — candidate accepts or rejects the offer. Accepting moves
     * the pipeline forward exactly the way HR manually advancing it to
     * 'offer_accepted' already does (offer_accepted_at, reused verbatim —
     * see OnboardingController::updateStatus()); rejecting uses the
     * app's one existing exit path, 'withdrawn', rather than inventing a
     * second one.
     */
    public function respondToOffer(int $id, string $token)
    {
        $record = $this->record($id, $token);

        if ($record === null) {
            return $this->invalidLink();
        }

        // STEP 6 is gated on HR having actually approved the candidate's
        // details (verification_status === 'verified') — see
        // OnboardingController::reviewVerification(). An offer_sent_at
        // timestamp alone (HR advancing the pipeline stage) isn't
        // enough; the offer only becomes reachable through the portal
        // once HR has signed off, same rule enforced in portal/show.php.
        if ($record['status'] !== 'offer_sent' || $record['offer_sent_at'] === null || $record['verification_status'] !== 'verified' || $record['offer_response'] !== null) {
            return redirect()->to("/onboarding/portal/{$id}/{$token}")->with('error', 'There is no pending offer to respond to.');
        }

        $response = $this->request->getPost('response');
        if (! in_array($response, OnboardingRecordModel::OFFER_RESPONSES, true)) {
            return redirect()->to("/onboarding/portal/{$id}/{$token}")->with('error', 'Please choose Accept or Decline.');
        }

        $data = [
            'offer_response'      => $response,
            'offer_response_at'   => date('Y-m-d H:i:s'),
            'offer_response_note' => $this->request->getPost('note') ?: null,
        ];

        if ($response === 'accepted') {
            $data['status']            = 'offer_accepted';
            $data['offer_accepted_at'] = $record['offer_accepted_at'] ?? date('Y-m-d H:i:s');
        } else {
            $data['status'] = OnboardingRecordModel::WITHDRAWN;
        }

        $this->recordModel->update($id, $data);
        if ($response === 'accepted') {
            $this->taskModel->seedDefaultTasks($id);
        }
        $this->logActivity('onboarding', 'update', $id, 'Candidate ' . $response . ' the offer');

        $message = $response === 'accepted'
            ? 'Thank you for accepting the offer! HR will be in touch about the next steps.'
            : 'Your response has been recorded. Thank you for letting us know.';

        return redirect()->to("/onboarding/portal/{$id}/{$token}")->with('success', $message);
    }

    /**
     * Same document HR sees at hr/onboarding/{id}/offer-letter, just
     * reached through the token instead of a permission check — one
     * template, two access paths (see offer_letter.php's optional
     * $pdfUrl override, used here to point "Download PDF" back at this
     * controller's own pdf() action rather than the auth-gated one).
     */
    public function offerLetter(int $id, string $token)
    {
        $record = $this->record($id, $token);

        if ($record === null || $record['offer_sent_at'] === null || $record['verification_status'] !== 'verified') {
            return $this->invalidLink();
        }

        if (($templated = $this->templatedDocument('offer_letter', $id, true)) !== null) {
            return $templated;
        }

        return view('App\Modules\Onboarding\offer_letter', [
            'title'     => 'Offer Letter — ' . $record['candidate_name'],
            'record'    => $record,
            'company'   => $this->companyModel->find($record['company_id']),
            'autoPrint' => false,
            'pdfUrl'    => site_url("onboarding/portal/{$id}/{$token}/offer-letter/pdf"),
        ]);
    }

    public function offerLetterPdf(int $id, string $token)
    {
        $record = $this->record($id, $token);

        if ($record === null || $record['offer_sent_at'] === null || $record['verification_status'] !== 'verified') {
            return $this->invalidLink();
        }

        if (($templated = $this->templatedDocument('offer_letter', $id, false)) !== null) {
            return $templated;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('App\Modules\Onboarding\exports\offer_letter_pdf', [
            'record'  => $record,
            'company' => $this->companyModel->find($record['company_id']),
        ]));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="offer-letter-' . url_title($record['candidate_name'], '-', true) . '.pdf"')
            ->setBody($dompdf->output());
    }

    /**
     * The joining letter HR sent after the offer was accepted. Only once HR
     * has actually sent it (joining_letter_sent_at) and only for a live
     * record - the same template-only document HR sees, reached by token.
     */
    public function joiningLetter(int $id, string $token)
    {
        return $this->serveJoiningLetter($id, $token, true);
    }

    public function joiningLetterPdf(int $id, string $token)
    {
        return $this->serveJoiningLetter($id, $token, false);
    }

    private function serveJoiningLetter(int $id, string $token, bool $inline)
    {
        $record = $this->record($id, $token);

        if ($record === null || $record['joining_letter_sent_at'] === null || in_array($record['status'], ['withdrawn', 'rejected'], true)) {
            return $this->invalidLink();
        }

        return $this->templatedDocument('joining_letter', $id, $inline) ?? $this->invalidLink();
    }

    /**
     * The only lookup every action here uses — never a bare id. Checks
     * token match via hash_equals() (timing-safe, same as
     * MeetingController::join()) and expiry, and returns the enriched
     * withRelations() row (company/department/manager names) rather
     * than the raw find() row, since the view needs those for display.
     */
    private function record(int $id, string $token): ?array
    {
        $raw = $this->recordModel->find($id);

        if ($raw === null || $raw['candidate_token'] === null || $token === '') {
            return null;
        }
        if (! hash_equals($raw['candidate_token'], $token)) {
            return null;
        }
        if ($raw['token_expires_at'] === null || $raw['token_expires_at'] < date('Y-m-d H:i:s')) {
            return null;
        }

        return $this->recordModel->withRelations($id);
    }

    private function invalidLink()
    {
        return view('App\Modules\Onboarding\portal\invalid', ['title' => 'Link Not Available']);
    }
}
