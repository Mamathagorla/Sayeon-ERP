<?php

namespace App\Modules\Offboarding\Controllers;

use App\Modules\Offboarding\Models\OffboardingPhaseModel;
use App\Controllers\BaseController;

/**
 * Add/Edit/Reorder/Delete for the process phases (Exit Request &
 * Approval / Handover & Transition / … / Exit Completed, plus anything
 * HR adds) shown on every exit record's Offboarding tab — split out from
 * OffboardingController the same way OffboardingTaskController already
 * splits out task actions. HR (and Super Admin) only, same HR_ROLES
 * pattern as OnboardingPhaseController — Manager/Company Admin/
 * Accountant/Employee hold offboarding.edit too, but only to complete
 * their own checklist items, never to reconfigure the pipeline itself.
 */
class OffboardingPhaseController extends BaseController
{
    private const HR_ROLES = ['hr', 'super_admin'];

    /**
     * "Exit Completed" is rendered from the record's own status field
     * (see Offboarding/Views/show.php), not from any task — it never has
     * real tasks under it, so blockingTaskCount() alone would never stop
     * it from being deleted even though the exit-status summary row that
     * every record's Offboarding tab relies on would silently disappear.
     * Deleting it specifically is refused; deactivating it (which also
     * hides that row, but reversibly) still works.
     */
    private const PROTECTED_LEGACY_KEY = 'exit_completed';

    protected OffboardingPhaseModel $phaseModel;

    public function __construct()
    {
        $this->phaseModel = new OffboardingPhaseModel();
    }

