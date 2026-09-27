<?php

namespace App\Modules\Purchase\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Single table backs BOTH the "Purchase Orders" screen (the approval
 * workflow: Requestor, Order Date, Expected, approval_status) and the
 * "Purchases" screen (the fulfillment view: approved orders only,
 * Purchase Date, Items, Amount, Payment, fulfillment_status) — one
 * request flowing through approval then fulfillment is a single real
 * entity, not two, so it isn't duplicated across two tables.
 */
class CreatePurchaseOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'company_id'         => ['type' => 'INT', 'unsigned' => true],
            'vendor_id'          => ['type' => 'INT', 'unsigned' => true],
            'po_number'          => ['type' => 'VARCHAR', 'constraint' => 40],
            'requested_by'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'order_date'         => ['type' => 'DATE'],
            'expected_date'      => ['type' => 'DATE', 'null' => true],
            'items_count'        => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'amount'             => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'approval_status'    => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected'], 'default' => 'pending'],
            'approved_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'approved_at'        => ['type' => 'DATETIME', 'null' => true],
            // Only meaningful once approved — null while pending/rejected.
            'fulfillment_status' => ['type' => 'ENUM', 'constraint' => ['ordered', 'in_transit', 'received'], 'null' => true],
            'payment_status'     => ['type' => 'ENUM', 'constraint' => ['pending', 'partial', 'paid'], 'default' => 'pending'],
            'notes'              => ['type' => 'TEXT', 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('po_number');
        $this->forge->addForeignKey('company_id', 'companies', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('vendor_id', 'vendors', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('requested_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('approved_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('purchase_orders');
    }

    public function down()
    {
        $this->forge->dropTable('purchase_orders', true);
    }
}
