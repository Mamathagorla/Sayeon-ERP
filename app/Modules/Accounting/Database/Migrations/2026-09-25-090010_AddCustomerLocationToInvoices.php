<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomerLocationToInvoices extends Migration
{
    /**
     * Structured customer location for GST purposes — country/state (FKs
     * into the Company module's shared countries/states tables) plus a
     * plain city name. customer_address is left exactly as it was (the
     * street-address line); these are additive columns, all nullable, so
     * every existing invoice keeps working unchanged until it's next
     * edited. gst_type is derived server-side from customer_state_id vs
     * the invoice's company's state_id (see InvoiceController) whenever
     * both are known; existing invoices have no state on either side yet,
     * so they keep behaving exactly as before (manually-set gst_type).
     */
    public function up()
    {
        // See the equivalent guard in AddCountryStateToCompanies — same
        // reasoning (no "ADD COLUMN IF NOT EXISTS" in MySQL).
        if (! $this->db->fieldExists('customer_country_id', 'invoices')) {
            $this->forge->addColumn('invoices', [
                'customer_country_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'customer_address'],
                'customer_state_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'customer_country_id'],
                'customer_city'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'customer_state_id'],
            ]);
            $this->forge->addForeignKey('customer_country_id', 'countries', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('customer_state_id', 'states', 'id', '', 'SET NULL');
            $this->forge->processIndexes('invoices');
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('invoices', 'invoices_customer_country_id_foreign');
        $this->forge->dropForeignKey('invoices', 'invoices_customer_state_id_foreign');
        $this->forge->dropColumn('invoices', ['customer_country_id', 'customer_state_id', 'customer_city']);
    }
}
