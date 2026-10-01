<?php

namespace App\Modules\Company\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCountriesTable extends Migration
{
    /**
     * Shared master data — the standard ISO 3166-1 country list, used by
     * both the Company form's Country dropdown and the Invoice form's
     * Customer Country dropdown, so both stay consistent instead of each
     * screen inventing its own list. Seeded inline (not via a separate
     * seeder) because this is fixed reference data the feature needs to
     * function at all, not sample/demo data.
     */
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'iso2'       => ['type' => 'CHAR', 'constraint' => 2],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addUniqueKey('iso2');
        $this->forge->createTable('countries');

        $now = date('Y-m-d H:i:s');
        $names = [
            'Afghanistan' => 'AF', 'Albania' => 'AL', 'Algeria' => 'DZ', 'Andorra' => 'AD', 'Angola' => 'AO',
            'Antigua and Barbuda' => 'AG', 'Argentina' => 'AR', 'Armenia' => 'AM', 'Australia' => 'AU', 'Austria' => 'AT',
            'Azerbaijan' => 'AZ', 'Bahamas' => 'BS', 'Bahrain' => 'BH', 'Bangladesh' => 'BD', 'Barbados' => 'BB',
            'Belarus' => 'BY', 'Belgium' => 'BE', 'Belize' => 'BZ', 'Benin' => 'BJ', 'Bhutan' => 'BT',
            'Bolivia' => 'BO', 'Bosnia and Herzegovina' => 'BA', 'Botswana' => 'BW', 'Brazil' => 'BR', 'Brunei' => 'BN',
            'Bulgaria' => 'BG', 'Burkina Faso' => 'BF', 'Burundi' => 'BI', 'Cabo Verde' => 'CV', 'Cambodia' => 'KH',
            'Cameroon' => 'CM', 'Canada' => 'CA', 'Central African Republic' => 'CF', 'Chad' => 'TD', 'Chile' => 'CL',
            'China' => 'CN', 'Colombia' => 'CO', 'Comoros' => 'KM', 'Congo (Congo-Brazzaville)' => 'CG', 'Costa Rica' => 'CR',
            'Croatia' => 'HR', 'Cuba' => 'CU', 'Cyprus' => 'CY', 'Czechia' => 'CZ', 'Democratic Republic of the Congo' => 'CD',
            'Denmark' => 'DK', 'Djibouti' => 'DJ', 'Dominica' => 'DM', 'Dominican Republic' => 'DO', 'Ecuador' => 'EC',
            'Egypt' => 'EG', 'El Salvador' => 'SV', 'Equatorial Guinea' => 'GQ', 'Eritrea' => 'ER', 'Estonia' => 'EE',
            'Eswatini' => 'SZ', 'Ethiopia' => 'ET', 'Fiji' => 'FJ', 'Finland' => 'FI', 'France' => 'FR',
            'Gabon' => 'GA', 'Gambia' => 'GM', 'Georgia' => 'GE', 'Germany' => 'DE', 'Ghana' => 'GH',
            'Greece' => 'GR', 'Grenada' => 'GD', 'Guatemala' => 'GT', 'Guinea' => 'GN', 'Guinea-Bissau' => 'GW',
            'Guyana' => 'GY', 'Haiti' => 'HT', 'Holy See' => 'VA', 'Honduras' => 'HN', 'Hungary' => 'HU',
            'Iceland' => 'IS', 'India' => 'IN', 'Indonesia' => 'ID', 'Iran' => 'IR', 'Iraq' => 'IQ',
            'Ireland' => 'IE', 'Israel' => 'IL', 'Italy' => 'IT', 'Jamaica' => 'JM', 'Japan' => 'JP',
            'Jordan' => 'JO', 'Kazakhstan' => 'KZ', 'Kenya' => 'KE', 'Kiribati' => 'KI', 'Kuwait' => 'KW',
            'Kyrgyzstan' => 'KG', 'Laos' => 'LA', 'Latvia' => 'LV', 'Lebanon' => 'LB', 'Lesotho' => 'LS',
            'Liberia' => 'LR', 'Libya' => 'LY', 'Liechtenstein' => 'LI', 'Lithuania' => 'LT', 'Luxembourg' => 'LU',
            'Madagascar' => 'MG', 'Malawi' => 'MW', 'Malaysia' => 'MY', 'Maldives' => 'MV', 'Mali' => 'ML',
            'Malta' => 'MT', 'Marshall Islands' => 'MH', 'Mauritania' => 'MR', 'Mauritius' => 'MU', 'Mexico' => 'MX',
            'Micronesia' => 'FM', 'Moldova' => 'MD', 'Monaco' => 'MC', 'Mongolia' => 'MN', 'Montenegro' => 'ME',
            'Morocco' => 'MA', 'Mozambique' => 'MZ', 'Myanmar' => 'MM', 'Namibia' => 'NA', 'Nauru' => 'NR',
            'Nepal' => 'NP', 'Netherlands' => 'NL', 'New Zealand' => 'NZ', 'Nicaragua' => 'NI', 'Niger' => 'NE',
            'Nigeria' => 'NG', 'North Korea' => 'KP', 'North Macedonia' => 'MK', 'Norway' => 'NO', 'Oman' => 'OM',
            'Pakistan' => 'PK', 'Palau' => 'PW', 'Palestine State' => 'PS', 'Panama' => 'PA', 'Papua New Guinea' => 'PG',
            'Paraguay' => 'PY', 'Peru' => 'PE', 'Philippines' => 'PH', 'Poland' => 'PL', 'Portugal' => 'PT',
            'Qatar' => 'QA', 'Romania' => 'RO', 'Russia' => 'RU', 'Rwanda' => 'RW', 'Saint Kitts and Nevis' => 'KN',
            'Saint Lucia' => 'LC', 'Saint Vincent and the Grenadines' => 'VC', 'Samoa' => 'WS', 'San Marino' => 'SM',
            'Sao Tome and Principe' => 'ST', 'Saudi Arabia' => 'SA', 'Senegal' => 'SN', 'Serbia' => 'RS', 'Seychelles' => 'SC',
            'Sierra Leone' => 'SL', 'Singapore' => 'SG', 'Slovakia' => 'SK', 'Slovenia' => 'SI', 'Solomon Islands' => 'SB',
            'Somalia' => 'SO', 'South Africa' => 'ZA', 'South Korea' => 'KR', 'South Sudan' => 'SS', 'Spain' => 'ES',
            'Sri Lanka' => 'LK', 'Sudan' => 'SD', 'Suriname' => 'SR', 'Sweden' => 'SE', 'Switzerland' => 'CH',
            'Syria' => 'SY', 'Tajikistan' => 'TJ', 'Tanzania' => 'TZ', 'Thailand' => 'TH', 'Timor-Leste' => 'TL',
            'Togo' => 'TG', 'Tonga' => 'TO', 'Trinidad and Tobago' => 'TT', 'Tunisia' => 'TN', 'Turkey' => 'TR',
            'Turkmenistan' => 'TM', 'Tuvalu' => 'TV', 'Uganda' => 'UG', 'Ukraine' => 'UA', 'United Arab Emirates' => 'AE',
            'United Kingdom' => 'GB', 'United States of America' => 'US', 'Uruguay' => 'UY', 'Uzbekistan' => 'UZ',
            'Vanuatu' => 'VU', 'Venezuela' => 'VE', 'Vietnam' => 'VN', 'Yemen' => 'YE', 'Zambia' => 'ZM', 'Zimbabwe' => 'ZW',
        ];

        // India first, then the rest alphabetically — it's the home
        // country for every company/customer this ERP has today.
        $rows   = [['name' => 'India', 'iso2' => 'IN', 'created_at' => $now, 'updated_at' => $now]];
        $others = $names;
        unset($others['India']);
        ksort($others);
        foreach ($others as $name => $iso2) {
            $rows[] = ['name' => $name, 'iso2' => $iso2, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            $this->db->table('countries')->insertBatch($chunk);
        }
    }

    public function down()
    {
        $this->forge->dropTable('countries', true);
    }
}
