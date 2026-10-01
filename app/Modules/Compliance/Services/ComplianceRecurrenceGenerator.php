<?php

namespace App\Modules\Compliance\Services;

use App\Modules\Compliance\Models\ComplianceItemModel;

/**
 * "Another feature/workflow to generate the compliance feature/
 * information" — a date-triggered counterpart to
 * ComplianceRecurrenceService::markFiled()'s action-triggered next-cycle
 * creation. Today the ENTIRE recurrence chain for an item depends on a
 * human remembering to click "Mark Filed" on time; if nobody does, the
 * item just sits there (eventually flipped to 'overdue' by
 * refreshOverdueStatuses()) and no successor ever appears. This walks
 * every recurring item nearing/at/past its own due date and spawns its
 * next cycle regardless of whether it's been filed — using
 * ComplianceRecurrenceService::spawnNextCycle(), the exact same write
 * markFiled() itself uses, so there is still exactly one place that
 * creates a next-cycle row. hasNextCycle() is the idempotency guard both
 * paths share, so an item is never given two successors even if it gets
 * filed and also picked up by this generator.
 *
 * Structurally this mirrors RecurringExpenseGenerator/
 * RecurringMeetingGenerator's runOncePerDay()/runAll()/runForCompany()
 * shape, the established "turn a schedule into concrete rows" pattern
 * in this app — see their docblocks for the reasoning repeated here.
 */
class ComplianceRecurrenceGenerator
{
    private ComplianceItemModel $itemModel;
    private ComplianceRecurrenceService $recurrenceService;

    // Same defensive-cap reasoning as RecurringExpenseGenerator::MAX_PER_RUN
    // — one company's worth of due templates in a single run() call.
    private const MAX_PER_RUN = 500;

    public function __construct(?ComplianceItemModel $itemModel = null, ?ComplianceRecurrenceService $recurrenceService = null)
    {
        $this->itemModel         = $itemModel ?? new ComplianceItemModel();
        $this->recurrenceService = $recurrenceService ?? new ComplianceRecurrenceService();
    }

    public function runOncePerDay(): void
    {
        $cache = cache();
        $today = date('Y-m-d');

        if ($cache->get('compliance_recurrence_last_run') === $today) {
            return;
        }
        $cache->save('compliance_recurrence_last_run', $today, 172800);

        try {
            $result = $this->runAll();
            if ($result['itemsGenerated'] > 0) {
                log_message('info', "Compliance recurrence (auto): {$result['itemsGenerated']} item(s) generated from {$result['itemsProcessed']} due item(s).");
            }
        } catch (\Throwable $e) {
            log_message('error', 'Compliance recurrence auto-run failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{itemsProcessed:int, itemsGenerated:int}
     */
    public function runAll(): array
    {
        return $this->run(null);
    }

    /**
     * @return array{itemsProcessed:int, itemsGenerated:int}
     */
    public function runForCompany(int $companyId): array
    {
        return $this->run($companyId);
    }

    private function run(?int $companyId): array
    {
        $due            = array_slice($this->itemModel->dueForGeneration($companyId), 0, self::MAX_PER_RUN);
        $itemsProcessed = 0;
        $itemsGenerated = 0;

        foreach ($due as $item) {
            // Belt-and-suspenders re-check — dueForGeneration() already
            // excludes items with a successor, but a prior iteration in
            // this same run (or a concurrent request) could have just
            // created one via markFiled() being clicked mid-run.
            if ($this->itemModel->hasNextCycle((int) $item['id'])) {
                continue;
            }

            $this->recurrenceService->spawnNextCycle($item, (int) $item['created_by']);
            $itemsProcessed++;
            $itemsGenerated++;
        }

        return ['itemsProcessed' => $itemsProcessed, 'itemsGenerated' => $itemsGenerated];
    }
}
