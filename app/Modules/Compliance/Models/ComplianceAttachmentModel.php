<?php

namespace App\Modules\Compliance\Models;

use CodeIgniter\Model;

class ComplianceAttachmentModel extends Model
{
    protected $table         = 'compliance_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['compliance_item_id', 'file_path', 'original_name', 'file_size', 'uploaded_by'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function forItem(int $itemId): array
    {
        return $this->select('compliance_attachments.*, users.name as uploaded_by_name')
            ->join('users', 'users.id = compliance_attachments.uploaded_by')
            ->where('compliance_item_id', $itemId)
            ->orderBy('compliance_attachments.created_at', 'DESC')
            ->findAll();
    }
}
