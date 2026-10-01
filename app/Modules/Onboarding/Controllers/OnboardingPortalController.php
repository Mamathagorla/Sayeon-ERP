<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Onboarding\Models\OnboardingRecordModel;
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

    public function __construct()
    {
        $this->recordModel = new OnboardingRecordModel();
        $this->companyModel = new CompanyModel();
    }

    public function show(int $id, string $token)
    {
        $record = $this->record($id, $token);

        if ($record === null) {
            return $this->invalidLink();
        }

        return view('App\Modules\Onboarding\portal\show', [
            'title'  => 'Your Onboarding — ' . $record['candidate_name'],
            'record' => $record,
            'token'  => $token,
        ]);
    }

    /**
     * STEP 3 — candidate verifies/updates their own details. Only the
     * phone number is actually editable in place (the one candidate_*
     * field HR would reasonably expect to change); everything else HR
     * entered stays as HR entered it. Re-submittable after HR asks for
     * a correction (verification_status = 'correction_needed'), but not
     * once already 'submitted' or 'verified' — the candidate can't just
     * keep resubmitting over HR's own review.
     */
    public function submitVerification(int $id, string $token)
    {
        $record = $this->record($id, $token);

        if ($record === null) {
            return $this->invalidLink();
        }

        if (! in_array($record['verification_status'], [null, 'pending', 'correction_needed'], true)) {
            return redirect()->to("/onboarding/portal/{$id}/{$token}")->with('error', 'This has already been submitted for review.');
        }

        $phone = trim((string) $this->request->getPost('candidate_phone'));
        if ($phone !== '' && ! preg_match('/^[0-9]{10}$/', $phone)) {
            return redirect()->to("/onboarding/portal/{$id}/{$token}")->with('error', 'Phone number must be exactly 10 digits.');
        }

        $this->recordModel->update($id, [
            'candidate_phone'            => $phone !== '' ? $phone : $record['candidate_phone'],
            'verification_status'        => 'submitted',
            'verification_submitted_at'  => date('Y-m-d H:i:s'),
            'verification_notes'         => $this->request->getPost('notes') ?: null,
        ]);

        $this->logActivity('onboarding', 'update', $id, 'Candidate submitted verification details for review');

        return redirect()->to("/onboarding/portal/{$id}/{$token}")->with('success', 'Thanks — your information has been submitted for HR review.');
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
        if ($record['offer_sent_at'] === null || $record['verification_status'] !== 'verified' || $record['offer_response'] !== null) {
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
