<?php

namespace App\Modules\Company\Models;

use CodeIgniter\Model;

/**
 * Account numbers are stored encrypted (app.baseURL's encryption.key,
 * see .env — this is the field that key was originally provisioned
 * for) and only the last 4 digits are kept in the clear, so list views
 * can render a masked "•••• 1234" without decrypting on every request.
 */
class BankAccountModel extends Model
{
    public const STATUSES = ['active', 'inactive', 'closed'];

    protected $table         = 'bank_accounts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'bank_name', 'branch', 'account_holder',
        'account_number_cipher', 'account_number_last4', 'ifsc',
        'authorized_signatories', 'status', 'document_path', 'document_name',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'company_id'     => 'required|integer',
        'bank_name'      => 'required|min_length[2]|max_length[150]',
        'account_holder' => 'required|min_length[2]|max_length[150]',
        'status'         => 'required|in_list[active,inactive,closed]',
    ];

    public function forCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId)->orderBy('bank_name', 'ASC')->findAll();
    }

    public function encryptAccountNumber(string $plainAccountNumber): string
    {
        $cipher = service('encrypter')->encrypt($plainAccountNumber);

        return base64_encode($cipher);
    }

    public function lastFour(string $plainAccountNumber): string
    {
        return substr($plainAccountNumber, -4);
    }

    public function decryptAccountNumber(array $bankAccount): string
    {
        return service('encrypter')->decrypt(base64_decode($bankAccount['account_number_cipher']));
    }
}
