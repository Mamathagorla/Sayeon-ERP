<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds line-item and GST support to Invoices. Checked the live data
 * before writing this: the `invoices` table has zero rows (this
 * project's invoice module hasn't been used yet), so there is nothing
 * to backfill — every new column is added with a safe default purely
 * as a matter of good practice, not because any existing row depends
 * on it.
 *
 * `invoices.amount` is kept as-is and becomes the server-computed
 * Grand Total (subtotal - discount + CGST/SGST/IGST) — every other
 * module that already reads invoices.amount (Reports, Dashboard cash
 * flow, PaymentController's balance calc) keeps working unchanged.
 */
class AddInvoiceItemsAndGstFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('invoices', [
            'customer_email'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'customer_name'],
            'customer_phone'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'customer_email'],
            'customer_gstin'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'customer_phone'],
            'customer_address' => ['type' => 'TEXT', 'null' => true, 'after' => 'customer_gstin'],
            // 'intra_state' -> split GST into CGST+SGST; 'inter_state' -> IGST only.
            // No structured "state" master data exists on companies/customers
            // yet, so this is the user's own declared choice per invoice
            // rather than something derived automatically.
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
            'invoice_id'  => ['type' => 'INT', 'unsigned' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'quantity'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1],
            'rate'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'sort_order'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('invoice_id');
        // Line items live and die with their invoice.
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', '', 'CASCADE');
        $this->forge->createTable('invoice_items');
    }

    public function down()
    {
        $this->forge->dropTable('invoice_items', true);

        $this->forge->dropColumn('invoices', [
            'customer_email', 'customer_phone', 'customer_gstin', 'customer_address',
            'gst_type', 'gst_percent', 'discount_percent', 'subtotal',
            'discount_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        ]);
    }
}
