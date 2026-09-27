<?php

namespace App\Modules\Company\Models;

use CodeIgniter\Model;

class CompanyModel extends Model
{
    protected $table            = 'companies';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields = [
        'name', 'slug', 'status', 'owner_id', 'country',
        'cin', 'gst', 'pan', 'registered_address', 'incorporation_date',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Real govt-issued identifier formats (India), not just "letters and
    // digits" — e.g. a 9-character string was previously accepted as a
    // "valid" GSTIN. permit_empty throughout: these fields are optional
    // (a company incorporated abroad may have none of them).
    private const GSTIN_PATTERN = '/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}[1-9A-Za-z]{1}Z[0-9A-Za-z]{1}$/';
    private const PAN_PATTERN   = '/^[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}$/';
    private const CIN_PATTERN   = '/^[LUlu][0-9]{5}[A-Za-z]{2}[0-9]{4}[A-Za-z]{3}[0-9]{6}$/';

    protected $validationRules = [
        'name'    => 'required|min_length[2]|max_length[150]',
        'country' => 'required|max_length[60]',
        'status'  => 'required|in_list[active,inactive]',
        'gst'     => 'permit_empty|regex_match[' . self::GSTIN_PATTERN . ']',
        'pan'     => 'permit_empty|regex_match[' . self::PAN_PATTERN . ']',
        'cin'     => 'permit_empty|regex_match[' . self::CIN_PATTERN . ']',
    ];

    protected $validationMessages = [
        'gst' => ['regex_match' => 'Enter a valid 15-character GSTIN (e.g. 27ABCDE1234F1Z5).'],
        'pan' => ['regex_match' => 'Enter a valid 10-character PAN (e.g. ABCDE1234F).'],
        'cin' => ['regex_match' => 'Enter a valid 21-character CIN (e.g. U74999MH2019PTC321001).'],
    ];

    /**
     * Companies with an owner name attached, for list views.
     */
    public function listWithOwner(): array
    {
        return $this->select('companies.*, users.name as owner_name')
            ->join('users', 'users.id = companies.owner_id', 'left')
            ->orderBy('companies.name', 'ASC')
            ->findAll();
    }

    public function withOwner(int $id): ?array
    {
        return $this->select('companies.*, users.name as owner_name')
            ->join('users', 'users.id = companies.owner_id', 'left')
            ->where('companies.id', $id)
            ->first();
    }

    public function optionsList(): array
    {
        return $this->select('id, name')->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }

    protected $beforeInsert = ['generateSlug'];
    protected $beforeUpdate = ['generateSlug'];

    protected function generateSlug(array $data): array
    {
        if (! empty($data['data']['name']) && empty($data['data']['slug'])) {
            $data['data']['slug'] = url_title($data['data']['name'], '-', true);
        }

        return $data;
    }
}
