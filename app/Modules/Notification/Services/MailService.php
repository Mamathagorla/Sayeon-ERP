<?php

namespace App\Modules\Notification\Services;

/**
 * The one place this app sends email through CodeIgniter's Email service.
 * Sender name and address come from the Email config (.env: email.fromEmail
 * and email.fromName), and SMTP settings come from .env as well, so nothing
 * is hard-coded here. Never throws: every outcome is returned as a status
 * string so callers can report it without failing the request that caused
 * the email (a meeting or participant is still saved when mail fails).
 */
class MailService
{
    public const SENT           = 'sent';
    public const NOT_CONFIGURED = 'not_configured';
    public const INVALID        = 'invalid_address';
    public const FAILED         = 'failed';

    /**
     * Upper bound, in seconds, for any single blocking socket operation during
     * a send. CodeIgniter's SMTPTimeout only covers the TCP connect and plain
     * reads; the STARTTLS handshake (stream_socket_enable_crypto) is bounded by
     * PHP's default_socket_timeout instead, which is 60 seconds, longer than the
     * 30 second request limit. It is lowered for the duration of one send.
     */
    private const SOCKET_TIMEOUT = 8;

    /** A failure that took at least this many seconds is treated as the SMTP server being unreachable. */
    private const SLOW_FAILURE = 3.0;

    /**
     * Set once a send fails in this request. SMTP being unreachable is the same
     * answer for every recipient, so the remaining sends are skipped instead of
     * each waiting out its own connection attempt (a meeting with N invitees
     * used to take N times the timeout).
     */
    private static bool $transportDown = false;

    /** True only when the SMTP host, login and sender address are all set. */
    public function isConfigured(): bool
    {
        $config = config('Email');

        return $config->SMTPHost !== '' && $config->SMTPUser !== '' && $config->SMTPPass !== '' && $config->fromEmail !== '';
    }

    /**
     * Sends one HTML email with a plain-text alternative to one recipient.
     * Each recipient gets their own message, so addresses are never shared.
     */
    public function send(string $to, string $subject, string $html, ?string $text = null): string
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return self::INVALID;
        }

        if (! $this->isConfigured()) {
            return self::NOT_CONFIGURED;
        }

        if (self::$transportDown) {
            return self::FAILED;
        }

        $config   = config('Email');
        $started  = microtime(true);
        $previous = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) self::SOCKET_TIMEOUT);

        try {
            $email = \Config\Services::email(null, false);
            $email->setFrom($config->fromEmail, $config->fromName);
            $email->setTo($to);
            $email->setSubject($this->cleanHeader($subject));
            $email->setMessage($html);

            if ($text !== null) {
                $email->setAltMessage($text);
            }

            if ($email->send()) {
                log_message('info', sprintf('Email sent in %.2fs.', microtime(true) - $started));

                return self::SENT;
            }

            // A slow failure is a connection or handshake problem that every
            // recipient would hit too; a fast one (a rejected address) is not.
            $elapsed = microtime(true) - $started;
            self::$transportDown = $elapsed >= self::SLOW_FAILURE;

            // The recipient address is deliberately not logged.
            log_message('error', sprintf(
                'Email send failed after %.2fs for subject "%s"%s.',
                $elapsed,
                $this->cleanHeader($subject),
                self::$transportDown ? '; further emails in this request are skipped' : ''
            ));

            return self::FAILED;
        } catch (\Throwable $e) {
            self::$transportDown = true;
            log_message('error', sprintf('Email send threw after %.2fs: %s', microtime(true) - $started, $e->getMessage()));

            return self::FAILED;
        } finally {
            ini_set('default_socket_timeout', (string) $previous);
        }
    }

    /** Subject lines must be one line: strip control characters and cap the length. */
    private function cleanHeader(string $value): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? ''), 0, 180);
    }
}
