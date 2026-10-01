<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCountryStateToCompanies extends Migration
{
    /**
     * Adds structured country_id/state_id alongside the existing
     * free-text `country` column — that column is left in place
     * (untouched) so anything still reading companies.country as a
     * string keeps working; the new columns are what the Country/State
     * dropdowns and the GST intra/inter-state logic use going forward.
     *
     * Existing rows are backfilled by matching their current `country`
     * text against countries.name (handling the common abbreviations
     * this app's own seed data uses — USA/UK/UAE). state_id is left
     * NULL for every existing company: there is no reliable source for
     * "which state" in the current data (the closest thing,
     * registered_address, is free text, e.g. just a city name) — an
     * admin fills it in once via the updated company edit form.
     */
    public function up()
    {
        // Guards the ADD COLUMN/ADD FOREIGN KEY below against being run
        // twice — e.g. a migrate command re-run after an interrupted
        // first attempt — since MySQL has no "ADD COLUMN IF NOT EXISTS".
        if (! $this->db->fieldExists('country_id', 'companies')) {
            $this->forge->addColumn('companies', [
                'country_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'country'],
                'state_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'country_id'],
            ]);
            $this->forge->addForeignKey('country_id', 'countries', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('state_id', 'states', 'id', '', 'SET NULL');
            $this->forge->processIndexes('companies');
        }

        $aliases = [
            'usa'            => 'United States of America',
            'us'             => 'United States of America',
            'united states'  => 'United States of America',
            'uk'             => 'United Kingdom',
            'uae'            => 'United Arab Emirates',
        ];

        foreach ($this->db->table('companies')->select('id, country')->get()->getResultArray() as $row) {
            $text = trim((string) $row['country']);
            if ($text === '') {
                continue;
            }

            $lookup = $aliases[strtolower($text)] ?? $text;

            $country = $this->db->table('countries')->where('name', $lookup)->get()->getRow('id');
            if ($country !== null) {
                $this->db->table('companies')->where('id', $row['id'])->update(['country_id' => $country->id]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('companies', 'companies_country_id_foreign');
        $this->forge->dropForeignKey('companies', 'companies_state_id_foreign');
        $this->forge->dropColumn('companies', ['country_id', 'state_id']);
    }
}
