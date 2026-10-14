<?php

namespace App\Modules\Accounting\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table         = 'payments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'invoice_id', 'bill_id', 'payroll_run_id', 'direction', 'amount',
        'payment_date', 'method', 'reference', 'notes', 'created_by',
    ];
    protected $useTimestamps = false;

    // Upper bound matches the DECIMAL(12,2) column — the field can't
    // physically hold more than this, so reject it before the DB does.
    private const MAX_AMOUNT = 9999999999.99;

    protected $validationRules = [
        'amount'       => 'required|decimal|greater_than[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'payment_date' => 'required|valid_date',
    ];

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function forInvoice(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)->orderBy('payment_date', 'DESC')->findAll();
    }

    public function forBill(int $billId): array
    {
        return $this->where('bill_id', $billId)->orderBy('payment_date', 'DESC')->findAll();
    }

    public function totalForInvoice(int $invoiceId): float
    {
        return (float) ($this->selectSum('amount')->where('invoice_id', $invoiceId)->first()['amount'] ?? 0);
    }

    public function totalForBill(int $billId): float
    {
        return (float) ($this->selectSum('amount')->where('bill_id', $billId)->first()['amount'] ?? 0);
    }

    /**
     * invoice_id|bill_id => total paid, for the given ids.
     *
     * @param int[] $ids
     * @return array<int, float>
     */
    public function paidMap(string $column, array $ids): array
    {
        if ($ids === [] || ! in_array($column, ['invoice_id', 'bill_id'], true)) {
            return [];
        }

        $rows = $this->select($column . ' as ref, SUM(amount) as paid')->whereIn($column, $ids)->groupBy($column)->findAll();

        return array_map('floatval', array_column($rows, 'paid', 'ref'));
    }

    /**
     * What is still owed on a set of open invoices/bills: sum of (amount - payments received/made), never below
     * zero per document. $rows need 'id' and 'amount'.
     */
    public function outstanding(array $rows, string $column): float
    {
        $paid  = $this->paidMap($column, array_map('intval', array_column($rows, 'id')));
        $total = 0.0;
        foreach ($rows as $r) {
            $total += max(0.0, (float) $r['amount'] - ($paid[(int) $r['id']] ?? 0.0));
        }

        return round($total, 2);
    }

    public function cashInOutTotals(?int $companyId, string $from, string $to): array
    {
        $builder = $this->where('payment_date >=', $from)->where('payment_date <=', $to);

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        $rows = $builder->select('direction, SUM(amount) as total')->groupBy('direction')->findAll();

        $totals = ['in' => 0.0, 'out' => 0.0];
        foreach ($rows as $row) {
            $totals[$row['direction']] = (float) $row['total'];
        }

        return $totals;
    }
}
