<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-08  Recruitment & Onboarding:
 * candidate -> selected -> portal link -> candidate details -> HR review -> approval -> offer
 * -> candidate response -> onboarding checklist -> joined -> employee record (+ login).
 */
final class RecruitmentToEmployeeFlowTest extends E2ETestCase
{
    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    private function newCandidate(string $name = 'Asha Verma', string $email = 'asha.verma@candidates.test', ?array $by = null): int
    {
        $A = $this->w['A'];
        $this->actAs($by ?? $A['hr'])->submit('/hr/onboarding', [
            'company_id'           => $A['company']['id'],
            'candidate_name'       => $name,
            'candidate_email'      => $email,
            'candidate_phone'      => '9876543210',
            'candidate_source'     => 'referral',
            'department_id'        => $this->w['dept'],
            'designation'          => 'Software Engineer',
            'reporting_manager_id' => $A['manager']['user_id'],
        ]);

        $row = $this->row('onboarding_records', ['candidate_email' => $email]);

        return (int) ($row['id'] ?? 0);
    }

    /** A complete, valid candidate form for the "Software Engineer" role (software question set). */
    private function portalForm(array $over = []): array
    {
        return $over + [
            'candidate_type'  => 'fresher',
            'candidate_phone' => '9876543210',
            'address_line1'   => '12 MG Road',
            'city'            => 'Pune',
            'permanent_same_as_current' => '1',
            'education'       => [['degree' => 'B.Tech', 'institution' => 'COEP', 'year' => '2024']],
            'answers'         => ['q_software_1' => 'PHP, Python', 'q_software_4' => 'REST APIs and databases'],
            'confirm_accuracy' => '1',
        ];
    }

