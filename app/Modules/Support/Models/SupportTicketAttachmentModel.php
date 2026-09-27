<?php

namespace App\Modules\Support\Models;

use CodeIgniter\Model;

class SupportTicketAttachmentModel extends Model
{
    protected $table         = 'support_ticket_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['message_id', 'file_path', 'original_name', 'file_size'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Attachments keyed by message_id, for the ticket detail view to
     * look up per message without an N+1 query per reply.
     */
    public function forMessages(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $grouped = [];
        foreach ($this->whereIn('message_id', $messageIds)->findAll() as $row) {
            $grouped[$row['message_id']][] = $row;
        }

        return $grouped;
    }
}
