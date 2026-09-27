<?php

namespace App\Modules\Purchase\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Line items for a purchase order — what's actually being bought, one
 * row per item, instead of the order only carrying a manually-typed
 * items_count/amount with no description of the goods themselves.
 * purchase_orders.items_count/amount stay on that table as a cached
 * summary (count of rows here / sum of line_total here), recalculated
 * by PurchaseOrderController whenever items change, so every existing
 * list/report reading those two columns keeps working unchanged.
 */
class CreatePurchaseOrderItemsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'purchase_order_id' => ['type' => 'INT', 'unsigned' => true],
            'item_name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'quantity'          => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'unit_price'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            // Stored, not computed on read — quantity * unit_price at
            // save time, same convention as other line-item tables in
            // this app (e.g. invoice_items).
            'line_total'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('purchase_order_id');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', '', 'CASCADE');
        $this->forge->createTable('purchase_order_items');
    }

    public function down()
    {
        $this->forge->dropTable('purchase_order_items', true);
    }
}
