<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Onboarding\Models\EvalCriterionModel;
use App\Modules\Onboarding\Models\QuestionModel;
use App\Modules\Onboarding\Models\RoleGroupModel;

/**
 * Where HR configures which questions the candidate form asks for which
 * kind of role, and which criteria the HR evaluation offers. A role group
 * is matched to a candidate by keywords against the job title, department
 * and job description (CandidateFormService::roleGroupFor()).
 *
 * Shared defaults (company_id NULL) are read-only for HR: "Customise for
 * my company" copies one into the company, and the company copy then
 * overrides the default for that company only - so one company's edits
 * can never change another company's form. Super Admin may edit the
 * shared defaults themselves. HR / Super Admin only, same as phases.
 */
class OnboardingRoleGroupController extends BaseController
{
    private const HR_ROLES = ['hr', 'super_admin'];

    protected RoleGroupModel $groupModel;
    protected QuestionModel $questionModel;
    protected EvalCriterionModel $criterionModel;

    public function __construct()
    {
        $this->groupModel     = new RoleGroupModel();
        $this->questionModel  = new QuestionModel();
        $this->criterionModel = new EvalCriterionModel();
    }

    public function index()
    {
        if (($denied = $this->denyUnlessHr('/hr/onboarding')) !== null) {
            return $denied;
        }

        $scope  = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $groups = $scope === null
            ? $this->groupModel->orderBy('company_id', 'ASC')->orderBy('priority', 'ASC')->orderBy('id', 'ASC')->findAll()
            : $this->groupModel->visibleTo($scope);

        $counts = [];
        foreach ($this->questionModel->select('role_group_id, COUNT(*) as total')->groupBy('role_group_id')->findAll() as $row) {
            $counts[(int) $row['role_group_id']] = (int) $row['total'];
        }

        // A company copy hides the shared default with the same slug for that company.
        $overridden = [];
        foreach ($groups as $g) {
            if ($g['company_id'] !== null && $scope !== null) {
                $overridden[$g['slug']] = true;
            }
        }

        $companyNames = [];
        foreach ((new CompanyModel())->optionsList() as $company) {
            $companyNames[(int) $company['id']] = $company['name'];
        }

        return view('App\Modules\Onboarding\role_groups', [
            'title'        => 'Candidate Questions',
            'navActive'    => 'hr-onboarding',
            'groups'       => $groups,
            'questionCounts' => $counts,
            'overridden'   => $overridden,
            'scope'        => $scope,
            'companyNames' => $companyNames,
            'canEdit'      => fn (array $g) => $this->canEdit($g),
        ]);
    }

    public function create()
    {
        if (($denied = $this->denyUnlessHr('/hr/onboarding/role-groups')) !== null) {
            return $denied;
        }

        return view('App\Modules\Onboarding\role_group_form', $this->formData(null));
    }

    public function store()
    {
        if (($denied = $this->denyUnlessHr('/hr/onboarding/role-groups')) !== null) {
            return $denied;
        }

        $scope     = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $companyId = $scope;
        if ($companyId === null) {
            // Super Admin viewing "all companies": an empty choice means a shared default.
            $chosen    = (int) $this->request->getPost('company_id');
            $companyId = $chosen > 0 ? $chosen : null;
            if ($companyId !== null && (new CompanyModel())->find($companyId) === null) {
                return redirect()->back()->withInput()->with('error', 'Select a valid company.');
            }
        }
        if ($companyId === 0) {
            return redirect()->to('/hr/onboarding/role-groups')->with('error', 'Your account is not linked to a company yet.');
        }

        $rules = $this->groupModel->getValidationRules();
        unset($rules['slug']); // derived from the name below
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $slug = $this->slugFor((string) $this->request->getPost('name'));
        if ($this->slugTaken($slug, $companyId, null)) {
            return redirect()->back()->withInput()->with('error', 'A role group with a similar name already exists here. Choose a different name.');
        }

        $id = $this->groupModel->insert($this->groupData() + ['company_id' => $companyId, 'slug' => $slug]);
        $this->logActivity('onboarding', 'create', (int) $id, 'Added candidate question set: ' . $this->request->getPost('name'));

        return redirect()->to('/hr/onboarding/role-groups/' . $id . '/edit')->with('success', 'Role group created. Now add its questions.');
    }

