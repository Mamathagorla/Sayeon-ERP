<?php

namespace App\Modules\Company\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run()
    {
        $owner = $this->db->table('users')->select('id')->where('email', env('app.superAdminEmail', 'admin@sanvima.com'))->get()->getRowArray();
        $ownerId = $owner['id'] ?? null;

        $companies = [
            ['name' => 'Sanvima Solutions', 'country' => 'India'],
            ['name' => 'Sayeon', 'country' => 'USA'],
            ['name' => 'Festive Retail Pvt Ltd', 'country' => 'India'],
            ['name' => 'Nihira Power & Infra Pvt Ltd', 'country' => 'India'],
            ['name' => 'Intrepid Professionals', 'country' => 'USA'],
        ];

        // 'USA' here maps to the countries table's ISO name — same
        // alias the AddCountryStateToCompanies migration's backfill uses.
        $countryAliases = ['USA' => 'United States of America'];

        foreach ($companies as $company) {
            $slug   = url_title($company['name'], '-', true);
            $exists = $this->db->table('companies')->where('slug', $slug)->countAllResults() > 0;

            if (! $exists) {
                $countryRow = $this->db->table('countries')
                    ->where('name', $countryAliases[$company['country']] ?? $company['country'])
                    ->get()->getRowArray();

                $this->db->table('companies')->insert([
                    'name'       => $company['name'],
                    'slug'       => $slug,
                    'status'     => 'active',
                    'owner_id'   => $ownerId,
                    'country'    => $company['country'],
                    'country_id' => $countryRow['id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
