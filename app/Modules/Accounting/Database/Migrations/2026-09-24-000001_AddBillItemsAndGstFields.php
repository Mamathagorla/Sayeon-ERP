<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds line-item and GST support to Bills — the same upgrade
 * AddInvoiceItemsAndGstFields already did for Invoices, so a Bill can
 * be a genuine itemized document instead of one flat amount + a plain
 * vendor-name string. Checked the live data before writing this: 2
 * existing bills have no items and no vendor GSTIN/address — every new
 * column gets a safe default, and both bills/show.php and the new
 * print/PDF views fall back to a single synthetic line item (bill
 * number + notes, for the existing `amount`) when a bill has no
 * bill_items rows yet, exactly like Invoice's own empty-items fallback.
 *
 * `bills.amount` is kept as-is and becomes the server-computed Grand
 * Total (subtotal - discount + CGST/SGST/IGST) — every other module
 * that already reads bills.amount (Reports, Dashboard cash flow,
 * PaymentController's balance calc) keeps working unchanged.
 */
class AddBillItemsAndGstFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('bills', [
            'vendor_gstin'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'vendor_name'],
            'vendor_address'   => ['type' => 'TEXT', 'null' => true, 'after' => 'vendor_gstin'],
            // Same convention as invoices.gst_type — the user's own
            // declared choice per bill, no structured "state" master
            // data to derive it from automatically.
            'gst_type'         => ['type' => 'ENUM', 'constraint' => ['intra_state', 'inter_state'], 'default' => 'intra_state', 'after' => 'amount'],
            'gst_percent'      => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'after' => 'gst_type'],
            'discount_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'after' => 'gst_percent'],
            'subtotal'         => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'discount_percent'],
            'discount_amount'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'subtotal'],
            'cgst_amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'discount_amount'],
            'sgst_amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'cgst_amount'],
            'igst_amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'sgst_amount'],
        ]);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'bill_id'     => ['type' => 'INT', 'unsigned' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'quantity'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1],
            'rate'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'sort_order'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bill_id');
        // Line items live and die with their bill.
        $this->forge->addForeignKey('bill_id', 'bills', 'id', '', 'CASCADE');
        $this->forge->createTable('bill_items');
    }

    public function down()
    {
        $this->forge->dropTable('bill_items', true);

        $this->forge->dropColumn('bills', [
            'vendor_gstin', 'vendor_address',
            'gst_type', 'gst_percent', 'discount_percent', 'subtotal',
            'discount_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        ]);
    }
}
