<?php

namespace App\Modules\Notification\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table         = 'notifications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'type', 'title', 'message', 'related_module', 'related_id',
        'channel', 'is_read', 'scheduled_at', 'sent_at', 'created_at',
    ];

    // Only created_at exists on this table (no updated_at) — timestamps
    // are set explicitly in create() instead of via CI4's auto-timestamp
    // hooks, which expect both fields to be present.
    protected $useTimestamps = false;

    public function create(int $userId, string $type, string $title, string $message, ?string $module = null, ?int $relatedId = null): int
    {
        return (int) $this->insert([
            'user_id'        => $userId,
            'type'           => $type,
            'title'          => $title,
            'message'        => $message,
            'related_module' => $module,
            'related_id'     => $relatedId,
            'channel'        => 'in_app',
            'is_read'        => 0,
            'sent_at'        => date('Y-m-d H:i:s'),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Whether a notification of this exact type/subject has already
     * been generated for this user today — keeps the due-date sweep
     * (which re-runs on every page view) from creating duplicates.
     */
    public function existsToday(int $userId, string $type, ?string $module, ?int $relatedId): bool
    {
        return $this->where('user_id', $userId)
            ->where('type', $type)
            ->where('related_module', $module)
            ->where('related_id', $relatedId)
            ->where('DATE(created_at)', date('Y-m-d'))
            ->countAllResults() > 0;
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->where('user_id', $userId)->where('is_read', 0)->countAllResults();
    }

    public function recentFor(int $userId, int $limit = 8): array
    {
        return $this->where('user_id', $userId)->orderBy('created_at', 'DESC')->findAll($limit);
    }

    public function allFor(int $userId, bool $unreadOnly = false): array
    {
        $builder = $this->where('user_id', $userId);

        if ($unreadOnly) {
            $builder->where('is_read', 0);
        }

        return $builder->orderBy('created_at', 'DESC')->findAll();
    }

    public function markRead(int $id, int $userId): void
    {
        $this->where('id', $id)->where('user_id', $userId)->set(['is_read' => 1])->update();
    }

    public function markAllRead(int $userId): void
    {
        $this->where('user_id', $userId)->where('is_read', 0)->set(['is_read' => 1])->update();
    }
}
