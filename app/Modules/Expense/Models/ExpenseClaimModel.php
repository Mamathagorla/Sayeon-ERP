<?php

namespace App\Modules\Expense\Models;

use CodeIgniter\Model;

/**
 * An employee's out-of-pocket expense claim.
 *
 * submitted -> approved -> reimbursed        (the happy path)
 * submitted -> rejected | cancelled          (terminal)
 * Reimbursement books a cash-out payment in the payments ledger (payment_id).
 */
class ExpenseClaimModel extends Model
{
    public const STATUSES = ['submitted', 'approved', 'rejected', 'reimbursed', 'cancelled'];

    private const MAX_AMOUNT = 9999999999.99;

    protected $table         = 'expense_claims';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'user_id', 'title', 'category', 'amount', 'expense_date', 'description', 'status',
        'decided_by', 'decided_at', 'decision_note', 'reimbursed_by', 'reimbursed_at', 'payment_method', 'payment_id',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'title'        => 'required|min_length[2]|max_length[150]',
        'category'     => 'required|max_length[100]',
        'amount'       => 'required|decimal|greater_than[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'expense_date' => 'required|valid_date',
        'description'  => 'permit_empty|max_length[2000]',
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('expense_claims.*, claimant.name as claimant_name, companies.name as company_name, decider.name as decider_name')
            ->join('users as claimant', 'claimant.id = expense_claims.user_id')
            ->join('companies', 'companies.id = expense_claims.company_id')
            ->join('users as decider', 'decider.id = expense_claims.decided_by', 'left');

        // array_key_exists: a scope of 0 (no company yet) must match nothing.
        if (array_key_exists('company_id', $filters) && $filters['company_id'] !== null && $filters['company_id'] !== '') {
            $builder->where('expense_claims.company_id', $filters['company_id']);
        }
        if (! empty($filters['user_id'])) {
            $builder->where('expense_claims.user_id', $filters['user_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('expense_claims.status', $filters['status']);
        }

        return $builder->orderBy('expense_claims.id', 'DESC');
    }
}
