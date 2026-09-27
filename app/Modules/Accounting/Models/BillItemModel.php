<?php

namespace App\Modules\Accounting\Models;

use CodeIgniter\Model;

class BillItemModel extends Model
{
    protected $table         = 'bill_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['bill_id', 'description', 'quantity', 'rate', 'amount', 'sort_order'];

    protected $useTimestamps = false;

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function forBill(int $billId): array
    {
        return $this->where('bill_id', $billId)->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Replaces every line item on a bill in one go — same reasoning as
     * InvoiceItemModel::replaceForInvoice(): simplest reliable way to
     * sync a dynamic add/remove-row form without diffing individual
     * row ids.
     */
    public function replaceForBill(int $billId, array $items): void
    {
        $this->where('bill_id', $billId)->delete();

        $rows = [];
        foreach (array_values($items) as $i => $item) {
            $rows[] = [
                'bill_id'     => $billId,
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'rate'        => $item['rate'],
                'amount'      => $item['amount'],
                'sort_order'  => $i,
                'created_at'  => date('Y-m-d H:i:s'),
            ];
        }

        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
