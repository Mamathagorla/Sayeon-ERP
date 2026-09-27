<?php

namespace App\Modules\Onboarding\Controllers;

use App\Controllers\BaseController;
use App\Modules\Onboarding\Models\OnboardingPhaseModel;

/**
 * Add/Edit/Reorder/Delete for the checklist phases (Pre-Joining/Joining/
 * Post-Joining, plus anything HR adds) shown on every candidate's
 * Onboarding tab — split out from OnboardingController the same way
 * OnboardingTaskController already splits out task actions, since this
 * is authorized differently: HR (and Super Admin) only, never Manager/
 * Company Admin/Accountant even though they hold onboarding.edit too
 * (same HR_ROLES pattern OnboardingTaskController uses, just narrower —
 * that one lets any owner_role act on their own tasks; this one is HR-
 * only because phases are pipeline-wide configuration, not one person's
 * checklist item).
 */
class OnboardingPhaseController extends BaseController
{
    private const HR_ROLES = ['hr', 'super_admin'];

    protected OnboardingPhaseModel $phaseModel;

    public function __construct()
    {
        $this->phaseModel = new OnboardingPhaseModel();
    }

    public function index()
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/onboarding')->with('error', 'Only HR can manage onboarding phases.');
        }

        $phases = $this->phaseModel->ordered();

        return view('App\Modules\Onboarding\phases', [
            'title'   => 'Onboarding Phases',
            'navActive' => 'hr-onboarding',
            'phases'  => $phases,
            'taskCounts' => $this->taskCountsFor($phases),
        ]);
    }

    public function create()
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Only HR can add a phase.');
        }

        return view('App\Modules\Onboarding\phase_form', [
            'title'      => 'Add Phase',
            'navActive'  => 'hr-onboarding',
            'phase'      => null,
            'nextOrder'  => $this->phaseModel->nextSortOrder(),
        ]);
    }

    public function store()
    {
        if (! $this->isHr()) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Only HR can add a phase.');
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

        $this->logActivity('onboarding', 'create', $id, 'Added onboarding phase: ' . $this->request->getPost('name'));

        return redirect()->to('/hr/onboarding/phases')->with('success', 'Phase added.');
    }

    public function edit(int $id)
    {
        $phase = $this->phaseModel->find($id);

        if ($phase === null) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Phase not found.');
        }
        if (! $this->isHr()) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Only HR can edit a phase.');
        }

        return view('App\Modules\Onboarding\phase_form', [
            'title'     => 'Edit Phase',
            'navActive' => 'hr-onboarding',
            'phase'     => $phase,
            'nextOrder' => (int) $phase['sort_order'],
        ]);
    }

    public function update(int $id)
    {
        $phase = $this->phaseModel->find($id);

        if ($phase === null) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Phase not found.');
        }
        if (! $this->isHr()) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Only HR can edit a phase.');
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

        $this->logActivity('onboarding', 'update', $id, 'Updated onboarding phase: ' . $this->request->getPost('name'));

        return redirect()->to('/hr/onboarding/phases')->with('success', 'Phase updated.');
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
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Only HR can reorder phases.');
        }
        if ($this->phaseModel->find($id) === null) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Phase not found.');
        }

        $this->phaseModel->reorder($id, $direction);

        return redirect()->to('/hr/onboarding/phases');
    }

    public function destroy(int $id)
    {
        $phase = $this->phaseModel->find($id);

        if ($phase === null) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Phase not found.');
        }
        if (! $this->isHr()) {
            return redirect()->to('/hr/onboarding/phases')->with('error', 'Only HR can delete a phase.');
        }

        $blocking = $this->phaseModel->blockingTaskCount($id);
        if ($blocking > 0) {
            return redirect()->to('/hr/onboarding/phases')->with(
                'error',
                'Can\'t delete "' . $phase['name'] . '" — it still has ' . $blocking . ' active or completed checklist item(s). Deactivate it instead, or complete/skip those items first.'
            );
        }

        $this->phaseModel->delete($id);
        $this->logActivity('onboarding', 'delete', $id, 'Deleted onboarding phase: ' . $phase['name']);

        return redirect()->to('/hr/onboarding/phases')->with('success', 'Phase deleted.');
    }

    private function isHr(): bool
    {
        return in_array(session('roleSlug'), self::HR_ROLES, true);
    }

    /**
     * Explicit query rather than an is_unique[...,id,{id}] model rule —
     * that placeholder only resolves from a same-named field actually
     * present in the submitted request data, which this form has no
     * reason to send, so it can't reliably exclude the phase's own row
     * while editing. Two phases sharing a name matters here specifically
     * because the checklist groups tasks by phase NAME
     * (OnboardingController::show()) — a collision would silently merge
     * their tasks into one visual section.
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
     * reserved at $desiredOrder for the phase being added/moved, so
     * "Order" on the form always produces an unambiguous position
     * instead of allowing colliding sort_order values. Returns the
     * (possibly clamped) order the caller should save on its own row.
     * $excludeId leaves the phase being edited out of the "others" list
     * it's reordering around.
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
        $counts = $db->table('onboarding_tasks')
            ->select('phase_id, COUNT(*) as total')
            ->whereIn('phase_id', array_column($phases, 'id'))
            ->groupBy('phase_id')
            ->get()
            ->getResultArray();

        return array_column($counts, 'total', 'phase_id');
    }
}