    /** The candidate has uploaded a resume (the upload endpoint itself is covered in OnboardingCandidateProfileFlowTest). */
    private function giveResume(int $id): void
    {
        $rec = $this->row('onboarding_records', ['id' => $id]);
        if ($this->countRows('documents', ['onboarding_record_id' => $id, 'document_type' => 'Resume']) === 0) {
            db_connect()->table('documents')->insert([
                'company_id' => $rec['company_id'], 'category' => 'hr', 'document_type' => 'Resume', 'title' => 'Resume',
                'onboarding_record_id' => $id, 'file_path' => 'onboarding_candidates/' . $id . '/e2e.pdf', 'original_name' => 'resume.pdf',
                'file_size' => 1000, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function submitPortal(int $id, string $token, array $over = []): void
    {
        $this->giveResume($id);
        $this->anonymous()->submit("/onboarding/portal/$id/$token/verify", $this->portalForm($over));
    }

    /** The company's active letter templates (once per test) and the details the letters need (CTC, joining date). */
    private function letterTemplates(): void
    {
        $co = $this->w['A']['company'];
        if ($this->countRows('document_templates', ['company_id' => $co['id']]) === 0) {
            $this->template($co, 'offer_letter', '<html><body>Offer: {{employee_name}} as {{designation}} from {{joining_date}} at {{annual_salary}}</body></html>');
            $this->template($co, 'joining_letter', '<html><body>Joining: {{employee_name}} as {{designation}} on {{joining_date}}</body></html>');
        }
        // The per-process availability cache would otherwise keep answering "no template" within one test.
        \App\Modules\Document\Services\DocumentAvailability::shared()->flush();
    }

    private function offerDetails(int $id): void
    {
        db_connect()->table('onboarding_records')->where('id', $id)->update(['joining_date' => date('Y-m-d', strtotime('+30 days')), 'offered_ctc' => 600000]);
    }

    /** HR records an assessment (required before approving a detailed submission). */
    private function evaluate(int $id, array $hr): void
    {
        $this->actAs($hr)->submit("/hr/onboarding/$id/evaluation", ['technical_assessment' => 'Solid fundamentals for the role.']);
    }

    /** Drives a candidate to `offer_sent` through the real routes; returns [id, token]. */
    private function toOfferSent(string $name = 'Asha Verma', string $email = 'asha.verma@candidates.test'): array
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate($name, $email);
        $this->assertGreaterThan(0, $id, 'candidate was not created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));

        $this->actAs($hr)->submit("/hr/onboarding/$id/select");
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-portal-link");
        $token = $this->row('onboarding_records', ['id' => $id])['candidate_token'];

        $this->submitPortal($id, $token);
        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        $this->evaluate($id, $hr);
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'approved']);
        $this->letterTemplates();
        $this->offerDetails($id);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");

        return [$id, $this->row('onboarding_records', ['id' => $id])['candidate_token']];
    }

    public function testHappyPathFromCandidateToEmployeeRecord(): void
    {
        $A  = $this->w['A'];
        $hr = $A['hr'];

        // 1. HR adds the candidate.
        $id  = $this->newCandidate();
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertNotNull($rec, 'candidate row should exist');
        $this->assertSame('candidate', $rec['status']);
        $this->assertSame((string) $A['company']['id'], (string) $rec['company_id']);
        $this->assertCount(1, $this->notificationsFor($A['manager']['user_id'], 'onboarding_assigned'), 'reporting manager is notified');
        $this->assertNotNull($this->row('activity_logs', ['module' => 'onboarding', 'action' => 'create', 'record_id' => $id]));

        // 2. Selected -> portal link (email is NOT configured, so nothing is mailed and the link is shown instead).
        $this->actAs($hr)->submit("/hr/onboarding/$id/select");
        $this->assertSame('selected_for_onboarding', $this->row('onboarding_records', ['id' => $id])['status']);

        $this->actAs($hr)->submit("/hr/onboarding/$id/send-portal-link");
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('link_sent', $rec['status']);
        $this->assertSame('pending', $rec['verification_status']);
        $this->assertSame(48, strlen($rec['candidate_token']));
        $this->assertGreaterThan(date('Y-m-d H:i:s', strtotime('+6 days')), $rec['token_expires_at']);
        $this->assertFlash('success', 'email delivery is not configured');
        $token = $rec['candidate_token'];

        // 3. The candidate (no ERP login) opens the portal and submits their details.
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $this->assertTrue($this->res->isOK());
        $this->submitPortal($id, $token, ['candidate_phone' => '9123456780']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('details_submitted', $rec['status']);
        $this->assertSame('submitted', $rec['verification_status']);
        $this->assertSame('9123456780', $rec['candidate_phone']);

        // 4. HR review -> approval.
        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        $this->assertSame('hr_review', $this->row('onboarding_records', ['id' => $id])['status']);
        // A detailed submission must be assessed before it can be approved.
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'approved']);
        $this->assertSame('hr_review', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->assertFlash('error', 'evaluation');
        $this->evaluate($id, $hr);
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'approved']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('approved', $rec['status']);
        $this->assertSame('verified', $rec['verification_status']);
        $this->assertSame((string) $hr['user_id'], (string) $rec['verification_reviewed_by']);

        // 5. Send the offer letter. Official HR letters are template-only and are produced BEFORE anything is sent:
        //    no template, or missing details (joining date / CTC), keep the candidate at "approved".
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $this->assertSame('approved', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->assertFlash('error', 'No active Offer Letter template');
        $this->assertSame(0, $this->countRows('generated_documents'), 'nothing is archived when no document is produced');

        $this->letterTemplates();
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $this->assertSame('approved', $this->row('onboarding_records', ['id' => $id])['status'], 'missing joining date / CTC block the offer');
        $this->assertFlash('error', 'missing');
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['offer_sent_at']);

        $this->offerDetails($id);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('offer_sent', $rec['status']);
        $this->assertNotNull($rec['offer_sent_at']);
        $this->assertSame(1, $this->countRows('generated_documents', ['document_type' => 'offer_letter', 'record_id' => $id]), 'the issued offer letter is archived');

        $this->anonymous()->visit("/onboarding/portal/$id/$token/offer-letter");
        $this->assertFalse($this->res->isRedirect(), 'the candidate can open the offer letter');

        // Resending before the candidate answers keeps the original sent date.
        $sentAt = $rec['offer_sent_at'];
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $this->assertFlash('success', 're-sent');
        $this->assertSame($sentAt, $this->row('onboarding_records', ['id' => $id])['offer_sent_at']);

        // The joining letter cannot be sent before the candidate accepts, and is invisible to them until then.
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-joining-letter");
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at']);
        $this->assertFlash('error', 'accepted the offer');
        $this->anonymous()->visit("/onboarding/portal/$id/$token/joining-letter");
        $this->assertStringContainsString('Link Not Available', $this->body());

        // 6. Candidate accepts -> checklist is seeded exactly once.
        $this->anonymous()->submit("/onboarding/portal/$id/$token/offer-response", ['response' => 'accepted']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('offer_accepted', $rec['status']);
        $this->assertSame('accepted', $rec['offer_response']);
        $tasks = $this->countRows('onboarding_tasks', ['onboarding_record_id' => $id]);
        $this->assertGreaterThan(0, $tasks);

        $this->anonymous()->submit("/onboarding/portal/$id/$token/offer-response", ['response' => 'accepted']);
        $this->assertSame($tasks, $this->countRows('onboarding_tasks', ['onboarding_record_id' => $id]), 'a repeated acceptance must not seed the checklist twice');

        // 6b. After acceptance HR sends the joining letter; the candidate then opens it from their link.
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-joining-letter");
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertNotNull($rec['joining_letter_sent_at'], 'joining letter sent');
        $this->assertSame('offer_accepted', $rec['status'], 'sending a letter does not move the stage');
        $this->assertSame(1, $this->countRows('generated_documents', ['document_type' => 'joining_letter', 'record_id' => $id]));
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $this->assertStringContainsString('Your Documents', $this->body());
        $this->assertStringContainsString('Joining Letter', $this->body());
        $this->assertStringContainsString('Offer Letter', $this->body(), 'the candidate sees both letters under Your Documents');
        $this->assertStringContainsString("/onboarding/portal/$id/$token/joining-letter/pdf", $this->body());

        // HR's Documents tab lists both letters with view / download.
        $this->actAs($hr)->visit("/hr/onboarding/$id");
        $html = $this->body();
        $this->assertStringContainsString('Letters sent to the candidate', $html);
        $this->assertStringContainsString('Asha Verma - Offer Letter', $html);
        $this->assertStringContainsString('Asha Verma - Joining Letter', $html);
        $this->assertStringContainsString('Accepted by candidate', $html);
        $this->assertStringContainsString("/hr/onboarding/$id/offer-letter/pdf", $html);
        $this->assertStringContainsString("/hr/onboarding/$id/joining-letter/pdf", $html);
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $this->anonymous()->visit("/onboarding/portal/$id/$token/joining-letter");
        $this->assertFalse($this->res->isRedirect());
        $this->assertStringNotContainsString('Link Not Available', $this->body());
        $this->anonymous()->visit("/onboarding/portal/$id/$token/joining-letter/pdf");
        $this->assertSame(200, $this->res->response()->getStatusCode());
        $this->actAs($hr)->visit("/hr/onboarding/$id/joining-letter");
        $this->assertFalse($this->res->isRedirect(), 'HR can open it as soon as it has been sent');
        $firstSent = $rec['joining_letter_sent_at'];
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-joining-letter");
        $this->assertFlash('success', 're-sent');
        $this->assertSame($firstSent, $this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at'], 'a resend keeps the first sent date');

        // 7. HR walks the pre-joining/joining stages in order.
        foreach (['documents_pending', 'bgv', 'hr_verification', 'ready_to_join', 'joining', 'joined'] as $stage) {
            $this->actAs($hr)->submit("/hr/onboarding/$id/status", ['status' => $stage]);
            $this->assertSame($stage, $this->row('onboarding_records', ['id' => $id])['status'], "stage $stage");
        }
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertNotNull($rec['hr_verified_at']);
        $this->assertNotNull($rec['joined_at']);

        // 8. Employee record + login are created from the onboarding record.
        $this->actAs($hr)->submit("/hr/onboarding/$id/create-employee");
        $rec  = $this->row('onboarding_records', ['id' => $id]);
        $this->assertNotEmpty($rec['employee_profile_id'], 'onboarding record is linked to the new profile');
        $prof = $this->row('employee_profiles', ['id' => $rec['employee_profile_id']]);
        $this->assertSame((string) $A['company']['id'], (string) $prof['company_id'], 'company comes from the onboarding record');
        $this->assertSame((string) $A['manager']['user_id'], (string) $prof['reporting_manager_id']);
        $this->assertSame('active', $prof['status']);
        $user = $this->row('users', ['id' => $prof['user_id']]);
        $this->assertSame('asha.verma@candidates.test', $user['email']);
        $this->assertSame((string) db_connect()->table('roles')->where('slug', 'employee')->get()->getRow('id'), (string) $user['role_id']);
        $this->assertStringStartsWith('ALP', $prof['employee_code'], 'employee code uses the company short code');
        $this->assertFlash('success', 'Temporary password');

        // 9. Retrying (double click / second tab) is a no-op: no second user, profile or code.
        $this->actAs($hr)->submit("/hr/onboarding/$id/create-employee");
        $this->assertSame(1, $this->countRows('users', ['email' => 'asha.verma@candidates.test']));
        $this->assertSame(1, $this->countRows('employee_profiles', ['user_id' => $user['id']]));
        $this->assertFlash('success', 'already linked');
    }

    public function testStagesCannotBeSkipped(): void
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate();

        // candidate -> straight to offer/joined is refused; only the defined next step is allowed.
        foreach (['/send-offer', '/start-review', '/verification/review'] as $path) {
            $this->actAs($hr)->submit("/hr/onboarding/$id$path", ['decision' => 'approved']);
            $this->assertSame('candidate', $this->row('onboarding_records', ['id' => $id])['status'], "POST $path must not move a fresh candidate");
        }
        $this->actAs($hr)->submit("/hr/onboarding/$id/status", ['status' => 'joined']);
        $this->assertSame('candidate', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->assertFlash('error', 'next available workflow action');

        // Employee cannot be created before the candidate has joined.
        $this->actAs($hr)->submit("/hr/onboarding/$id/create-employee");
        $this->assertFlash('error', 'once the candidate has joined');
        $this->assertSame(0, $this->countRows('users', ['email' => 'asha.verma@candidates.test']));
    }

    public function testCorrectionRequestedThenResubmitted(): void
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate();
        $this->actAs($hr)->submit("/hr/onboarding/$id/select");
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-portal-link");
        $token = $this->row('onboarding_records', ['id' => $id])['candidate_token'];

        $this->submitPortal($id, $token);
        // A second submit while under review is refused.
        $this->submitPortal($id, $token);
        $this->assertFlash('error', 'already been submitted');

        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        // Asking for changes without saying what to change is refused.
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'correction_needed']);
        $this->assertSame('hr_review', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'correction_needed', 'notes' => 'Address proof missing']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('link_sent', $rec['status'], 'sent back to the candidate');
        $this->assertSame('correction_needed', $rec['verification_status']);
        $this->assertSame('Address proof missing', $rec['verification_notes']);

        $this->submitPortal($id, $token);
        $this->assertSame('details_submitted', $this->row('onboarding_records', ['id' => $id])['status'], 'candidate can resubmit after a correction request');
    }

