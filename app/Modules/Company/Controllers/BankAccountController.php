<?php

namespace App\Modules\Company\Controllers;

use App\Controllers\BaseController;
use App\Modules\Company\Models\BankAccountModel;

class BankAccountController extends BaseController
{
    protected BankAccountModel $bankAccountModel;

    public function __construct()
    {
        $this->bankAccountModel = new BankAccountModel();
    }

    public function store(int $companyId)
    {
        if ($this->outOfScope(['admin'], $companyId)) {
            return redirect()->to('/companies')->with('error', 'You can only manage your own company.');
        }

        $rules = [
            'bank_name'      => 'required|min_length[2]|max_length[150]',
            'account_holder' => 'required|min_length[2]|max_length[150]',
            // Digits only — regex_match, not numeric/integer, since those
            // both accept a leading +/- sign and numeric also accepts a
            // decimal point, neither of which belongs in an account number.
            'account_number' => 'required|regex_match[/^\d+$/]|min_length[4]|max_length[34]',
            // IFSC — always 11 chars: 4-letter bank code, a literal '0',
            // then 6 alphanumeric branch chars (e.g. HDFC0001234).
            'ifsc'           => 'permit_empty|regex_match[/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/]',
        ];
        $messages = [
            'account_number' => ['regex_match' => 'Account number must contain digits only.'],
            'ifsc'           => ['regex_match' => 'Enter a valid 11-character IFSC code (e.g. HDFC0001234).'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $accountNumber = $this->request->getPost('account_number');

        $data = [
            'company_id'             => $companyId,
            'bank_name'              => $this->request->getPost('bank_name'),
            'branch'                 => $this->request->getPost('branch'),
            'account_holder'         => $this->request->getPost('account_holder'),
            'account_number_cipher'  => $this->bankAccountModel->encryptAccountNumber($accountNumber),
            'account_number_last4'   => $this->bankAccountModel->lastFour($accountNumber),
            'ifsc'                   => $this->request->getPost('ifsc'),
            'authorized_signatories' => $this->request->getPost('authorized_signatories'),
            'status'                 => $this->request->getPost('status') ?: 'active',
        ];

        $file = $this->request->getFile('document');

        if ($file !== null && $file->isValid()) {
            $storage = service('fileStorage');
            $data['document_path'] = $storage->store($file, "bank-accounts/{$companyId}");
            $data['document_name'] = $file->getClientName();
        }

        $id = $this->bankAccountModel->insert($data);
        $this->logActivity('bank_account', 'create', $id, 'Added bank account: ' . $data['bank_name'] . ' ••••' . $data['account_number_last4']);

        return redirect()->to('/companies/' . $companyId)->with('success', 'Bank account added.');
    }

    public function edit(int $companyId, int $bankAccountId)
    {
        $account = $this->bankAccountModel->find($bankAccountId);

        if ($account === null || (int) $account['company_id'] !== $companyId || $this->outOfScope(['admin'], $companyId)) {
            return redirect()->to('/companies/' . $companyId)->with('error', 'Bank account not found.');
        }

        return view('App\Modules\Company\bank_accounts\edit', [
            'title'     => 'Edit Bank Account',
            'navActive' => 'companies',
            'companyId' => $companyId,
            'account'   => $account,
        ]);
    }

    public function update(int $companyId, int $bankAccountId)
    {
        $account = $this->bankAccountModel->find($bankAccountId);

        if ($account === null || (int) $account['company_id'] !== $companyId || $this->outOfScope(['admin'], $companyId)) {
            return redirect()->to('/companies/' . $companyId)->with('error', 'Bank account not found.');
        }

        $rules = [
            'bank_name'      => 'required|min_length[2]|max_length[150]',
            'account_holder' => 'required|min_length[2]|max_length[150]',
            // Optional on edit — the stored number is encrypted and only
            // ever shown as "•••• last4", so there's nothing to prefill;
            // leaving it blank means "keep the existing number". Digits
            // only when a new one is provided — see store() for why
            // regex_match instead of numeric/integer.
            'account_number' => 'permit_empty|regex_match[/^\d+$/]|min_length[4]|max_length[34]',
            'ifsc'           => 'permit_empty|regex_match[/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/]',
        ];
        $messages = [
            'account_number' => ['regex_match' => 'Account number must contain digits only.'],
            'ifsc'           => ['regex_match' => 'Enter a valid 11-character IFSC code (e.g. HDFC0001234).'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'bank_name'              => $this->request->getPost('bank_name'),
            'branch'                 => $this->request->getPost('branch'),
            'account_holder'         => $this->request->getPost('account_holder'),
            'ifsc'                   => $this->request->getPost('ifsc'),
            'authorized_signatories' => $this->request->getPost('authorized_signatories'),
            'status'                 => $this->request->getPost('status') ?: 'active',
        ];

        $accountNumber = $this->request->getPost('account_number');
        if ($accountNumber) {
            $data['account_number_cipher'] = $this->bankAccountModel->encryptAccountNumber($accountNumber);
            $data['account_number_last4']  = $this->bankAccountModel->lastFour($accountNumber);
        }

        $file = $this->request->getFile('document');

        if ($file !== null && $file->isValid()) {
            if ($account['document_path']) {
                service('fileStorage')->delete($account['document_path']);
            }
            $storage = service('fileStorage');
            $data['document_path'] = $storage->store($file, "bank-accounts/{$companyId}");
            $data['document_name'] = $file->getClientName();
        }

        $this->bankAccountModel->update($bankAccountId, $data);
        $this->logActivity('bank_account', 'update', $bankAccountId, 'Updated bank account: ' . $data['bank_name']);

        return redirect()->to('/companies/' . $companyId)->with('success', 'Bank account updated.');
    }

    public function delete(int $companyId, int $bankAccountId)
    {
        if ($this->outOfScope(['admin'], $companyId)) {
            return redirect()->to('/companies')->with('error', 'You can only manage your own company.');
        }

        $account = $this->bankAccountModel->find($bankAccountId);

        if ($account && (int) $account['company_id'] === $companyId) {
            if ($account['document_path']) {
                service('fileStorage')->delete($account['document_path']);
            }
            $this->bankAccountModel->delete($bankAccountId);
            $this->logActivity('bank_account', 'delete', $bankAccountId, 'Removed bank account #' . $bankAccountId);
        }

        return redirect()->to('/companies/' . $companyId)->with('success', 'Bank account removed.');
    }
}
