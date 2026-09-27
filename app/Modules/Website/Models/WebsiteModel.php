<?php

namespace App\Modules\Website\Models;

use CodeIgniter\Model;

/**
 * Admin login and FTP passwords are stored encrypted (same
 * .env encryption key and pattern as Company\Models\BankAccountModel)
 * and are never included in list/show queries by default — only
 * fetched and decrypted on an explicit reveal() call.
 */
class WebsiteModel extends Model
{
    public const STATUSES = ['active', 'inactive', 'expired'];

    protected $table         = 'websites';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'domain', 'registrar', 'hosting_provider', 'server_ip',
        'dns_details', 'ssl_expiry', 'renewal_date', 'control_panel', 'control_panel_url',
        'git_repository', 'ftp_host', 'ftp_username', 'admin_login_username',
        'admin_login_password_cipher', 'ftp_password_cipher', 'status', 'notes', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'        => 'required|integer',
        'domain'            => 'required|min_length[3]|max_length[190]',
        'status'            => 'required|in_list[active,inactive,expired]',
        'server_ip'         => 'permit_empty|valid_ip',
        // git_repository is deliberately NOT validated with valid_url —
        // legitimate SSH-style git remotes (e.g. git@github.com:org/repo.git)
        // fail standard URL validation.
        'control_panel_url' => 'permit_empty|valid_url',
    ];

    protected $validationMessages = [
        'server_ip' => [
            'valid_ip' => 'Enter a valid IP address.',
        ],
        'control_panel_url' => [
            'valid_url' => 'Enter a valid URL.',
        ],
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('websites.*, companies.name as company_name')
            ->join('companies', 'companies.id = websites.company_id');

        if (! empty($filters['company_id'])) {
            $builder->where('websites.company_id', $filters['company_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('websites.status', $filters['status']);
        }

        return $builder->orderBy('websites.domain', 'ASC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('websites.id', $id)->first();
    }

    public function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        return base64_encode(service('encrypter')->encrypt($plain));
    }

    public function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }

        return service('encrypter')->decrypt(base64_decode($cipher));
    }

    /**
     * SSL certificates or renewal dates expiring within N days — feeds
     * the notification sweep, mirroring ComplianceItemModel::alerts().
     */
    public function expiringSoon(int $withinDays = 30): array
    {
        $cutoff = date('Y-m-d', strtotime("+{$withinDays} days"));

        return $this->where('status !=', 'expired')
            ->groupStart()
                ->where('ssl_expiry IS NOT NULL')->where('ssl_expiry <=', $cutoff)
                ->orGroupStart()
                    ->where('renewal_date IS NOT NULL')->where('renewal_date <=', $cutoff)
                ->groupEnd()
            ->groupEnd()
            ->findAll();
    }
}