    public function edit(int $id)
    {
        $group = $this->groupFor($id, false);
        if (! is_array($group)) {
            return $group;
        }

        return view('App\Modules\Onboarding\role_group_form', $this->formData($group));
    }

    public function update(int $id)
    {
        $group = $this->groupFor($id, true);
        if (! is_array($group)) {
            return $group;
        }

        $rules = $this->groupModel->getValidationRules();
        unset($rules['slug']);
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->groupModel->update($id, $this->groupData());
        $this->logActivity('onboarding', 'update', $id, 'Updated candidate question set: ' . $this->request->getPost('name'));

        return redirect()->to('/hr/onboarding/role-groups/' . $id . '/edit')->with('success', 'Role group saved.');
    }

    /** Copies a shared default (with its questions and criteria) into the viewer's company. */
    public function cloneForCompany(int $id)
    {
        $group = $this->groupFor($id, false);
        if (! is_array($group)) {
            return $group;
        }

        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($scope === null || $scope === 0) {
            return redirect()->to('/hr/onboarding/role-groups')->with('error', 'Pick a company in the top bar first - a company copy belongs to one company.');
        }
        if ($this->slugTaken($group['slug'], $scope, null)) {
            return redirect()->to('/hr/onboarding/role-groups')->with('error', 'This company already has its own copy of that role group.');
        }

        $db = db_connect();
        $db->transStart();
        $newId = (int) $this->groupModel->insert([
            'company_id' => $scope, 'name' => $group['name'], 'slug' => $group['slug'], 'description' => $group['description'],
            'keywords' => $group['keywords'], 'features' => $group['features'], 'priority' => $group['priority'], 'is_active' => $group['is_active'],
        ]);
        foreach ($this->questionModel->forGroup($id) as $q) {
            $this->questionModel->insert([
                'role_group_id' => $newId, 'question_key' => $q['question_key'], 'label' => $q['label'], 'field_type' => $q['field_type'],
                'options' => $q['options'], 'help_text' => $q['help_text'], 'is_required' => $q['is_required'], 'applies_to' => $q['applies_to'],
                'sort_order' => $q['sort_order'], 'is_active' => $q['is_active'],
            ]);
        }
        foreach ($this->criterionModel->forGroup($id) as $c) {
            $this->criterionModel->insert([
                'role_group_id' => $newId, 'label' => $c['label'], 'description' => $c['description'], 'sort_order' => $c['sort_order'], 'is_active' => $c['is_active'],
            ]);
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/hr/onboarding/role-groups')->with('error', 'Could not create the company copy. Nothing was saved.');
        }

        $this->logActivity('onboarding', 'create', $newId, 'Customised candidate question set for the company: ' . $group['name']);

        return redirect()->to('/hr/onboarding/role-groups/' . $newId . '/edit')->with('success', 'Company copy created - edit it freely; the shared default is unchanged.');
    }

    public function destroy(int $id)
    {
        $group = $this->groupFor($id, true);
        if (! is_array($group)) {
            return $group;
        }
        if ($group['company_id'] === null && $group['slug'] === 'general') {
            return redirect()->to('/hr/onboarding/role-groups')->with('error', 'The shared General group is the fallback for every other role and cannot be deleted.');
        }

        $this->groupModel->delete($id);
        $this->logActivity('onboarding', 'delete', $id, 'Deleted candidate question set: ' . $group['name']);

        return redirect()->to('/hr/onboarding/role-groups')->with('success', 'Role group deleted. Answers already given by candidates are kept.');
    }

