<?php

namespace App\Modules\Accounting\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    public const STATUSES = ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'];
    public const OPEN_STATUSES = ['sent', 'partially_paid', 'overdue'];

    public const GST_TYPES = ['intra_state', 'inter_state'];

    protected $table         = 'invoices';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'invoice_number', 'customer_name', 'customer_email',
        'customer_phone', 'customer_gstin', 'customer_address', 'amount',
        'gst_type', 'gst_percent', 'discount_percent', 'subtotal',
        'discount_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'issue_date', 'due_date', 'status', 'notes', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Upper bound matches the DECIMAL(12,2) column — the field can't
    // physically hold more than this. Public so InvoiceController can
    // reject an over-limit computed Grand Total with a friendly error
    // before the DB does (amount itself isn't in $validationRules —
    // see the note above — since it's never a raw POST field).
    public const MAX_AMOUNT = 9999999999.99;

    // Letters/digits/hyphens — matches nextInvoiceNumber()'s own
    // "INV-2026-0001" format, so a strict alpha_numeric rule (no hyphen)
    // would reject the app's own generated default.
    private const NUMBER_PATTERN = '/^[A-Za-z0-9-]+$/';

    // Same 15-character GSTIN format CompanyModel validates its own
    // `gst` field against.
    private const GSTIN_PATTERN = '/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}$/';

    // 'amount' is deliberately NOT validated here — it's the server-
    // computed Grand Total (see calculateTotals()), never taken
    // directly from the request, so there's no raw POST field for a
    // validation rule to check.
    protected $validationRules = [
        'company_id'        => 'required|integer',
        'invoice_number'    => 'required|regex_match[' . self::NUMBER_PATTERN . ']|max_length[40]|is_unique[invoices.invoice_number,id,{id}]',
        'customer_name'     => 'required|min_length[2]|max_length[150]',
        'customer_email'    => 'permit_empty|valid_email|max_length[150]',
        'customer_phone'    => 'permit_empty|regex_match[/^[0-9+\-\s]{6,20}$/]',
        'customer_gstin'    => 'permit_empty|regex_match[' . self::GSTIN_PATTERN . ']',
        'issue_date'        => 'required|valid_date',
        'due_date'          => 'permit_empty|valid_date',
        'gst_type'          => 'required|in_list[intra_state,inter_state]',
        'gst_percent'       => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'discount_percent'  => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    protected $validationMessages = [
        'invoice_number' => ['regex_match' => 'Invoice number may only contain letters, numbers and hyphens.'],
        'customer_gstin' => ['regex_match' => 'Enter a valid 15-character GSTIN (e.g. 27ABCDE1234F1Z5).'],
        'customer_phone' => ['regex_match' => 'Enter a valid phone number.'],
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('invoices.*, companies.name as company_name')
            ->join('companies', 'companies.id = invoices.company_id');

        if (! empty($filters['company_id'])) {
            $builder->where('invoices.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('invoices.status', $filters['status'])
                : $builder->where('invoices.status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $builder->groupStart()
                ->like('invoices.customer_name', $filters['q'])
                ->orLike('invoices.invoice_number', $filters['q'])
                ->groupEnd();
        }
        if (! empty($filters['issue_from'])) {
            $builder->where('invoices.issue_date >=', $filters['issue_from']);
        }
        if (! empty($filters['issue_to'])) {
            $builder->where('invoices.issue_date <=', $filters['issue_to']);
        }

        return $builder->orderBy('invoices.issue_date', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('invoices.id', $id)->first();
    }

    /**
     * Single source of truth for the GST math, shared by store()/
     * update() (and available to anything else that needs to preview
     * totals) so the calculation only exists once. $items is the same
     * shape InvoiceItemModel::replaceForInvoice() expects
     * (description/quantity/rate/amount per row — amount is
     * recomputed here from quantity*rate, never trusted from input).
     *
     * GST split: 'intra_state' divides gst_percent evenly into
     * CGST+SGST (the standard India GST rule for a sale within the
     * same state); 'inter_state' applies the full rate as IGST. There's
     * no structured company/customer "state" master data in this
     * project to auto-detect which applies, so the invoice itself
     * declares it.
     */
    public function calculateTotals(array $items, float $discountPercent, float $gstPercent, string $gstType): array
    {
        $subtotal = 0.0;
        $computedItems = [];

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $rate     = (float) $item['rate'];
            $amount   = round($quantity * $rate, 2);
            $subtotal += $amount;

            $computedItems[] = [
                'description' => $item['description'],
                'quantity'    => $quantity,
                'rate'        => $rate,
                'amount'      => $amount,
            ];
        }

        $subtotal        = round($subtotal, 2);
        $discountAmount  = round($subtotal * $discountPercent / 100, 2);
        $taxable         = max(0, $subtotal - $discountAmount);

        $cgst = $sgst = $igst = 0.0;
        if ($gstType === 'inter_state') {
            $igst = round($taxable * $gstPercent / 100, 2);
        } else {
            $cgst = round($taxable * $gstPercent / 200, 2);
            $sgst = round($taxable * $gstPercent / 200, 2);
        }

        $grandTotal = round($taxable + $cgst + $sgst + $igst, 2);

        return [
            'items'           => $computedItems,
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'cgst_amount'     => $cgst,
            'sgst_amount'     => $sgst,
            'igst_amount'     => $igst,
            'amount'          => $grandTotal,
        ];
    }

    /**
     * Flips sent/partially_paid invoices whose due date has passed to
     * 'overdue' — same lazy-refresh-on-list-load pattern as
     * ComplianceItemModel::refreshOverdueStatuses().
     */
    public function refreshOverdueStatuses(): void
    {
        $this->whereIn('status', ['sent', 'partially_paid'])
            ->where('due_date IS NOT NULL')
            ->where('due_date <', date('Y-m-d'))
            ->set(['status' => 'overdue'])
            ->update();
    }

    public function nextInvoiceNumber(): string
    {
        $last = $this->select('invoice_number')->orderBy('id', 'DESC')->first();

        $nextNumber = 1;
        if ($last && preg_match('/(\d+)$/', $last['invoice_number'], $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return 'INV-' . date('Y') . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