    public function testRejectedCandidateCannotReceiveAnOffer(): void
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate();
        $this->actAs($hr)->submit("/hr/onboarding/$id/select");
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-portal-link");
        $token = $this->row('onboarding_records', ['id' => $id])['candidate_token'];
        $this->submitPortal($id, $token, ['education' => [['degree' => 'MBA', 'institution' => 'IIM', 'year' => '2020']]]);
        $this->actAs($hr)->submit("/hr/onboarding/$id/start-review");
        // A rejection needs a reason on file.
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'rejected']);
        $this->assertSame('hr_review', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/verification/review", ['decision' => 'rejected', 'notes' => 'Failed screening']);

        $this->assertSame('rejected', $this->row('onboarding_records', ['id' => $id])['status']);

        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $this->assertSame('rejected', $this->row('onboarding_records', ['id' => $id])['status'], 'no offer for a rejected candidate');
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['offer_sent_at']);

        $this->anonymous()->visit("/onboarding/portal/$id/$token/offer-letter");
        $this->assertStringNotContainsString('Software Engineer', $this->body(), 'offer letter is not served');
    }

    public function testCandidateDeclinesOfferAndIsWithdrawn(): void
    {
        [$id, $token] = $this->toOfferSent();

        $this->anonymous()->submit("/onboarding/portal/$id/$token/offer-response", ['response' => 'rejected', 'note' => 'Accepted another role']);
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('withdrawn', $rec['status']);
        $this->assertSame('rejected', $rec['offer_response']);
        $this->assertSame(0, $this->countRows('onboarding_tasks', ['onboarding_record_id' => $id]), 'no checklist for a declined offer');

        // A withdrawn candidate cannot be pushed forward or turned into an employee.
        $this->actAs($this->w['A']['hr'])->submit("/hr/onboarding/$id/status", ['status' => 'documents_pending']);
        $this->assertSame('withdrawn', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->actAs($this->w['A']['hr'])->submit("/hr/onboarding/$id/create-employee");
        $this->assertSame(0, $this->countRows('users', ['email' => 'asha.verma@candidates.test']));
    }

    public function testHrCanWithdrawAnActiveCandidateButNotAClosedOne(): void
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate();
        $this->actAs($hr)->submit("/hr/onboarding/$id/withdraw");
        $this->assertSame('withdrawn', $this->row('onboarding_records', ['id' => $id])['status']);

        $this->actAs($hr)->submit("/hr/onboarding/$id/withdraw");
        $this->assertFlash('error', 'already at a closed stage');
    }

    public function testPortalTokenIsEnforced(): void
    {
        [$id, $token] = $this->toOfferSent();

        // Wrong token and expired token never show or change the record.
        $this->anonymous()->visit("/onboarding/portal/$id/" . str_repeat('0', 48));
        $this->assertStringNotContainsString('Asha Verma', $this->body());
        $this->anonymous()->submit("/onboarding/portal/$id/" . str_repeat('0', 48) . '/offer-response', ['response' => 'accepted']);
        $this->assertSame('offer_sent', $this->row('onboarding_records', ['id' => $id])['status']);

        db_connect()->table('onboarding_records')->where('id', $id)->update(['token_expires_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $this->anonymous()->visit("/onboarding/portal/$id/$token");
        $this->assertStringNotContainsString('Asha Verma', $this->body(), 'expired token shows nothing');
        $this->anonymous()->submit("/onboarding/portal/$id/$token/offer-response", ['response' => 'accepted']);
        $this->assertSame('offer_sent', $this->row('onboarding_records', ['id' => $id])['status'], 'expired token cannot accept the offer');

        // Recovery: an expired link must be re-issuable by HR so the candidate is not locked out of an offer
        // they have not answered yet. (D-01)
        $this->actAs($this->w['A']['hr'])->submit("/hr/onboarding/$id/send-portal-link");
        $reissued = $this->row('onboarding_records', ['id' => $id]);
        $this->assertRuleOrKnownDefect(
            $reissued['candidate_token'] !== $token && $reissued['status'] === 'offer_sent' && $reissued['token_expires_at'] > date('Y-m-d H:i:s'),
            'D-01',
            'After the offer is sent and the 7-day portal link expires, HR has no way to issue a fresh link (send-portal-link only works before the offer, send-offer only from "approved").'
        );
    }

    public function testOfferCannotBeAcceptedBeforeHrApproval(): void
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate();
        $this->actAs($hr)->submit("/hr/onboarding/$id/select");
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-portal-link");
        $token = $this->row('onboarding_records', ['id' => $id])['candidate_token'];

        $this->anonymous()->submit("/onboarding/portal/$id/$token/offer-response", ['response' => 'accepted']);
        $this->assertSame('link_sent', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['offer_response']);
    }

    public function testLettersAreOnlyForTheRightPeopleAndStages(): void
    {
        $A  = $this->w['A'];
        $hr = $A['hr'];
        [$id, $token] = $this->toOfferSent();

        // Only HR sends letters; other roles (even with onboarding.edit) and other companies cannot.
        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->actAs($A[$role])->submit("/hr/onboarding/$id/send-joining-letter");
            $this->assertNull($this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at'], "$role cannot send a joining letter");
        }
        $this->anonymous()->submit("/hr/onboarding/$id/send-joining-letter");
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at']);

        // Accepting makes it available; another company's HR still cannot send it.
        $this->anonymous()->submit("/onboarding/portal/$id/$token/offer-response", ['response' => 'accepted']);
        $this->actAs($this->w['B']['hr'])->submit("/hr/onboarding/$id/send-joining-letter");
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at'], 'HR of another company cannot send it');

        // The joining letter needs its own template and the joining date.
        db_connect()->table('document_templates')->where(['company_id' => $A['company']['id'], 'document_type' => 'joining_letter'])->update(['status' => 'inactive']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-joining-letter");
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at']);
        $this->assertFlash('error', 'Joining Letter template');
        db_connect()->table('document_templates')->where(['company_id' => $A['company']['id'], 'document_type' => 'joining_letter'])->update(['status' => 'active']);
        \App\Modules\Document\Services\DocumentAvailability::shared()->flush();

        db_connect()->table('onboarding_records')->where('id', $id)->update(['joining_date' => null]);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-joining-letter");
        $this->assertNull($this->row('onboarding_records', ['id' => $id])['joining_letter_sent_at']);
        $this->assertFlash('error', 'missing');
        $this->offerDetails($id);

        // An expired link is refreshed so the mailed link really works.
        db_connect()->table('onboarding_records')->where('id', $id)->update(['token_expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-joining-letter");
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertNotNull($rec['joining_letter_sent_at']);
        $this->assertSame($token, $rec['candidate_token']);
        $this->assertGreaterThan(date('Y-m-d H:i:s', strtotime('+6 days')), $rec['token_expires_at']);
        $this->anonymous()->visit("/onboarding/portal/$id/$token/joining-letter");
        $this->assertStringNotContainsString('Link Not Available', $this->body());

        // A withdrawn candidate no longer sees it.
        db_connect()->table('onboarding_records')->where('id', $id)->update(['status' => 'withdrawn']);
        $this->anonymous()->visit("/onboarding/portal/$id/$token/joining-letter");
        $this->assertStringContainsString('Link Not Available', $this->body());
    }

    public function testMissingOfferDetailsPointBackToTheRecordNotToThePostOnlyAction(): void
    {
        $hr = $this->w['A']['hr'];
        $id = $this->newCandidate();
        $this->letterTemplates();
        db_connect()->table('onboarding_records')->where('id', $id)->update(['status' => 'approved']);

        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $this->assertSame('approved', $this->row('onboarding_records', ['id' => $id])['status']);
        $guide = json_encode($this->flash('missing_details'));
        $this->assertStringContainsString('hr\/onboarding\/' . $id, $guide, 'the guidance points at the onboarding record');
        $this->assertStringNotContainsString('send-offer', $guide, '"Return & continue" must not lead to the POST-only action');

        // A stale link to the action URL just lands on the record.
        $this->actAs($hr)->visit("/hr/onboarding/$id/send-offer");
        $this->assertTrue($this->res->isRedirect());
        $this->assertStringEndsWith("/hr/onboarding/$id", $this->res->getRedirectUrl());
        $this->assertSame('approved', $this->row('onboarding_records', ['id' => $id])['status'], 'visiting the URL sends nothing');
    }

    public function testOfferCanBeResentButNotAfterTheCandidateResponds(): void
    {
        $hr = $this->w['A']['hr'];
        [$id, $token] = $this->toOfferSent();

        // The link expired before the candidate answered: resending refreshes it (no dead end for HR).
        db_connect()->table('onboarding_records')->where('id', $id)->update(['token_expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $rec = $this->row('onboarding_records', ['id' => $id]);
        $this->assertSame('offer_sent', $rec['status']);
        $this->assertGreaterThan(date('Y-m-d H:i:s', strtotime('+6 days')), $rec['token_expires_at']);
        $this->anonymous()->visit("/onboarding/portal/$id/{$rec['candidate_token']}");
        $this->assertStringContainsString('Your Offer', $this->body());

        $this->anonymous()->submit("/onboarding/portal/$id/{$rec['candidate_token']}/offer-response", ['response' => 'accepted']);
        $this->actAs($hr)->submit("/hr/onboarding/$id/send-offer");
        $this->assertFlash('error', 'already responded');
        $this->assertSame('offer_accepted', $this->row('onboarding_records', ['id' => $id])['status']);
    }

    public function testRolePermissionsOnTheOnboardingPipeline(): void
    {
        $A  = $this->w['A'];
        $id = $this->newCandidate();

        // Only HR / Super Admin own the pipeline - even Company Admin (who holds onboarding.edit) is refused by the controller.
        foreach (['admin', 'manager', 'accountant'] as $role) {
            $this->actAs($A[$role])->submit("/hr/onboarding/$id/select");
            $this->assertSame('candidate', $this->row('onboarding_records', ['id' => $id])['status'], "$role must not select a candidate");
        }

        // Roles without onboarding.edit never reach the controller.
        foreach (['employee', 'compliance'] as $role) {
            $this->actAs($A[$role])->submit("/hr/onboarding/$id/select");
            $this->assertDenied("$role must be denied by the permission filter");
            $this->assertSame('candidate', $this->row('onboarding_records', ['id' => $id])['status']);
        }

        // Creating a candidate needs onboarding.create: manager does not have it.
        $before = $this->countRows('onboarding_records');
        $this->actAs($A['manager'])->submit('/hr/onboarding', ['company_id' => $A['company']['id'], 'candidate_name' => 'Nope Person', 'candidate_email' => 'nope@x.test']);
        $this->assertSame($before, $this->countRows('onboarding_records'));

        // Anonymous visitors are bounced to the login page.
        $this->anonymous()->visit('/hr/onboarding');
        $this->assertTrue($this->res->isRedirect());
        $this->assertStringContainsString('auth/login', $this->res->getRedirectUrl());
    }

    public function testCompanyIsolationOnOnboardingRecords(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->newCandidate();

        // HR of company B cannot see, advance or delete company A's candidate; it behaves as "not found".
        $this->actAs($B['hr'])->visit("/hr/onboarding/$id");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'not found');

        $this->actAs($B['hr'])->submit("/hr/onboarding/$id/select");
        $this->assertSame('candidate', $this->row('onboarding_records', ['id' => $id])['status']);
        $this->actAs($B['hr'])->submit("/hr/onboarding/$id/delete");
        $this->assertNotNull($this->row('onboarding_records', ['id' => $id]), 'cross-company delete is refused');

        // HR of A cannot file a candidate under company B, nor name a manager from another company.
        $this->actAs($A['hr'])->submit('/hr/onboarding', [
            'company_id' => $B['company']['id'], 'candidate_name' => 'Cross Company', 'candidate_email' => 'cross@x.test', 'candidate_phone' => '9876543210',
            'department_id' => $this->w['dept'], 'designation' => 'QA', 'reporting_manager_id' => $B['manager']['user_id'],
        ]);
        $this->assertNull($this->row('onboarding_records', ['candidate_email' => 'cross@x.test']));

        $this->actAs($A['hr'])->submit('/hr/onboarding', [
            'company_id' => $A['company']['id'], 'candidate_name' => 'Wrong Manager', 'candidate_email' => 'wrongmgr@x.test', 'candidate_phone' => '9876543210',
            'department_id' => $this->w['dept'], 'designation' => 'QA', 'reporting_manager_id' => $B['manager']['user_id'],
        ]);
        $this->assertNull($this->row('onboarding_records', ['candidate_email' => 'wrongmgr@x.test']));
        $this->assertFlash('error', 'reporting manager from the candidate');
    }

    public function testInvalidCandidateInputIsRejected(): void
    {
        $A = $this->w['A'];
        $this->actAs($A['hr'])->submit('/hr/onboarding', ['company_id' => $A['company']['id'], 'candidate_name' => '', 'candidate_email' => 'not-an-email']);
        $this->assertSame(0, $this->countRows('onboarding_records'));
        $this->assertNotEmpty($this->flash('errors'));
    }

    public function testCreateEmployeeReusesAnExistingLoginInTheSameCompanyAndRefusesAnotherCompany(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $hr = $A['hr'];

        // The candidate's email already belongs to an employee of company B.
        $id = $this->newCandidate('Bhavna Rao', $B['employee']['email']);
        db_connect()->table('onboarding_records')->where('id', $id)->update(['status' => 'joined', 'joined_at' => date('Y-m-d H:i:s')]);

        $this->actAs($hr)->submit("/hr/onboarding/$id/create-employee");
        $this->assertFlash('error', 'another company');
        $this->assertEmpty($this->row('onboarding_records', ['id' => $id])['employee_profile_id']);
        $this->assertSame(1, $this->countRows('employee_profiles', ['user_id' => $B['employee']['user_id']]), 'the foreign employee was not moved or duplicated');

        // Same company: the existing user/profile is linked, not duplicated.
        $id2 = $this->newCandidate('Existing Staff', $A['employee']['email']);
        db_connect()->table('onboarding_records')->where('id', $id2)->update(['status' => 'joined', 'joined_at' => date('Y-m-d H:i:s')]);
        $this->actAs($hr)->submit("/hr/onboarding/$id2/create-employee");
        $this->assertSame((string) $A['employee']['profile_id'], (string) $this->row('onboarding_records', ['id' => $id2])['employee_profile_id']);
        $this->assertSame(1, $this->countRows('users', ['email' => $A['employee']['email']]));
        $this->assertSame(1, $this->countRows('employee_profiles', ['user_id' => $A['employee']['user_id']]));
    }

    public function testCreateEmployeeLeavesNothingBehindWhenTheEmployeeRoleIsMissing(): void
    {
        $A  = $this->w['A'];
        $id = $this->newCandidate('No Role Hire', 'norole@candidates.test');
        db_connect()->table('onboarding_records')->where('id', $id)->update(['status' => 'joined', 'joined_at' => date('Y-m-d H:i:s')]);

        // Simulate the failure the controller guards against; the role is restored afterwards.
        $db   = db_connect();
        $role = $db->table('roles')->where('slug', 'employee')->get()->getRowArray();
        $db->table('roles')->where('id', $role['id'])->update(['slug' => 'employee_tmp_off']);
        try {
            $this->actAs($A['hr'])->submit("/hr/onboarding/$id/create-employee");
        } finally {
            $db->table('roles')->where('id', $role['id'])->update(['slug' => 'employee']);
        }

        $this->assertFlash('error', 'Employee role is missing');
        $this->assertSame(0, $this->countRows('users', ['email' => 'norole@candidates.test']));
        $this->assertEmpty($this->row('onboarding_records', ['id' => $id])['employee_profile_id']);
    }
}
