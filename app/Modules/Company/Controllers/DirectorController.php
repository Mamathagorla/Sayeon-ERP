<?php

namespace App\Modules\Company\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyDirectorModel;
use App\Modules\Company\Models\CompanyModel;

class DirectorController extends BaseController
{
    protected CompanyDirectorModel $directorModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->directorModel = new CompanyDirectorModel();
        $this->companyModel  = new CompanyModel();
    }

    public function store(int $companyId)
    {
        if ($this->outOfScope(['admin'], $companyId) || $this->companyModel->find($companyId) === null) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        $rules = [
            'name'  => 'required|min_length[2]|max_length[150]',
            'din'   => 'permit_empty|regex_match[/^\d+$/]|max_length[30]',
            'email' => 'permit_empty|valid_email',
            'phone' => 'permit_empty|regex_match[/^[0-9]{10}$/]',
        ];
        $messages = [
            'din'   => ['regex_match' => 'DIN must contain digits only.'],
            'phone' => ['regex_match' => 'Phone number must be exactly 10 digits.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $id = $this->directorModel->insert([
            'company_id'     => $companyId,
            'name'           => $this->request->getPost('name'),
            'din'            => $this->request->getPost('din'),
            'designation'    => $this->request->getPost('designation'),
            'email'          => $this->request->getPost('email'),
            'phone'          => $this->request->getPost('phone'),
            'appointed_date' => $this->request->getPost('appointed_date') ?: null,
        ]);

        $this->logActivity('company', 'update', $companyId, 'Added director ' . $this->request->getPost('name'));

        return redirect()->to('/companies/' . $companyId)->with('success', 'Director added.');
    }

    public function edit(int $companyId, int $directorId)
    {
        $director = $this->authorizedDirector($companyId, $directorId);

        if ($director === null) {
            return redirect()->to('/companies/' . $companyId)->with('error', 'Director not found.');
        }

        return view('App\Modules\Company\directors\edit', [
            'title'     => 'Edit Director',
            'navActive' => 'companies',
            'companyId' => $companyId,
            'director'  => $director,
        ]);
    }

    public function update(int $companyId, int $directorId)
    {
        $director = $this->authorizedDirector($companyId, $directorId);

        if ($director === null) {
            return redirect()->to('/companies/' . $companyId)->with('error', 'Director not found.');
        }

        $rules = [
            'name'  => 'required|min_length[2]|max_length[150]',
            'din'   => 'permit_empty|regex_match[/^\d+$/]|max_length[30]',
            'email' => 'permit_empty|valid_email',
            'phone' => 'permit_empty|regex_match[/^[0-9]{10}$/]',
        ];
        $messages = [
            'din'   => ['regex_match' => 'DIN must contain digits only.'],
            'phone' => ['regex_match' => 'Phone number must be exactly 10 digits.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $this->directorModel->update($directorId, [
            'name'           => $this->request->getPost('name'),
            'din'            => $this->request->getPost('din'),
            'designation'    => $this->request->getPost('designation'),
            'email'          => $this->request->getPost('email'),
            'phone'          => $this->request->getPost('phone'),
            'appointed_date' => $this->request->getPost('appointed_date') ?: null,
            'resigned_date'  => $this->request->getPost('resigned_date') ?: null,
        ]);

        $this->logActivity('company', 'update', $companyId, 'Updated director: ' . $this->request->getPost('name'));

        return redirect()->to('/companies/' . $companyId)->with('success', 'Director updated.');
    }

    public function delete(int $companyId, int $directorId)
    {
        if ($this->outOfScope(['admin'], $companyId)) {
            return redirect()->to('/companies')->with('error', 'Company not found.');
        }

        $director = $this->directorModel->find($directorId);

        if ($director === null || (int) $director['company_id'] !== $companyId) {
            return redirect()->to('/companies/' . $companyId)->with('error', 'Director not found.');
        }

        $this->directorModel->delete($directorId);
        $this->logActivity('company', 'update', $companyId, 'Removed director #' . $directorId);

        return redirect()->to('/companies/' . $companyId)->with('success', 'Director removed.');
    }

    private function authorizedDirector(int $companyId, int $directorId): ?array
    {
        if ($this->outOfScope(['admin'], $companyId)) {
            return null;
        }

        $director = $this->directorModel->find($directorId);

        return ($director !== null && (int) $director['company_id'] === $companyId) ? $director : null;
    }
}
