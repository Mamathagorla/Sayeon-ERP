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
        ]);

        return redirect()->to('/hr/leave/types')->with('success', 'Leave type added.');
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