    public function index()
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding')->with('error', 'Only HR can manage offboarding phases.');
        }

        $phases = $this->phaseModel->ordered();

        return view('App\Modules\Offboarding\phases', [
            'title'      => 'Offboarding Phases',
            'navActive'  => 'hr-offboarding',
            'phases'     => $phases,
            'taskCounts' => $this->taskCountsFor($phases),
        ]);
    }

    public function create()
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Only HR can add a phase.');
        }

        return view('App\Modules\Offboarding\phase_form', [
            'title'     => 'Add Phase',
            'navActive' => 'hr-offboarding',
            'phase'     => null,
            'nextOrder' => $this->phaseModel->nextSortOrder(),
        ]);
    }

    public function store()
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Only HR can add a phase.');
        }

        if (! $this->validate($this->phaseModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($this->duplicateName($this->request->getPost('name'), null)) {
            return redirect()->back()->withInput()->with('error', 'A phase with that name already exists.');
        }

        $order = $this->reposition(null, (int) ($this->request->getPost('sort_order') ?: $this->phaseModel->nextSortOrder()));

        $id = $this->phaseModel->insert([
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description') ?: null,
            'sort_order'  => $order,
            'is_active'   => $this->request->getPost('is_active') === '1' ? 1 : 0,
        ]);

        $this->logActivity('offboarding', 'create', $id, 'Added offboarding phase: ' . $this->request->getPost('name'));

        return redirect()->to('/hr/offboarding/phases')->with('success', 'Phase added.');
    }

    public function edit(int $id)
    {
        $phase = $this->phaseModel->find($id);

        if ($phase === null) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Phase not found.');
        }
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Only HR can edit a phase.');
        }

        return view('App\Modules\Offboarding\phase_form', [
            'title'     => 'Edit Phase',
            'navActive' => 'hr-offboarding',
            'phase'     => $phase,
            'nextOrder' => (int) $phase['sort_order'],
        ]);
    }

    public function update(int $id)
    {
        $phase = $this->phaseModel->find($id);

        if ($phase === null) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Phase not found.');
        }
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Only HR can edit a phase.');
        }

        if (! $this->validate($this->phaseModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        if ($this->duplicateName($this->request->getPost('name'), $id)) {
            return redirect()->back()->withInput()->with('error', 'A phase with that name already exists.');
        }

        $requestedOrder = (int) ($this->request->getPost('sort_order') ?: $phase['sort_order']);
        $order = $requestedOrder === (int) $phase['sort_order']
            ? $requestedOrder
            : $this->reposition($id, $requestedOrder);

        $this->phaseModel->update($id, [
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description') ?: null,
            'sort_order'  => $order,
            'is_active'   => $this->request->getPost('is_active') === '1' ? 1 : 0,
        ]);

        $this->logActivity('offboarding', 'update', $id, 'Updated offboarding phase: ' . $this->request->getPost('name'));

        return redirect()->to('/hr/offboarding/phases')->with('success', 'Phase updated.');
    }

    public function moveUp(int $id)
    {
        return $this->move($id, 'up');
    }

    public function moveDown(int $id)
    {
        return $this->move($id, 'down');
    }

    private function move(int $id, string $direction)
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Only HR can reorder phases.');
        }
        if ($this->phaseModel->find($id) === null) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Phase not found.');
        }

        $this->phaseModel->reorder($id, $direction);

        return redirect()->to('/hr/offboarding/phases');
    }

    public function destroy(int $id)
    {
        $phase = $this->phaseModel->find($id);

        if ($phase === null) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Phase not found.');
        }
        if (! $this->isHr()) {
            return redirect()->to('/hr/offboarding/phases')->with('error', 'Only HR can delete a phase.');
        }

        if ($phase['legacy_key'] === self::PROTECTED_LEGACY_KEY) {
            return redirect()->to('/hr/offboarding/phases')->with(
                'error',
                '"' . $phase['name'] . '" shows every exit\'s final status and can\'t be deleted — deactivate it instead if you don\'t want it shown.'
            );
        }

        $blocking = $this->phaseModel->blockingTaskCount($id);
        if ($blocking > 0) {
            return redirect()->to('/hr/offboarding/phases')->with(
                'error',
                'Can\'t delete "' . $phase['name'] . '" — it still has ' . $blocking . ' active or completed checklist item(s). Deactivate it instead, or complete/skip those items first.'
            );
        }

        $this->phaseModel->delete($id);
        $this->logActivity('offboarding', 'delete', $id, 'Deleted offboarding phase: ' . $phase['name']);

        return redirect()->to('/hr/offboarding/phases')->with('success', 'Phase deleted.');
    }

    private function isHr(): bool
    {
        return in_array(session('roleSlug'), self::HR_ROLES, true);
    }

    /**
     * Explicit query, not an is_unique[...] model rule — see
     * OnboardingPhaseController::duplicateName() for why.
     */
    private function duplicateName(string $name, ?int $excludeId): bool
    {
        $builder = $this->phaseModel->where('name', $name);
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Renumbers every OTHER phase into a clean 1..N sequence with a slot
     * reserved at $desiredOrder for the phase being added/moved — see
     * OnboardingPhaseController::reposition() for the full walk-through.
     */
    private function reposition(?int $excludeId, int $desiredOrder): int
    {
        $others = array_values(array_filter(
            $this->phaseModel->ordered(),
            static fn (array $p) => (int) $p['id'] !== $excludeId
        ));

        $desiredOrder = max(1, min($desiredOrder, count($others) + 1));

        $order = 1;
        foreach ($others as $p) {
            if ($order === $desiredOrder) {
                $order++;
            }
            if ((int) $p['sort_order'] !== $order) {
                $this->phaseModel->update($p['id'], ['sort_order' => $order]);
            }
            $order++;
        }

        return $desiredOrder;
    }

    /**
     * How many tasks (any status) currently sit under each phase — shown
     * on the index list so HR can see at a glance which phases are safe
     * to delete outright versus which are actually in use.
     */
    private function taskCountsFor(array $phases): array
    {
        if ($phases === []) {
            return [];
        }

        $db     = db_connect();
        $counts = $db->table('offboarding_tasks')
            ->select('phase_id, COUNT(*) as total')
            ->whereIn('phase_id', array_column($phases, 'id'))
            ->groupBy('phase_id')
            ->get()
            ->getResultArray();

        return array_column($counts, 'total', 'phase_id');
    }
}
