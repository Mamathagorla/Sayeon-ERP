<?php

namespace App\Modules\Support\Models;

use CodeIgniter\Model;

class SupportTicketModel extends Model
{
    public const PRIORITIES = ['low', 'medium', 'high'];
    public const STATUSES   = ['open', 'in_progress', 'resolved', 'closed'];

    public const CATEGORY_LABELS = [
        'general'         => 'General',
        'finance_billing' => 'Finance & Billing',
        'technical'       => 'Technical',
        'hr_payroll'      => 'HR & Payroll',
        'account_access'  => 'Account & Access',
    ];

    // Days from creation to due_date, per priority — same idea as
    // DocumentModel::CATEGORIES's fixed slug list, just driving a date
    // instead of a dropdown.
    public const SLA_DAYS = ['low' => 14, 'medium' => 7, 'high' => 3];

    protected $table         = 'support_tickets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_id', 'subject', 'description', 'category', 'priority', 'due_date', 'status',
        'raised_by', 'assigned_to', 'resolution_notes', 'resolved_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'subject'     => 'required|min_length[3]|max_length[150]',
        'description' => 'required|min_length[5]',
        'category'    => 'permit_empty|in_list[general,finance_billing,technical,hr_payroll,account_access]',
        'priority'    => 'permit_empty|in_list[low,medium,high]',
        'status'      => 'permit_empty|in_list[open,in_progress,resolved,closed]',
    ];

    public function filtered(array $filters = [])
    {
        $builder = $this->select('support_tickets.*, companies.name as company_name,
                raiser.name as raiser_name, raiser.email as raiser_email, assignee.name as assignee_name')
            ->join('companies', 'companies.id = support_tickets.company_id', 'left')
            ->join('users as raiser', 'raiser.id = support_tickets.raised_by')
            ->join('users as assignee', 'assignee.id = support_tickets.assigned_to', 'left');

        // array_key_exists (not empty()) — same convention as every
        // other module's filtered(): 0 must match zero rows, not fall
        // through to "no restriction".
        if (array_key_exists('company_id', $filters)) {
            $builder->where('support_tickets.company_id', $filters['company_id']);
        }
        if (! empty($filters['raised_by'])) {
            $builder->where('support_tickets.raised_by', $filters['raised_by']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('support_tickets.status', $filters['status'])
                : $builder->where('support_tickets.status', $filters['status']);
        }

        return $builder->orderBy('support_tickets.created_at', 'DESC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('support_tickets.id', $id)->first();
    }

    public function dueDateFor(string $priority): string
    {
        $days = self::SLA_DAYS[$priority] ?? self::SLA_DAYS['medium'];

        return date('Y-m-d', strtotime("+{$days} days"));
    }

    /**
     * Drives the "SLA" badge on the ticket detail view — "Due in N
     * Days" / "Overdue by N Days" once past due_date, or "Resolved"
     * once the ticket is out of the open/in_progress states.
     */
    public function slaStatus(array $ticket): array
    {
        if (empty($ticket['due_date'])) {
            return ['label' => '—', 'class' => 'secondary'];
        }

        if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
            return ['label' => 'Resolved', 'class' => 'success'];
        }

        $days = (int) ceil((strtotime($ticket['due_date']) - strtotime(date('Y-m-d'))) / 86400);

        if ($days < 0) {
            return ['label' => 'Overdue by ' . abs($days) . ' Day' . (abs($days) === 1 ? '' : 's'), 'class' => 'danger'];
        }
        if ($days === 0) {
            return ['label' => 'Due Today', 'class' => 'warning'];
        }

        return ['label' => 'Due in ' . $days . ' Day' . ($days === 1 ? '' : 's'), 'class' => 'warning'];
    }
}
