<?php

namespace App\Modules\Meeting\Services\Providers;

/**
 * Every outbound call a meeting provider makes goes through here so that none
 * of them can block the request indefinitely: CodeIgniter's CURLRequest
 * defaults to no overall timeout (and a 150 second connect timeout). Each call
 * also logs its duration, never the URL query, headers or body (they carry
 * access tokens and client secrets).
 *
 * The HTTP client can be replaced with setHttpClient() so tests never touch
 * the network.
 */
trait BoundedHttp
{
    private const CONNECT_TIMEOUT = 5;
    private const TOTAL_TIMEOUT   = 15;

    private ?object $httpClient = null;

    public function setHttpClient(object $client): void
    {
        $this->httpClient = $client;
    }

    private function client(): object
    {
        return $this->httpClient ?? \Config\Services::curlrequest(['http_errors' => false]);
    }

    /**
     * @param string $label Short name of the call for the log (e.g. "zoom.create"), not the URL.
     */
    private function http(string $method, string $url, array $options, string $label)
    {
        $options += [
            'http_errors'     => false,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout'         => self::TOTAL_TIMEOUT,
        ];

        $started = microtime(true);

        try {
            $response = $this->client()->{$method}($url, $options);
        } catch (\Throwable $e) {
            log_message('error', sprintf('%s failed after %.2fs: %s', $label, microtime(true) - $started, $this->scrub($e->getMessage())));

            throw $e;
        }

        log_message('info', sprintf('%s HTTP %d in %.2fs', $label, $response->getStatusCode(), microtime(true) - $started));

        return $response;
    }

    /** cURL error text can echo the URL; drop any query string so tokens/ids never reach the log. */
    private function scrub(string $message): string
    {
        return preg_replace('/\?[^\s"\']*/', '?…', $message) ?? $message;
    }
}
