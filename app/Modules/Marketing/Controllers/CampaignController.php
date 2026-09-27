<?php

namespace App\Modules\Marketing\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Marketing\Models\CampaignModel;

class CampaignController extends BaseController
{
    protected CampaignModel $campaignModel;
    protected CompanyModel $companyModel;

    public function __construct()
    {
        $this->campaignModel = new CampaignModel();
        $this->companyModel  = new CompanyModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'channel', 'status']) ?? []);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        $campaigns = $this->campaignModel->filtered($filters)->findAll();

        $totalBudget  = array_sum(array_column($campaigns, 'budget'));
        $totalRevenue = array_sum(array_column($campaigns, 'revenue'));

        return view('App\Modules\Marketing\index', [
            'title'       => 'Marketing Campaigns',
            'navActive'   => 'campaigns',
            'campaigns'   => $campaigns,
            'companies'   => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'channels'    => CampaignModel::CHANNELS,
            'statuses'    => CampaignModel::STATUSES,
            'filters'     => $filters,
            'totalBudget'  => $totalBudget,
            'totalRevenue' => $totalRevenue,
            'overallRoi'   => $totalBudget > 0 ? round((($totalRevenue - $totalBudget) / $totalBudget) * 100, 1) : null,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Marketing\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->campaignModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only create campaigns for your own company.');
        }

        $id = $this->campaignModel->insert($this->payload(true));
        $this->logActivity('campaign', 'create', $id, 'Created campaign: ' . $this->request->getPost('name'));

        return redirect()->to('/campaigns/' . $id)->with('success', 'Campaign created.');
    }

    public function show(int $id)
    {
        $campaign = $this->campaignModel->withRelations($id);

        if ($campaign === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $campaign['company_id'])) {
            return redirect()->to('/campaigns')->with('error', 'Campaign not found.');
        }

        return view('App\Modules\Marketing\show', [
            'title'     => $campaign['name'],
            'navActive' => 'campaigns',
            'campaign'  => $campaign,
            'roi'       => $this->campaignModel->roiPercent($campaign),
            'conversionRate' => $this->campaignModel->conversionRatePercent($campaign),
        ]);
    }

    public function edit(int $id)
    {
        $campaign = $this->campaignModel->find($id);

        if ($campaign === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $campaign['company_id'])) {
            return redirect()->to('/campaigns')->with('error', 'Campaign not found.');
        }

        return view('App\Modules\Marketing\form', $this->formData($campaign));
    }

    public function update(int $id)
    {
        $campaign = $this->campaignModel->find($id);

        if ($campaign === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $campaign['company_id'])) {
            return redirect()->to('/campaigns')->with('error', 'Campaign not found.');
        }

        if (! $this->validate($this->campaignModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign campaigns to your own company.');
        }

        $this->campaignModel->update($id, $this->payload(false));
        $this->logActivity('campaign', 'update', $id, 'Updated campaign #' . $id);

        return redirect()->to('/campaigns/' . $id)->with('success', 'Campaign updated.');
    }

    public function delete(int $id)
    {
        $campaign = $this->campaignModel->find($id);

        if ($campaign === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $campaign['company_id'])) {
            return redirect()->to('/campaigns')->with('error', 'Campaign not found.');
        }

        $this->campaignModel->delete($id);
        $this->logActivity('campaign', 'delete', $id, 'Deleted campaign #' . $id);

        return redirect()->to('/campaigns')->with('success', 'Campaign deleted.');
    }

    private function formData(?array $campaign): array
    {
        return [
            'title'     => $campaign ? 'Edit Campaign' : 'Add Campaign',
            'navActive' => 'campaigns',
            'campaign'  => $campaign,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'channels'  => CampaignModel::CHANNELS,
            'statuses'  => CampaignModel::STATUSES,
        ];
    }

    private function payload(bool $isNew): array
    {
        $data = [
            'company_id'  => (int) $this->request->getPost('company_id'),
            'name'        => $this->request->getPost('name'),
            'channel'     => $this->request->getPost('channel'),
            'budget'      => $this->request->getPost('budget') ?: 0,
            'start_date'  => $this->request->getPost('start_date') ?: null,
            'end_date'    => $this->request->getPost('end_date') ?: null,
            'leads'       => $this->request->getPost('leads') ?: 0,
            'conversions' => $this->request->getPost('conversions') ?: 0,
            'revenue'     => $this->request->getPost('revenue') ?: 0,
            'status'      => $this->request->getPost('status') ?: 'draft',
            'notes'       => $this->request->getPost('notes'),
        ];

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }
}