    public function saveQuestion(int $groupId)
    {
        $group = $this->groupFor($groupId, true);
        if (! is_array($group)) {
            return $group;
        }
        $back = '/hr/onboarding/role-groups/' . $groupId . '/edit';

        $rules = $this->questionModel->getValidationRules();
        if (! $this->validate($rules)) {
            return redirect()->to($back)->withInput()->with('errors', $this->validator->getErrors());
        }

        $type    = (string) $this->request->getPost('field_type');
        $options = trim(str_replace("\r\n", "\n", (string) $this->request->getPost('options')));
        if ($type === 'select') {
            $lines = array_filter(array_map('trim', explode("\n", $options)));
            if (count($lines) < 2) {
                return redirect()->to($back)->withInput()->with('error', 'A dropdown question needs at least two options, one per line.');
            }
            $options = implode("\n", array_unique($lines));
        } else {
            $options = '';
        }

        $data = [
            'label'       => trim((string) $this->request->getPost('label')),
            'field_type'  => $type,
            'options'     => $options !== '' ? $options : null,
            'help_text'   => trim((string) $this->request->getPost('help_text')) ?: null,
            'is_required' => $this->request->getPost('is_required') === '1' ? 1 : 0,
            'applies_to'  => (string) $this->request->getPost('applies_to'),
            'sort_order'  => (int) ($this->request->getPost('sort_order') ?: 0),
            'is_active'   => $this->request->getPost('is_active') === '0' ? 0 : 1,
        ];

        $questionId = (int) $this->request->getPost('question_id');
        if ($questionId > 0) {
            $existing = $this->questionModel->find($questionId);
            if ($existing === null || (int) $existing['role_group_id'] !== $groupId) {
                return redirect()->to($back)->with('error', 'Question not found.');
            }
            $this->questionModel->update($questionId, $data);
            $message = 'Question updated.';
        } else {
            $this->questionModel->insert($data + ['role_group_id' => $groupId, 'question_key' => 'q_' . bin2hex(random_bytes(5))]);
            $message = 'Question added.';
        }
        $this->logActivity('onboarding', 'update', $groupId, 'Edited candidate questions for: ' . $group['name']);

        return redirect()->to($back)->with('success', $message);
    }

    public function deleteQuestion(int $groupId, int $questionId)
    {
        $group = $this->groupFor($groupId, true);
        if (! is_array($group)) {
            return $group;
        }

        $question = $this->questionModel->find($questionId);
        if ($question !== null && (int) $question['role_group_id'] === $groupId) {
            $this->questionModel->delete($questionId);
            $this->logActivity('onboarding', 'update', $groupId, 'Removed a candidate question from: ' . $group['name']);
        }

        return redirect()->to('/hr/onboarding/role-groups/' . $groupId . '/edit')->with('success', 'Question removed. Existing candidate answers are kept.');
    }

