<?php

namespace App\Modules\Marketing\Models;

use CodeIgniter\Model;

class CampaignModel extends Model
{
    public const CHANNELS = ['google_ads', 'facebook', 'instagram', 'linkedin', 'email', 'whatsapp'];
    public const STATUSES = ['draft', 'active', 'paused', 'completed'];
    public const CHANNEL_LABELS = [
        'google_ads' => 'Google Ads',
        'facebook'   => 'Facebook',
        'instagram'  => 'Instagram',
        'linkedin'   => 'LinkedIn',
        'email'      => 'Email',
        'whatsapp'   => 'WhatsApp',
    ];

    protected $table         = 'campaigns';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'name', 'channel', 'budget', 'start_date', 'end_date',
        'leads', 'conversions', 'revenue', 'status', 'notes', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'  => 'required|integer',
        'name'        => 'required|min_length[2]|max_length[150]',
        'channel'     => 'required|in_list[google_ads,facebook,instagram,linkedin,email,whatsapp]',
        'budget'      => 'required|decimal|greater_than_equal_to[0]',
        'leads'       => 'permit_empty|integer|greater_than_equal_to[0]',
        'conversions' => 'permit_empty|integer|greater_than_equal_to[0]',
        'revenue'     => 'permit_empty|decimal|greater_than_equal_to[0]',
        'status'      => 'required|in_list[draft,active,paused,completed]',
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('campaigns.*, companies.name as company_name')
            ->join('companies', 'companies.id = campaigns.company_id');

        if (! empty($filters['company_id'])) {
            $builder->where('campaigns.company_id', $filters['company_id']);
        }
        if (! empty($filters['channel'])) {
            is_array($filters['channel'])
                ? $builder->whereIn('campaigns.channel', $filters['channel'])
                : $builder->where('campaigns.channel', $filters['channel']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('campaigns.status', $filters['status'])
                : $builder->where('campaigns.status', $filters['status']);
        }

        return $builder->orderBy('campaigns.created_at', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('campaigns.id', $id)->first();
    }

    /**
     * ROI and conversion rate are derived, not stored — they're always
     * computed from the current budget/revenue/leads/conversions rather
     * than risking a stale cached percentage.
     */
    public function roiPercent(array $campaign): ?float
    {
        if ((float) $campaign['budget'] <= 0) {
            return null;
        }

        return round((((float) $campaign['revenue'] - (float) $campaign['budget']) / (float) $campaign['budget']) * 100, 1);
    }

    public function conversionRatePercent(array $campaign): ?float
    {
        if ((int) $campaign['leads'] <= 0) {
            return null;
        }

        return round(((int) $campaign['conversions'] / (int) $campaign['leads']) * 100, 1);
    }
}
