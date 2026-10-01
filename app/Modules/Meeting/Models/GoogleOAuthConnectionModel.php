<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

class GoogleOAuthConnectionModel extends Model
{
    protected $table         = 'google_oauth_connections';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'google_email', 'access_token', 'refresh_token', 'token_expires_at', 'scope',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function forUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }

    /**
     * Insert-or-replace for one user — a re-connect (e.g. after
     * revoking access on Google's side, or just reconnecting) always
     * fully replaces whatever was stored before rather than merging,
     * since a stale refresh_token from a previous grant is useless once
     * a new one has been issued.
     */
    public function saveForUser(int $userId, array $data): void
    {
        $existing = $this->forUser($userId);

        if ($existing !== null) {
            $this->update($existing['id'], $data);
        } else {
            $this->insert(['user_id' => $userId] + $data);
        }
    }

    public function disconnectUser(int $userId): void
    {
        $this->where('user_id', $userId)->delete();
    }
}
