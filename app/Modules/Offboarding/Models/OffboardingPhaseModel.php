<?php

namespace App\Modules\Offboarding\Models;

use CodeIgniter\Model;

/**
 * Admin-manageable process phases — see OffboardingPhaseController for
 * the Add/Edit/Reorder/Delete screens, and the
 * 2026-09-22-110001_CreateOffboardingPhasesTable migration's docblock
 * for why legacy_key exists and isn't user-editable.
 */
class OffboardingPhaseModel extends Model
{
    protected $table         = 'offboarding_phases';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'description', 'legacy_key', 'sort_order', 'is_active'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Name uniqueness is enforced in OffboardingPhaseController (a plain
    // explicit query), not here — see OnboardingPhaseModel's identical
    // comment for why an is_unique[...,id,{id}] rule isn't used.
    protected $validationRules = [
        'name'        => 'required|min_length[2]|max_length[100]',
        'description' => 'permit_empty|max_length[500]',
        'sort_order'  => 'permit_empty|integer|greater_than[0]',
    ];

    public function ordered(bool $activeOnly = false): array
    {
        $builder = $this->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC');

        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        return $builder->findAll();
    }

    public function idForLegacyKey(string $key): ?int
    {
        $row = $this->where('legacy_key', $key)->where('is_active', 1)->first();

        return $row['id'] ?? null;
    }

    public function nextSortOrder(): int
    {
        $max = $this->selectMax('sort_order')->first();

        return ((int) ($max['sort_order'] ?? 0)) + 1;
    }

    /**
     * Tasks that would be silently orphaned (in progress or already
     * done) if this phase were deleted — OffboardingPhaseController
     * blocks deletion whenever this is > 0. A phase with only 'skipped'
     * tasks (or none at all — true of the "Exit Completed" phase, which
     * has no task types mapped to it at all) can still be removed,
     * except "Exit Completed" itself — see the controller's own
     * docblock for that one narrower rule.
     */
    public function blockingTaskCount(int $phaseId): int
    {
        return $this->db->table('offboarding_tasks')
            ->where('phase_id', $phaseId)
            ->whereIn('status', ['pending', 'in_progress', 'completed'])
            ->countAllResults();
    }

    /**
     * Inserts (or moves, for update()) a phase to $order, shifting every
     * phase already at or after that position down by one — gives
     * "Order" on the add/edit form real, unambiguous meaning instead of
     * allowing duplicate/colliding sort_order values. $excludeId keeps a
     * phase being edited from bumping into its own old slot.
     */
    public function makeRoomAt(int $order, ?int $excludeId = null): void
    {
        // Raw query builder, not $this — Model::update() rejects an
        // empty $row (it has no field data to carry, only a computed
        // SET expression), so it has to bypass the validated Model path
        // the same way blockingTaskCount() above already does.
        $builder = $this->db->table('offboarding_phases')->where('sort_order >=', $order);
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }
        $builder->set('sort_order', 'sort_order + 1', false)->update();
    }

    /**
     * Swaps sort_order with the previous/next row in the CURRENT order —
     * powers the index page's up/down reorder buttons. No-op at either
     * edge of the list.
     */
    public function reorder(int $id, string $direction): void
    {
        $all = $this->ordered();
        // Cast to int — the DB driver returns id/sort_order as numeric
        // strings, and array_search's strict mode (needed so a real "not
        // found" isn't confused with position 0) would otherwise never
        // match the int $id against a string '5', silently no-op-ing
        // every reorder click.
        $ids = array_map('intval', array_column($all, 'id'));
        $pos = array_search($id, $ids, true);

        if ($pos === false) {
            return;
        }

        $swapPos = $direction === 'up' ? $pos - 1 : $pos + 1;
        if ($swapPos < 0 || $swapPos >= count($all)) {
            return;
        }

        $a = $all[$pos];
        $b = $all[$swapPos];
        $this->update($a['id'], ['sort_order' => $b['sort_order']]);
        $this->update($b['id'], ['sort_order' => $a['sort_order']]);
    }
}
