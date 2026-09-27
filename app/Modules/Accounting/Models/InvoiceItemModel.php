<?php

namespace App\Modules\Accounting\Models;

use CodeIgniter\Model;

class InvoiceItemModel extends Model
{
    protected $table         = 'invoice_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['invoice_id', 'description', 'quantity', 'rate', 'amount', 'sort_order'];

    protected $useTimestamps = false;

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function forInvoice(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Replaces every line item on an invoice in one go — simplest
     * reliable way to sync a dynamic add/remove-row form to the DB
     * without diffing individual row ids. Called inside the same
     * insert/update flow as the parent invoice, so a failed item never
     * gets stranded pointing at a non-existent invoice.
     */
    public function replaceForInvoice(int $invoiceId, array $items): void
    {
        $this->where('invoice_id', $invoiceId)->delete();

        $rows = [];
        foreach (array_values($items) as $i => $item) {
            $rows[] = [
                'invoice_id'  => $invoiceId,
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
