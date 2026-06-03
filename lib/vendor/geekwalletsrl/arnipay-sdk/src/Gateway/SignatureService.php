<?php

namespace Arnipay\Gateway;

class SignatureService
{
    /**
     * Generate an HMAC signature using a shared canonical format.
     *
     * @param string $method The HTTP method used for the request.
     * @param string $uri The request URI (path and query string).
     * @param int $timestamp The Unix timestamp associated with the request.
     * @param string $identifier A stable identifier (e.g. client ID) tied to the key owner.
     * @param string $secret The shared secret/private key used to sign the message.
     * @param string $body The exact request body payload as a string.
     * @return string
     */
    public function generate(string $method, string $uri, int $timestamp, string $identifier, string $secret, string $body = ''): string
    {
        $canonical = $this->buildCanonicalString($method, $uri, $timestamp, $identifier, $body);

        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Build the canonical string representation used for signing.
     *
     * @param string $method
     * @param string $uri
     * @param int $timestamp
     * @param string $identifier
     * @param string $body
     * @return string
     */
    public function buildCanonicalString(string $method, string $uri, int $timestamp, string $identifier, string $body = ''): string
    {
        $bodyHash = base64_encode(hash('sha256', $body, true));

        return implode("\n", [
            strtoupper($method),
            $uri,
            (string) $timestamp,
            $identifier,
            $bodyHash,
        ]);
    }

    /**
     * Extract the path and query string portion of a URL.
     *
     * @param string $url
     * @return string
     */
    public function extractUri(string $url): string
    {
        $components = parse_url($url);

        if (!$components) {
            return '/';
        }

        $path = $components['path'] ?? '/';
        $query = isset($components['query']) && $components['query'] !== '' ? '?' . $components['query'] : '';

        return $path . $query;
    }
}
