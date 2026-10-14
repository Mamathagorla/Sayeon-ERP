<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatesForAdditionalCountries extends Migration
{
    /**
     * Adds first-level administrative regions for countries beyond
     * India — CreateStatesTable deliberately seeded India only
     * ("an invented or incomplete subdivision list would be worse than
     * none"). The gap that left: every Company/Customer/Invoice form's
     * State dropdown is a plain select with no free-text fallback, so a
     * company/customer in any other country couldn't record a state at
     * all once states.gst_state_code stopped being the only consumer of
     * this table (see the GST feature). Nothing else needed to change —
     * StateModel::allGroupedByCountry() and every form's cascading JS
     * already key off country_id generically, not India specifically.
     *
     * Covers the five countries most likely to appear as a company's or
     * customer's country in this ERP (the US is already one — see
     * Intrepid Professionals): United States, United Kingdom, Canada,
     * Australia, United Arab Emirates. Standard, well-established
     * ISO 3166-2 top-level divisions, not generated or guessed. Every
     * other country is intentionally left as-is, for the same "no data
     * is better than wrong data" reason the original migration gave.
     *
     * gst_state_code is left NULL for all of these — it is an
     * India-specific GST concept and only India's rows use it.
     */
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $byCountry = [
            'US' => [
                'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware',
                'District of Columbia', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
                'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota',
                'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey',
                'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon',
                'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah',
                'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
            ],
            'GB' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            'CA' => [
                'Alberta', 'British Columbia', 'Manitoba', 'New Brunswick', 'Newfoundland and Labrador',
                'Nova Scotia', 'Ontario', 'Prince Edward Island', 'Quebec', 'Saskatchewan',
                'Northwest Territories', 'Nunavut', 'Yukon',
            ],
            'AU' => [
                'New South Wales', 'Victoria', 'Queensland', 'Western Australia', 'South Australia', 'Tasmania',
                'Australian Capital Territory', 'Northern Territory',
            ],
            'AE' => ['Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'],
        ];

        foreach ($byCountry as $iso2 => $names) {
            $countryId = $this->db->table('countries')->where('iso2', $iso2)->get()->getRow('id');

            if ($countryId === null) {
                continue; // seed data for this ISO code isn't present — nothing to link to.
            }

            // Idempotent: re-running after a partial failure (or on a database that
            // already has some of these rows) won't hit the (country_id, name) unique key.
            $existing = array_column(
                $this->db->table('states')->select('name')->where('country_id', $countryId)->get()->getResultArray(),
                'name'
            );

            $rows = [];
            foreach ($names as $name) {
                if (in_array($name, $existing, true)) {
                    continue;
                }
                $rows[] = ['country_id' => $countryId, 'name' => $name, 'gst_state_code' => null, 'created_at' => $now, 'updated_at' => $now];
            }

            if ($rows !== []) {
                $this->db->table('states')->insertBatch($rows);
            }
        }
    }

    /**
     * Removes only the rows this migration added (by country + the exact
     * name lists above) — India's rows, and anything else already in the
     * table, are untouched.
     */
    public function down()
    {
        $byCountry = [
            'US' => [
                'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware',
                'District of Columbia', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
                'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota',
                'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey',
                'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon',
                'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah',
                'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
            ],
            'GB' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
            'CA' => [
                'Alberta', 'British Columbia', 'Manitoba', 'New Brunswick', 'Newfoundland and Labrador',
                'Nova Scotia', 'Ontario', 'Prince Edward Island', 'Quebec', 'Saskatchewan',
                'Northwest Territories', 'Nunavut', 'Yukon',
            ],
            'AU' => [
                'New South Wales', 'Victoria', 'Queensland', 'Western Australia', 'South Australia', 'Tasmania',
                'Australian Capital Territory', 'Northern Territory',
            ],
            'AE' => ['Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'],
        ];

        foreach ($byCountry as $iso2 => $names) {
            $countryId = $this->db->table('countries')->where('iso2', $iso2)->get()->getRow('id');

            if ($countryId === null) {
                continue;
            }

            $this->db->table('states')->where('country_id', $countryId)->whereIn('name', $names)->delete();
        }
    }
}
