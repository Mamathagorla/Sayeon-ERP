<?php

namespace App\Modules\HR\Controllers;

use App\Controllers\BaseController;
use App\Modules\HR\Models\LeaveTypeModel;

class LeaveTypeController extends BaseController
{
    protected LeaveTypeModel $leaveTypeModel;

    public function __construct()
    {
        $this->leaveTypeModel = new LeaveTypeModel();
    }

    public function index()
    {
        return view('App\Modules\HR\leave/types', [
            'title'      => 'Leave Types',
            'navActive'  => 'hr-leave',
            'leaveTypes' => $this->leaveTypeModel->optionsList(),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->leaveTypeModel->getValidationRules())) {
            return redirect()->to('/hr/leave/types')->with('errors', $this->validator->getErrors());
        }

        $this->leaveTypeModel->insert([
            'name'         => $this->request->getPost('name'),
            'annual_quota' => $this->request->getPost('annual_quota'),
            'is_paid'      => $this->request->getPost('is_paid') ? 1 : 0,
        ]);

        return redirect()->to('/hr/leave/types')->with('success', 'Leave type added.');
    }

    /**
     * The only field ever needing a change after creation — Paid/Unpaid
     * feeds PayrollController::generate()'s Loss-of-Pay calculation (see
     * AddIsPaidToLeaveTypes), so HR needs a way to correct it without
     * deleting and recreating the type (blocked anyway by isInUse() once
     * any request has used it). No full edit form: every other field is
     * effectively permanent once requests exist against it.
     */
    public function togglePaid(int $id)
    {
        $type = $this->leaveTypeModel->find($id);

        if ($type === null) {
            return redirect()->to('/hr/leave/types')->with('error', 'Leave type not found.');
        }

        $this->leaveTypeModel->update($id, ['is_paid' => $type['is_paid'] ? 0 : 1]);

        return redirect()->to('/hr/leave/types')->with('success', 'Leave type updated.');
    }

    public function delete(int $id)
    {
        if ($this->leaveTypeModel->isInUse($id)) {
            return redirect()->to('/hr/leave/types')->with('error', 'Cannot delete a leave type that already has requests against it.');
        }

        $this->leaveTypeModel->delete($id);

        return redirect()->to('/hr/leave/types')->with('success', 'Leave type deleted.');
    }
}
