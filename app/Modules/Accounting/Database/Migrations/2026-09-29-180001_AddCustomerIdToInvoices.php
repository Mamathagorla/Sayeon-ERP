<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links an invoice to an optional row in the (already-existing, standalone)
 * customers module, so a user can pick a saved customer instead of
 * re-typing their name/GSTIN/address every time. Deliberately nullable and
 * NOT a replacement for the existing customer_name/email/phone/gstin/
 * address/country_id/state_id/city snapshot columns on invoices — those
 * stay exactly as they are (an issued invoice must keep showing the
 * customer details as they were at the time, even if the Customer record
 * is edited or deleted later). customer_id is only ever a convenience
 * "where this snapshot was copied from" pointer, never a live join the
 * rest of the app depends on.
 */
class AddCustomerIdToInvoices extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('customer_id', 'invoices')) {
            $this->forge->addColumn('invoices', [
                'customer_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'company_id'],
            ]);
            $this->forge->addForeignKey('customer_id', 'customers', 'id', '', 'SET NULL');
            $this->forge->processIndexes('invoices');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('customer_id', 'invoices')) {
            $this->forge->dropForeignKey('invoices', 'invoices_customer_id_foreign');
            $this->forge->dropColumn('invoices', 'customer_id');
        }
    }
}
