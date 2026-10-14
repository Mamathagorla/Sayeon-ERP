<?php

namespace App\Modules\Auth\Models;

use CodeIgniter\Model;
use DateTimeZone;

/**
 * Organization-wide localization profile (see the CreateAppSettingsTable
 * migration). Values are validated against the lists below — never accepted
 * as free text — so only real ISO 4217 currency codes, IANA timezones,
 * supported locales and known formats can ever be stored.
 */
class AppSettingModel extends Model
{
    public const KEYS = ['country_iso2', 'currency_code', 'timezone', 'locale', 'date_format', 'time_format'];

    public const CURRENCIES = [
        'USD' => 'US Dollar', 'INR' => 'Indian Rupee', 'EUR' => 'Euro', 'GBP' => 'British Pound',
        'AUD' => 'Australian Dollar', 'CAD' => 'Canadian Dollar', 'SGD' => 'Singapore Dollar',
        'AED' => 'UAE Dirham', 'JPY' => 'Japanese Yen', 'CNY' => 'Chinese Yuan', 'CHF' => 'Swiss Franc',
        'SEK' => 'Swedish Krona', 'NZD' => 'New Zealand Dollar', 'ZAR' => 'South African Rand',
        'BRL' => 'Brazilian Real', 'MXN' => 'Mexican Peso', 'HKD' => 'Hong Kong Dollar',
        'MYR' => 'Malaysian Ringgit', 'THB' => 'Thai Baht', 'SAR' => 'Saudi Riyal',
    ];

    public const LOCALES = [
        'en_IN' => 'English (India)', 'en_US' => 'English (United States)', 'en_GB' => 'English (United Kingdom)',
        'en_AU' => 'English (Australia)', 'en_CA' => 'English (Canada)', 'en_SG' => 'English (Singapore)',
        'de_DE' => 'Deutsch (Deutschland)', 'fr_FR' => 'Français (France)', 'es_ES' => 'Español (España)',
        'ja_JP' => '日本語 (日本)',
    ];

    // Format string => label. The label is shown on the settings page next to
    // a live example, so the administrator sees exactly what will be displayed.
    public const DATE_FORMATS = [
        'd/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD',
        'd-m-Y' => 'DD-MM-YYYY', 'd M Y' => 'DD Mon YYYY', 'M j, Y' => 'Mon D, YYYY',
    ];

    public const TIME_FORMATS = ['12h' => '12-hour (1:30 PM)', '24h' => '24-hour (13:30)'];

    protected $table         = 'app_settings';
    protected $primaryKey    = 'setting_key';
    protected $useAutoIncrement = false;
    protected $returnType    = 'array';
    protected $allowedFields = ['setting_value', 'updated_at', 'updated_by'];
    protected $useTimestamps = false;

    /** @return array<string, string|null> */
    public function allSettings(): array
    {
        return array_column($this->findAll(), 'setting_value', 'setting_key');
    }

    /**
     * Validates and stores one setting. Returns an error message, or null on
     * success. The caller is responsible for validating the whole set first.
     */
    public function setValue(string $key, string $value, ?int $userId): ?string
    {
        if (! in_array($key, self::KEYS, true)) {
            return 'Unknown setting: ' . $key;
        }

        $error = self::validateSetting($key, $value);
        if ($error !== null) {
            return $error;
        }

        $exists = $this->find($key) !== null;
        $data   = ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $userId];

        if ($exists) {
            $this->update($key, $data);
        } else {
            $this->insert(['setting_key' => $key] + $data);
        }

        return null;
    }

    /**
     * Single source of truth for allowed values, shared by the settings page
     * and the per-user profile fields.
     */
    public static function validateSetting(string $key, string $value): ?string
    {
        return match ($key) {
            'country_iso2'  => preg_match('/^[A-Z]{2}$/', $value) && db_connect()->table('countries')->where('iso2', $value)->countAllResults() > 0
                ? null : 'Choose a country from the list.',
            'currency_code' => array_key_exists($value, self::CURRENCIES) ? null : 'Choose a currency from the list.',
            'timezone'      => self::isTimezone($value) ? null : 'Choose a valid timezone.',
            'locale'        => array_key_exists($value, self::LOCALES) ? null : 'Choose a locale from the list.',
            'date_format'   => array_key_exists($value, self::DATE_FORMATS) ? null : 'Choose a date format from the list.',
            'time_format'   => array_key_exists($value, self::TIME_FORMATS) ? null : 'Choose 12-hour or 24-hour time.',
            default         => 'Unknown setting: ' . $key,
        };
    }

    public static function isTimezone(string $value): bool
    {
        return in_array($value, DateTimeZone::listIdentifiers(), true);
    }
}
