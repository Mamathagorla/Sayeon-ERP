<?php

namespace App\Modules\Purchase\Models;

use CodeIgniter\Model;

class PurchaseOrderModel extends Model
{
    public const APPROVAL_STATUSES    = ['pending', 'approved', 'rejected'];
    public const FULFILLMENT_STATUSES = ['ordered', 'in_transit', 'received'];
    public const PAYMENT_STATUSES     = ['pending', 'partial', 'paid'];

    protected $table         = 'purchase_orders';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'vendor_id', 'po_number', 'requested_by', 'order_date', 'expected_date',
        'items_count', 'amount', 'approval_status', 'approved_by', 'approved_at',
        'fulfillment_status', 'payment_status', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Upper bound matches the DECIMAL(12,2) column, same convention as
    // ExpenseModel/BillModel's own MAX_AMOUNT.
    public const MAX_AMOUNT = 9999999999.99;

    protected $validationRules = [
        'company_id'    => 'required|integer',
        'vendor_id'     => 'required|integer',
        'po_number'     => 'required|regex_match[/^[A-Za-z0-9-]+$/]|max_length[40]|is_unique[purchase_orders.po_number,id,{id}]',
        'order_date'    => 'required|valid_date',
        'expected_date' => 'permit_empty|valid_date',
        // items_count/amount are no longer direct form inputs — they're
        // computed server-side from the submitted line items (see
        // PurchaseOrderController::parseItems()/calculateTotals()), so
        // there's nothing to validate by field name here; the item rows
        // themselves are validated while parsing instead.
    ];

    protected $validationMessages = [
        'po_number' => ['regex_match' => 'PO number may only contain letters, numbers and hyphens.'],
    ];

    public function filtered(array $filters = [])
    {
        // A correlated subquery, not a LEFT JOIN, so a multi-item order
        // still returns exactly one row here — a join against the
        // one-to-many purchase_order_items would duplicate every other
        // joined column per item and break every existing caller of
        // filtered() (including withRelations()'s ->first()).
        $builder = $this->select("purchase_orders.*, companies.name as company_name, vendors.name as vendor_name,
                requester.name as requester_name, approver.name as approver_name,
                (SELECT GROUP_CONCAT(item_name ORDER BY id SEPARATOR ', ') FROM purchase_order_items
                    WHERE purchase_order_items.purchase_order_id = purchase_orders.id) as items_preview")
            ->join('companies', 'companies.id = purchase_orders.company_id')
            ->join('vendors', 'vendors.id = purchase_orders.vendor_id')
            ->join('users as requester', 'requester.id = purchase_orders.requested_by', 'left')
            ->join('users as approver', 'approver.id = purchase_orders.approved_by', 'left');

        // array_key_exists (not empty()) — same convention as every
        // other module's filtered(): 0 must match zero rows.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('purchase_orders.company_id', $filters['company_id']);
        }
        if (! empty($filters['approval_status'])) {
            is_array($filters['approval_status'])
                ? $builder->whereIn('purchase_orders.approval_status', $filters['approval_status'])
                : $builder->where('purchase_orders.approval_status', $filters['approval_status']);
        }
        // "Purchases" view — approved orders only (the fulfillment
        // stage), as opposed to "Purchase Orders" which shows every
        // request regardless of approval outcome.
        if (! empty($filters['approved_only'])) {
            $builder->where('purchase_orders.approval_status', 'approved');
        }
        if (! empty($filters['fulfillment_status'])) {
            is_array($filters['fulfillment_status'])
                ? $builder->whereIn('purchase_orders.fulfillment_status', $filters['fulfillment_status'])
                : $builder->where('purchase_orders.fulfillment_status', $filters['fulfillment_status']);
        }

        return $builder->orderBy('purchase_orders.order_date', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('purchase_orders.id', $id)->first();
    }

    /**
     * quantity * unit_price per line, rounded, summed into the order's
     * cached items_count/amount — same shape as InvoiceModel::
     * calculateTotals(), just without tax/discount since a purchase
     * order doesn't carry those.
     */
    public function calculateTotals(array $items): array
    {
        $amount = 0.0;
        $computedItems = [];

        foreach ($items as $item) {
            $quantity  = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = round($quantity * $unitPrice, 2);
            $amount += $lineTotal;

            $computedItems[] = [
                'item_name'  => $item['item_name'],
                'quantity'   => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'items'       => $computedItems,
            'items_count' => count($computedItems),
            'amount'      => round($amount, 2),
        ];
    }

    public function nextPoNumber(): string
    {
        $last = $this->select('po_number')->orderBy('id', 'DESC')->first();

        $nextNumber = 1;
        if ($last && preg_match('/(\d+)$/', $last['po_number'], $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return 'PO-' . date('Y') . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
