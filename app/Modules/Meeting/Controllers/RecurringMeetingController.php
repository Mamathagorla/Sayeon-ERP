<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Meeting\Models\RecurringMeetingModel;
use App\Modules\Meeting\Models\RecurringMeetingParticipantModel;
use App\Modules\Meeting\Services\RecurringMeetingGenerator;

/**
 * Recurring meeting templates — "automatic meeting creation based on
 * time". Same company isolation and meeting.* permissions as
 * MeetingController; generation itself lives in RecurringMeetingGenerator
 * (see its docblock), mirroring RecurringExpenseController's shape.
 */
class RecurringMeetingController extends BaseController
{
    protected RecurringMeetingModel $recurringModel;
    protected RecurringMeetingParticipantModel $templateParticipantModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->recurringModel = new RecurringMeetingModel();
        $this->templateParticipantModel = new RecurringMeetingParticipantModel();
        $this->companyModel   = new CompanyModel();
        $this->userModel      = new UserModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Meeting\recurring_index', [
            'title'     => 'Recurring Meetings',
            'navActive' => 'recurring-meetings',
            'templates' => $this->recurringModel->filtered($filters)->findAll(),
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'  => RecurringMeetingModel::STATUSES,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Meeting\recurring_form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->recurringModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = (int) $this->request->getPost('company_id');

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $companyId)) {
            return redirect()->back()->withInput()->with('error', 'You can only add recurring meetings for your own company.');
        }

        $error = $this->validateEndCondition();
        if ($error !== null) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $startDate = $this->request->getPost('start_date');

        $id = $this->recurringModel->insert([
            'company_id'            => $companyId,
            'title'                 => $this->request->getPost('title'),
            'agenda'                => $this->request->getPost('agenda'),
            'location'              => $this->request->getPost('location') ?: null,
            'meeting_link'          => $this->request->getPost('meeting_link') ?: null,
            'start_time'            => $this->request->getPost('start_time') ?: null,
            'end_time'              => $this->request->getPost('end_time') ?: null,
            'frequency'             => $this->request->getPost('frequency') ?: 'weekly',
            'start_date'            => $startDate,
            'end_type'              => $this->request->getPost('end_type') ?: 'occurrences',
            'end_date'              => $this->request->getPost('end_type') === 'end_date' ? $this->request->getPost('end_date') : null,
            'occurrences_total'     => $this->request->getPost('end_type') === 'occurrences' ? (int) $this->request->getPost('occurrences_total') : null,
            'occurrences_generated' => 0,
            'next_generation_date'  => $startDate,
            'status'                => 'active',
            'created_by'            => $this->currentUserId(),
        ]);

        $this->templateParticipantModel->replaceForTemplate($id, $this->request->getPost('participant_user_ids') ?? []);

        $this->logActivity('meeting', 'create', $id, 'Created recurring meeting: ' . $this->request->getPost('title'));

        return redirect()->to('/recurring-meetings')->with('success', 'Recurring meeting created. It will generate its first meeting on ' . esc($startDate) . '.');
    }

    public function edit(int $id)
    {
        $template = $this->recurringModel->filtered()->where('recurring_meetings.id', $id)->first();

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-meetings')->with('error', 'Recurring meeting not found.');
        }

        $data = $this->formData($template);
        // (int) cast — DB-driver values come back as numeric strings, and
        // the view's in_array(..., true) is a strict comparison against
        // (int) $u['id'], so leaving these as strings would silently
        // never match and no checkbox would ever show pre-checked.
        $data['selectedParticipantIds'] = array_map('intval', array_column($this->templateParticipantModel->forTemplate($id), 'user_id'));

        return view('App\Modules\Meeting\recurring_form', $data);
    }

    /**
     * Only forward-looking fields — same reasoning as
     * RecurringExpenseController::update(): frequency/start_date are
     * locked after creation so next_generation_date/occurrences_generated
     * never desync from what's already been generated.
     */
    public function update(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-meetings')->with('error', 'Recurring meeting not found.');
        }

        $rules = ['title' => 'required|min_length[2]|max_length[200]'];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->recurringModel->update($id, [
            'title'        => $this->request->getPost('title'),
            'agenda'       => $this->request->getPost('agenda'),
            'location'     => $this->request->getPost('location') ?: null,
            'meeting_link' => $this->request->getPost('meeting_link') ?: null,
            'start_time'   => $this->request->getPost('start_time') ?: null,
            'end_time'     => $this->request->getPost('end_time') ?: null,
        ]);

        $this->templateParticipantModel->replaceForTemplate($id, $this->request->getPost('participant_user_ids') ?? []);

        $this->logActivity('meeting', 'update', $id, 'Updated recurring meeting: ' . $this->request->getPost('title'));

        return redirect()->to('/recurring-meetings')->with('success', 'Recurring meeting updated.');
    }

    public function pause(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-meetings')->with('error', 'Recurring meeting not found.');
        }
        if ($template['status'] !== 'active') {
            return redirect()->to('/recurring-meetings')->with('error', 'Only an active recurring meeting can be paused.');
        }

        $this->recurringModel->update($id, ['status' => 'paused']);
        $this->logActivity('meeting', 'update', $id, 'Paused recurring meeting: ' . $template['title']);

        return redirect()->to('/recurring-meetings')->with('success', 'Recurring meeting paused. No further meetings will be created until resumed.');
    }

    public function resume(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-meetings')->with('error', 'Recurring meeting not found.');
        }
        if ($template['status'] !== 'paused') {
            return redirect()->to('/recurring-meetings')->with('error', 'Only a paused recurring meeting can be resumed.');
        }

        $data = ['status' => 'active'];
        if ($template['next_generation_date'] !== null && $template['next_generation_date'] < date('Y-m-d')) {
            $data['next_generation_date'] = date('Y-m-d');
        }

        $this->recurringModel->update($id, $data);
        $this->logActivity('meeting', 'update', $id, 'Resumed recurring meeting: ' . $template['title']);

        return redirect()->to('/recurring-meetings')->with('success', 'Recurring meeting resumed.');
    }

    public function cancel(int $id)
    {
        $template = $this->recurringModel->find($id);

        if ($template === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $template['company_id'])) {
            return redirect()->to('/recurring-meetings')->with('error', 'Recurring meeting not found.');
        }
        if (in_array($template['status'], ['completed', 'cancelled'], true)) {
            return redirect()->to('/recurring-meetings')->with('error', 'This recurring meeting has already ended.');
        }

        $this->recurringModel->update($id, ['status' => 'cancelled', 'next_generation_date' => null]);
        $this->logActivity('meeting', 'update', $id, 'Cancelled recurring meeting: ' . $template['title']);

        return redirect()->to('/recurring-meetings')->with('success', 'Recurring meeting cancelled. Already-scheduled meetings are unaffected.');
    }

    /**
     * Manual, on-demand catch-up — same reasoning as
     * RecurringExpenseController::generateNow().
     */
    public function generateNow()
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        $result = $companyScope !== null
            ? (new RecurringMeetingGenerator())->runForCompany($companyScope)
            : (new RecurringMeetingGenerator())->runAll();

        if ($result['meetingsGenerated'] > 0) {
            $this->logActivity('meeting', 'create', null, "Generated {$result['meetingsGenerated']} meeting(s) from {$result['templatesProcessed']} recurring template(s).");
        }

        $message = $result['meetingsGenerated'] > 0
            ? "Generated {$result['meetingsGenerated']} meeting(s) from {$result['templatesProcessed']} recurring template(s)."
            : 'Nothing due to generate right now.';

        return redirect()->to('/recurring-meetings')->with('success', $message);
    }

    private function formData(?array $template): array
    {
        return [
            'title'      => $template ? 'Edit Recurring Meeting' : 'New Recurring Meeting',
            'navActive'  => 'recurring-meetings',
            'template'   => $template,
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'frequencies' => RecurringMeetingModel::FREQUENCIES,
            'users'      => $this->scopedUserOptions($this->userModel->listForOptions()),
            'selectedParticipantIds' => [],
        ];
    }

    /**
     * Same reasoning as RecurringExpenseController::validateEndCondition()
     * — end_type-conditional fields can't be a single declarative rule.
     */
    private function validateEndCondition(): ?string
    {
        $endType = $this->request->getPost('end_type');

        if ($endType === 'end_date') {
            $endDate   = $this->request->getPost('end_date');
            $startDate = $this->request->getPost('start_date');
            if (! $endDate) {
                return 'End date is required when ending by date.';
            }
            if ($endDate < $startDate) {
                return 'End date cannot be before the start date.';
            }

            return null;
        }

        if ($endType === 'occurrences') {
            $occurrences = $this->request->getPost('occurrences_total');
            if (! $occurrences || (int) $occurrences < 1) {
                return 'Number of occurrences must be at least 1.';
            }

            return null;
        }

        return 'Choose whether this recurring meeting ends by date or by number of occurrences.';
    }
}
