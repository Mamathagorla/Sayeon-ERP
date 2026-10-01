<?php

namespace App\Modules\Accounting\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Renumbers every existing invoice into the new company-prefixed format
 * (e.g. 'SAY-INV001'), replacing the old shared 'INV-{year}-####'
 * sequence — part of the explicit "short, company-specific ID system"
 * request. Ordered by id within each company, so existing invoices keep
 * their original relative order. Note this changes invoice_number's
 * actual printed value, not just an internal code — acceptable here
 * since this is pre-production/demo data (3 rows), but in a live
 * accounting system already-issued invoice numbers are normally treated
 * as immutable records; flagging that explicitly rather than silently
 * treating this renumbering as risk-free.
 */
class BackfillCompanyScopedInvoiceNumbers extends Migration
{
    public function up()
    {
        $companies = $this->db->table('companies')->select('id, short_code')->where('short_code IS NOT NULL')->get()->getResultArray();

        foreach ($companies as $company) {
            $rows = $this->db->table('invoices')
                ->select('id')
                ->where('company_id', $company['id'])
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $n = 1;
            foreach ($rows as $row) {
                $number = $company['short_code'] . '-INV' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
                $this->db->table('invoices')->where('id', $row['id'])->update(['invoice_number' => $number]);
                $n++;
            }
        }
    }

    public function down()
    {
        // Renumbering is one-directional by design (see class docblock).
    }
}
