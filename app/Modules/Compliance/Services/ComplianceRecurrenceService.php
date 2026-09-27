<?php

namespace App\Modules\Compliance\Services;

use App\Modules\Compliance\Models\ComplianceItemModel;

/**
 * Owns the "recurring" behavior the spec asks for: filing a GST return
 * doesn't end the obligation, it starts the next cycle. Marking an item
 * Filed creates the next period's item automatically (so history of
 * past filings is preserved as separate rows — see migration comment
 * on previous_item_id) rather than mutating due_date in place.
 */
class ComplianceRecurrenceService
{
    protected ComplianceItemModel $itemModel;

    public function __construct()
    {
        $this->itemModel = new ComplianceItemModel();
    }

    public function markFiled(int $itemId, int $userId): array
    {
        $item = $this->itemModel->find($itemId);

        if ($item === null) {
            throw new \RuntimeException('Compliance item not found.');
        }

        $this->itemModel->update($itemId, [
            'status'   => 'filed',
            'filed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($item['recurrence'] === 'none') {
            return ['next_item_id' => null];
        }

        $nextDueDate = $this->advanceDate($item['due_date'], $item['recurrence']);

        $nextId = $this->itemModel->insert([
            'company_id'           => $item['company_id'],
            'compliance_type_id'   => $item['compliance_type_id'],
            'title'                => $item['title'],
            // The regulator doesn't change cycle to cycle, so it carries
            // forward same as title. 'period' is deliberately NOT copied
            // — it's a free-text label (e.g. "2024-2025") with no
            // reliable way to auto-advance, so it's left for the user to
            // set on the new cycle rather than carrying over a stale one.
            'regulator'            => $item['regulator'],
            'due_date'             => $nextDueDate,
            'recurrence'           => $item['recurrence'],
            'responsible_user_id'  => $item['responsible_user_id'],
            'status'               => 'pending',
            'reminder_days_before' => $item['reminder_days_before'],
            'previous_item_id'     => $item['id'],
            'created_by'           => $userId,
        ]);

        return ['next_item_id' => $nextId];
    }

    private function advanceDate(string $date, string $recurrence): string
    {
        $months = match ($recurrence) {
            'monthly'     => 1,
            'quarterly'   => 3,
            'half_yearly' => 6,
            'annually'    => 12,
            default       => 0,
        };

        return date('Y-m-d', strtotime("{$date} +{$months} months"));
    }
}
