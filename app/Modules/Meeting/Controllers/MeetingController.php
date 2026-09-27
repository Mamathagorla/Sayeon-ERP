<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\Meeting\Models\MeetingActionItemModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;

class MeetingController extends BaseController
{
    protected MeetingModel $meetingModel;
    protected MeetingParticipantModel $participantModel;
    protected MeetingActionItemModel $actionItemModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;
    protected DepartmentModel $departmentModel;

    public function __construct()
    {
        $this->meetingModel     = new MeetingModel();
        $this->participantModel = new MeetingParticipantModel();
        $this->actionItemModel  = new MeetingActionItemModel();
        $this->companyModel     = new CompanyModel();
        $this->userModel        = new UserModel();
        $this->departmentModel  = new DepartmentModel();
    }

    public function index()
    {
        $filters = $this->request->getGet(['company_id', 'upcoming']) ?? [];

        // Employee sees only meetings they're a participant in, same
        // personal-scope treatment as Tasks and the Dashboard.
        $isPersonalScope = session('roleSlug') === 'employee';
        if ($isPersonalScope) {
            $filters['participant_user_id'] = session('userId');
        }

        $filters = array_filter($filters);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Meeting\index', [
            'title'           => $isPersonalScope ? 'My Meetings' : 'Meetings',
            'navActive'       => 'meetings',
            'isPersonalScope' => $isPersonalScope,
            'meetings'        => $this->meetingModel->filtered($filters)->findAll(),
            'companies'       => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'filters'         => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Meeting\form', [
            'title'     => 'Schedule Meeting',
            'navActive' => 'meetings',
            'meeting'   => null,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->meetingModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only schedule meetings for your own company.');
        }

        $id = $this->meetingModel->insert($this->meetingPayload(true));
        $this->logActivity('meeting', 'create', $id, 'Scheduled meeting: ' . $this->request->getPost('title'));

        return redirect()->to('/meetings/' . $id)->with('success', 'Meeting scheduled.');
    }

    public function show(int $id)
    {
        $meeting = $this->meetingModel->withCompany($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        return view('App\Modules\Meeting\show', [
            'title'        => $meeting['title'],
            'navActive'    => 'meetings',
            'meeting'      => $meeting,
            'participants' => $this->participantModel->forMeeting($id),
            'participantModel' => $this->participantModel,
            'actionItems'  => $this->actionItemModel->forMeeting($id),
            'users'        => $this->scopedUserOptions($this->userModel->listForOptions()),
            'departments'  => $this->departmentModel->optionsList(),
        ]);
    }

    public function edit(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        return view('App\Modules\Meeting\form', [
            'title'     => 'Edit Meeting',
            'navActive' => 'meetings',
            'meeting'   => $meeting,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
        ]);
    }

    public function update(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->validate($this->meetingModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign meetings to your own company.');
        }

        $this->meetingModel->update($id, $this->meetingPayload(false));
        $this->logActivity('meeting', 'update', $id, 'Updated meeting #' . $id);

        return redirect()->to('/meetings/' . $id)->with('success', 'Meeting updated.');
    }

    public function delete(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $this->meetingModel->delete($id);
        $this->logActivity('meeting', 'delete', $id, 'Deleted meeting #' . $id);

        return redirect()->to('/meetings')->with('success', 'Meeting deleted.');
    }

    public function updateMom(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $this->meetingModel->update($id, ['mom' => $this->request->getPost('mom')]);
        $this->logActivity('meeting', 'update', $id, 'Updated minutes of meeting for #' . $id);

        return redirect()->to('/meetings/' . $id)->with('success', 'Minutes of Meeting saved.');
    }

    private function meetingPayload(bool $isNew): array
    {
        $data = [
            'company_id'   => (int) $this->request->getPost('company_id'),
            'title'        => $this->request->getPost('title'),
            'agenda'       => $this->request->getPost('agenda'),
            'meeting_date' => $this->request->getPost('meeting_date'),
            'start_time'   => $this->request->getPost('start_time') ?: null,
            'end_time'     => $this->request->getPost('end_time') ?: null,
            'location'     => $this->request->getPost('location'),
        ];

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }
}
