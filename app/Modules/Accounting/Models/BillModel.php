<?php

namespace App\Modules\Accounting\Models;

use CodeIgniter\Model;

class BillModel extends Model
{
    public const STATUSES = ['unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'];
    public const OPEN_STATUSES = ['unpaid', 'partially_paid', 'overdue'];
    public const GST_TYPES = ['intra_state', 'inter_state'];

    protected $table         = 'bills';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'bill_number', 'vendor_name', 'vendor_gstin', 'vendor_address', 'amount',
        'gst_type', 'gst_percent', 'discount_percent', 'subtotal',
        'discount_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'issue_date', 'due_date', 'status', 'notes', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Upper bound matches the DECIMAL(12,2) column — the field can't
    // physically hold more than this, so reject it before the DB does.
    // Public so BillController can reject an over-limit computed Grand
    // Total the same way InvoiceModel::MAX_AMOUNT already does.
    public const MAX_AMOUNT = 9999999999.99;

    // Letters/digits/hyphens — matches nextBillNumber()'s own
    // "BILL-2026-0001" format, so a strict alpha_numeric rule (no
    // hyphen) would reject the app's own generated default.
    private const NUMBER_PATTERN = '/^[A-Za-z0-9-]+$/';

    // Same 15-character GSTIN format CompanyModel/InvoiceModel validate
    // their own GST fields against.
    private const GSTIN_PATTERN = '/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}$/';

    // 'amount' is deliberately NOT validated here — like Invoice, it's
    // the server-computed Grand Total (see calculateTotals()), never
    // taken directly from the request.
    protected $validationRules = [
        'company_id'       => 'required|integer',
        'bill_number'      => 'required|regex_match[' . self::NUMBER_PATTERN . ']|max_length[40]|is_unique[bills.bill_number,id,{id}]',
        'vendor_name'      => 'required|min_length[2]|max_length[150]',
        'vendor_gstin'     => 'permit_empty|regex_match[' . self::GSTIN_PATTERN . ']',
        'issue_date'       => 'required|valid_date',
        'due_date'         => 'permit_empty|valid_date',
        'gst_type'         => 'required|in_list[intra_state,inter_state]',
        'gst_percent'      => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'discount_percent' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    protected $validationMessages = [
        'bill_number'  => ['regex_match' => 'Bill number may only contain letters, numbers and hyphens.'],
        'vendor_gstin' => ['regex_match' => 'Enter a valid 15-character GSTIN (e.g. 27ABCDE1234F1Z5).'],
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('bills.*, companies.name as company_name')
            ->join('companies', 'companies.id = bills.company_id');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's bills.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('bills.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('bills.status', $filters['status'])
                : $builder->where('bills.status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $builder->groupStart()
                ->like('bills.vendor_name', $filters['q'])
                ->orLike('bills.bill_number', $filters['q'])
                ->groupEnd();
        }
        if (! empty($filters['issue_from'])) {
            $builder->where('bills.issue_date >=', $filters['issue_from']);
        }
        if (! empty($filters['issue_to'])) {
            $builder->where('bills.issue_date <=', $filters['issue_to']);
        }

        return $builder->orderBy('bills.issue_date', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('bills.id', $id)->first();
    }

    /**
     * Single source of truth for the GST math — identical logic to
     * InvoiceModel::calculateTotals(), duplicated rather than shared
     * since Bill and Invoice are otherwise-independent models and this
     * keeps each one self-contained.
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

    public function refreshOverdueStatuses(): void
    {
        $this->whereIn('status', ['unpaid', 'partially_paid'])
            ->where('due_date IS NOT NULL')
            ->where('due_date <', date('Y-m-d'))
            ->set(['status' => 'overdue'])
            ->update();
    }

    public function nextBillNumber(): string
    {
        $last = $this->select('bill_number')->orderBy('id', 'DESC')->first();

        $nextNumber = 1;
        if ($last && preg_match('/(\d+)$/', $last['bill_number'], $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return 'BILL-' . date('Y') . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
