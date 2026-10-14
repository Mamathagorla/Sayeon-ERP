<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

class GoogleOAuthConnectionModel extends Model
{
    /** The one scope Google Meet creation needs (Calendar events, not the whole calendar). */
    public const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    /** Prefix on values encrypted at rest; anything without it is a legacy plain-text value. */
    private const ENC_PREFIX = 'enc1:';

    protected $table         = 'google_oauth_connections';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'google_email', 'access_token', 'refresh_token', 'token_expires_at', 'scope',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** The connection row with its tokens decrypted. A token that cannot be decrypted reads as missing. */
    public function forUser(int $userId): ?array
    {
        $row = $this->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        foreach (['access_token', 'refresh_token'] as $column) {
            $row[$column] = $this->reveal($row[$column] ?? null);
        }

        return $row;
    }

    /**
     * Whether a space-separated list of granted scopes lets us create events.
     * Google's consent screen lets the user untick individual permissions, so
     * a successful sign-in does not mean Calendar access was granted. The
     * broader calendar scope also covers events.
     */
    public static function grantsCalendar(?string $scopes): bool
    {
        $granted = preg_split('/\s+/', trim((string) $scopes), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array(self::CALENDAR_SCOPE, $granted, true)
            || in_array('https://www.googleapis.com/auth/calendar', $granted, true);
    }

    /** Connected with a refresh token AND the Calendar permission actually granted. */
    public static function isUsable(?array $connection): bool
    {
        return $connection !== null
            && ! empty($connection['refresh_token'])
            && self::grantsCalendar($connection['scope'] ?? null);
    }

    /**
     * Insert-or-update for one user. Passing the tokens of a different Google
     * account replaces the previous ones; an empty or missing refresh_token
     * never overwrites a stored one (Google only returns a refresh token on
     * consent, and a plain access-token refresh does not return one at all).
     * Tokens are encrypted before they are stored.
     */
    public function saveForUser(int $userId, array $data): void
    {
        if (empty($data['refresh_token'])) {
            unset($data['refresh_token']);
        }

        foreach (['access_token', 'refresh_token'] as $column) {
            if (isset($data[$column])) {
                $data[$column] = $this->conceal($data[$column]);
            }
        }

        $existing = $this->where('user_id', $userId)->first();

        if ($existing !== null) {
            $this->update($existing['id'], $data);
        } else {
            $this->insert(['user_id' => $userId] + $data);
        }
    }

    /**
     * The stored grant is unusable (revoked, or missing Calendar permission):
     * forget the access token and the granted scopes so the organizer is sent
     * through a fresh consent instead of the same failing token being retried.
     */
    public function markReconnectRequired(int $userId): void
    {
        $existing = $this->where('user_id', $userId)->first();

        if ($existing !== null) {
            $this->update($existing['id'], ['access_token' => null, 'token_expires_at' => null, 'scope' => '']);
        }
    }

    public function disconnectUser(int $userId): void
    {
        $this->where('user_id', $userId)->delete();
    }

    private function conceal(string $plain): string
    {
        try {
            return self::ENC_PREFIX . base64_encode(service('encrypter')->encrypt($plain));
        } catch (\Throwable $e) {
            // No usable encryption key: keep working, as before, rather than lose the connection.
            log_message('error', 'Google token could not be encrypted; stored unencrypted.');

            return $plain;
        }
    }

    private function reveal(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (! str_starts_with($stored, self::ENC_PREFIX)) {
            return $stored;
        }

        try {
            return service('encrypter')->decrypt(base64_decode(substr($stored, strlen(self::ENC_PREFIX)), true) ?: '');
        } catch (\Throwable $e) {
            log_message('error', 'Stored Google token could not be decrypted; reconnect required.');

            return null;
        }
    }
}