    public function saveCriterion(int $groupId)
    {
        $group = $this->groupFor($groupId, true);
        if (! is_array($group)) {
            return $group;
        }
        $back = '/hr/onboarding/role-groups/' . $groupId . '/edit';

        if (! $this->validate($this->criterionModel->getValidationRules())) {
            return redirect()->to($back)->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'label'       => trim((string) $this->request->getPost('label')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'sort_order'  => (int) ($this->request->getPost('sort_order') ?: 0),
            'is_active'   => $this->request->getPost('is_active') === '0' ? 0 : 1,
        ];

        $criterionId = (int) $this->request->getPost('criterion_id');
        if ($criterionId > 0) {
            $existing = $this->criterionModel->find($criterionId);
            if ($existing === null || (int) $existing['role_group_id'] !== $groupId) {
                return redirect()->to($back)->with('error', 'Criterion not found.');
            }
            $this->criterionModel->update($criterionId, $data);
        } else {
            $this->criterionModel->insert($data + ['role_group_id' => $groupId]);
        }
        $this->logActivity('onboarding', 'update', $groupId, 'Edited evaluation criteria for: ' . $group['name']);

        return redirect()->to($back)->with('success', 'Criterion saved.');
    }

    public function deleteCriterion(int $groupId, int $criterionId)
    {
        $group = $this->groupFor($groupId, true);
        if (! is_array($group)) {
            return $group;
        }

        $criterion = $this->criterionModel->find($criterionId);
        if ($criterion !== null && (int) $criterion['role_group_id'] === $groupId) {
            $this->criterionModel->delete($criterionId);
            $this->logActivity('onboarding', 'update', $groupId, 'Removed an evaluation criterion from: ' . $group['name']);
        }

        return redirect()->to('/hr/onboarding/role-groups/' . $groupId . '/edit')->with('success', 'Criterion removed. Evaluations already saved keep their scores.');
    }

    // ------------------------------------------------------------------

    private function denyUnlessHr(string $to)
    {
        return in_array(session('roleSlug'), self::HR_ROLES, true)
            ? null
            : redirect()->to($to)->with('error', 'Only HR can manage candidate questions.');
    }

    /**
     * The group the viewer may open: visible to their company (own or shared
     * default). With $forWrite also requires that they may change it - HR
     * only edits their own company's copies; Super Admin may edit any.
     */
    private function groupFor(int $id, bool $forWrite)
    {
        if (($denied = $this->denyUnlessHr('/hr/onboarding')) !== null) {
            return $denied;
        }

        $group = $this->groupModel->find($id);
        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        if ($group === null || ($group['company_id'] !== null && $scope !== null && (int) $group['company_id'] !== $scope)) {
            return redirect()->to('/hr/onboarding/role-groups')->with('error', 'Role group not found.');
        }
        if ($forWrite && ! $this->canEdit($group)) {
            return redirect()->to('/hr/onboarding/role-groups/' . $id . '/edit')->with('error', 'This is a shared default. Use "Customise for my company" to make an editable copy.');
        }

        return $group;
    }

    private function canEdit(array $group): bool
    {
        if (session('roleSlug') === 'super_admin') {
            return true;
        }

        return $group['company_id'] !== null && (int) $group['company_id'] === $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
    }

    private function groupData(): array
    {
        $features = array_values(array_intersect((array) $this->request->getPost('features'), array_keys(RoleGroupModel::FEATURES)));

        return [
            'name'        => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'keywords'    => trim((string) $this->request->getPost('keywords')) ?: null,
            'features'    => implode(',', $features),
            'priority'    => (int) ($this->request->getPost('priority') ?: 100),
            'is_active'   => $this->request->getPost('is_active') === '1' ? 1 : 0,
        ];
    }

    private function slugFor(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
        $slug = trim($slug, '-');

        return substr($slug !== '' ? $slug : 'group', 0, 60);
    }

    private function slugTaken(string $slug, ?int $companyId, ?int $exceptId): bool
    {
        $builder = $this->groupModel->where('slug', $slug);
        $companyId === null ? $builder->where('company_id', null) : $builder->where('company_id', $companyId);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    private function formData(?array $group): array
    {
        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        return [
            'title'      => $group ? 'Edit Role Group' : 'Add Role Group',
            'navActive'  => 'hr-onboarding',
            'group'      => $group,
            'editable'   => $group === null || $this->canEdit($group),
            'questions'  => $group ? $this->questionModel->forGroup((int) $group['id']) : [],
            'criteria'   => $group ? $this->criterionModel->forGroup((int) $group['id']) : [],
            'features'   => RoleGroupModel::FEATURES,
            'types'      => QuestionModel::TYPES,
            'appliesTo'  => QuestionModel::APPLIES_TO,
            'companies'  => $group === null && $scope === null ? (new CompanyModel())->optionsList() : [],
            'canClone'   => $group !== null && $group['company_id'] === null && $scope !== null && $scope > 0,
        ];
    }
}
