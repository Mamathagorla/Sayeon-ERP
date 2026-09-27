<?php

namespace App\Modules\Company\Models;

use CodeIgniter\Model;

class CompanyDirectorModel extends Model
{
    protected $table            = 'company_directors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'company_id', 'name', 'din', 'designation', 'email', 'phone',
        'appointed_date', 'resigned_date',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id' => 'required|integer',
        // A person's name — letters/spaces/apostrophes/hyphens/periods
        // only (covers "O'Brien", "Jean-Pierre", "Dr. Rao"), not digits
        // or other symbols. \p{L} (Unicode letter) rather than A-Za-z
        // so non-English names aren't rejected.
        'name'       => 'required|min_length[2]|max_length[150]|regex_match[/^[\p{L}\s.\'-]+$/u]',
        // DIN (Director Identification Number, India) is always exactly
        // 8 digits — the previous rule only checked "digits only" with
        // no length bound, so e.g. "123" passed as "valid".
        'din'        => 'permit_empty|regex_match[/^\d{8}$/]',
        'email'      => 'permit_empty|valid_email',
        'phone'      => 'permit_empty|regex_match[/^[0-9]{10}$/]',
    ];

    protected $validationMessages = [
        'name' => [
            'regex_match' => 'Name may only contain letters, spaces, apostrophes, hyphens and periods.',
        ],
        'din' => [
            'regex_match' => 'DIN must be exactly 8 digits.',
        ],
        'phone' => [
            'regex_match' => 'Phone number must be exactly 10 digits.',
        ],
    ];

    public function forCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll();
    }
}
