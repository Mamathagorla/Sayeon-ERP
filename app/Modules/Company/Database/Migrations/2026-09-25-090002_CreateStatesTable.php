<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStatesTable extends Migration
{
    /**
     * Shared master data — states/UTs, scoped to a country via country_id.
     * Only India is seeded: GST (and therefore the intra-state/inter-state
     * split this feature adds) is an India-specific tax, and India is the
     * home country of every company in this ERP. gst_state_code is the
     * real 2-digit state code GST uses (the same digits that open a
     * GSTIN, e.g. 27... = Maharashtra) — not used for validation yet, but
     * kept so it's available without a second lookup table later.
     *
     * Deliberately NOT seeding states for every other country — an
     * invented or incomplete subdivision list would be worse than none.
     * A country with no rows here just falls back to a free-text
     * State/Province box on the form (see the Company/Invoice JS).
     */
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'country_id'     => ['type' => 'INT', 'unsigned' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'gst_state_code' => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['country_id', 'name']);
        $this->forge->addForeignKey('country_id', 'countries', 'id', '', 'CASCADE');
        $this->forge->createTable('states');

        $countryId = $this->db->table('countries')->where('iso2', 'IN')->get()->getRow('id');

        if ($countryId === null) {
            return; // CreateCountriesTable didn't run first — nothing to link states to.
        }

        // Current (post-2020 Dadra & Nagar Haveli / Daman & Diu merger)
        // list of India's 28 states + 6 union territories, with their
        // real GST state codes. Codes 25/28 are retired, not skipped by
        // mistake.
        $states = [
            '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
            '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan',
            '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh',
            '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura',
            '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand',
            '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
            '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka',
            '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
            '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana',
            '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        ];

        $now  = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($states as $code => $name) {
            $rows[] = ['country_id' => $countryId, 'name' => $name, 'gst_state_code' => $code, 'created_at' => $now, 'updated_at' => $now];
        }

        $this->db->table('states')->insertBatch($rows);
    }

    public function down()
    {
        $this->forge->dropTable('states', true);
    }
}
