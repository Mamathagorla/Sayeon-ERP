<?php

namespace App\Modules\Support\Models;

use CodeIgniter\Model;

class SupportTicketMessageModel extends Model
{
    protected $table         = 'support_ticket_messages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['ticket_id', 'user_id', 'message', 'is_internal_note'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'message' => 'required|min_length[1]',
    ];

    /**
     * $includeInternal gates agent-only notes out of the thread for a
     * raiser viewer — see SupportController::show()'s $isResolverViewer.
     */
    public function forTicket(int $ticketId, bool $includeInternal): array
    {
        $builder = $this->select('support_ticket_messages.*, users.name as author_name')
            ->join('users', 'users.id = support_ticket_messages.user_id')
            ->where('ticket_id', $ticketId);

        if (! $includeInternal) {
            $builder->where('is_internal_note', 0);
        }

        return $builder->orderBy('support_ticket_messages.created_at', 'ASC')->findAll();
    }
}
