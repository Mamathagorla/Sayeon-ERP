<?php

namespace App\Modules\Purchase\Models;

use CodeIgniter\Model;

class PurchaseOrderItemModel extends Model
{
    protected $table         = 'purchase_order_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['purchase_order_id', 'item_name', 'quantity', 'unit_price', 'line_total'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function forOrder(int $purchaseOrderId): array
    {
        return $this->where('purchase_order_id', $purchaseOrderId)->orderBy('id', 'ASC')->findAll();
    }

    /**
     * Replaces every line item on an order in one go — same
     * delete-then-insertBatch convention as InvoiceItemModel::
     * replaceForInvoice(), the simplest reliable way to sync a dynamic
     * add/remove-row form without diffing individual row ids.
     */
    public function replaceForOrder(int $purchaseOrderId, array $items): void
    {
        $this->where('purchase_order_id', $purchaseOrderId)->delete();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'purchase_order_id' => $purchaseOrderId,
                'item_name'         => $item['item_name'],
                'quantity'          => $item['quantity'],
                'unit_price'        => $item['unit_price'],
                'line_total'        => $item['line_total'],
            ];
        }

        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
