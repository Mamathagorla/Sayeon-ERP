<?php

namespace App\Modules\Auth\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\AppSettingModel;
use App\Modules\Auth\Services\LocalizationService;
use DateTimeZone;

/**
 * Organization localization settings under Administration. Gated by the
 * setting.view / setting.edit permissions (Super Admin holds them through
 * the permission bypass, like every other administration screen).
 */
class LocalizationController extends BaseController
{
    protected AppSettingModel $settingModel;

    public function __construct()
    {
        $this->settingModel = new AppSettingModel();
    }

    public function index()
    {
        $service = LocalizationService::instance();

        return view('App\Modules\Auth\localization', [
            'title'      => 'Localization',
            'navActive'  => 'localization',
            'values'     => $service->org(),
            'countries'  => db_connect()->table('countries')->select('iso2, name')->orderBy('name', 'ASC')->get()->getResultArray(),
            'currencies' => AppSettingModel::CURRENCIES,
            'locales'    => AppSettingModel::LOCALES,
            'dateFormats' => AppSettingModel::DATE_FORMATS,
            'timeFormats' => AppSettingModel::TIME_FORMATS,
            'timezones'  => DateTimeZone::listIdentifiers(),
            'preview'    => [
                'date'     => erp_date(date('Y-m-d')),
                'time'     => erp_time('14:30:00'),
                'datetime' => erp_datetime(date('Y-m-d H:i:s')),
                'money'    => erp_money(1234567.5),
                'number'   => erp_number(1234567.5),
                'timezone' => erp_timezone(),
            ],
        ]);
    }

    /**
     * All six settings are validated first and written together only when
     * every value is valid — a half-saved localization profile is never left
     * behind.
     */
    public function save()
    {
        $input  = [];
        $errors = [];

        foreach (AppSettingModel::KEYS as $key) {
            $value = trim((string) $this->request->getPost($key));
            $error = AppSettingModel::validateSetting($key, $value);

            if ($error !== null) {
                $errors[] = $error;
            }
            $input[$key] = $value;
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', implode(' ', $errors));
        }

        $userId = (int) $this->currentUserId();

        $db = db_connect();
        $db->transStart();
        foreach ($input as $key => $value) {
            $this->settingModel->setValue($key, $value, $userId);
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Could not save the localization settings. Please try again.');
        }

        LocalizationService::reset();
        $this->logActivity('setting', 'update', null, 'Updated localization settings');

        return redirect()->to('/auth/localization')->with('success', 'Localization settings saved. Dates, times and amounts now use these settings.');
    }
}
