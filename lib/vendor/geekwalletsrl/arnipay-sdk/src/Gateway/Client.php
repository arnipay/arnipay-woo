<?php

namespace Arnipay\Gateway;

/*
 * !!! ATENCIÓN: ARCHIVO MODIFICADO RESPECTO AL SDK ORIGINAL !!!
 * --------------------------------------------------------------
 * Este Client.php contiene parches específicos del plugin de WooCommerce:
 *   - Timeouts de cURL (CURLOPT_CONNECTTIMEOUT y CURLOPT_TIMEOUT) para
 *     que una API lenta no cuelgue el checkout.
 *   - Validación de respuesta vacía o JSON inválido.
 *
 * Si actualizás el SDK por Composer, REAPLICÁ estos cambios manualmente
 * o el plugin perderá robustez contra fallos del backend de arnipay.
 * Detalle completo en: lib/PATCHES.md
 * --------------------------------------------------------------
 */

use Arnipay\Exception\GatewayException;
use InvalidArgumentException;

class Client
{
    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $privateKey;

    /**
     * @var string
     */
    protected $baseUrl = 'https://arnipay.com.py/api/v1';

    /**
     * @var bool Whether to verify the SSL certificate
     */
    protected $verifySsl = true;

    /**
     * @var SignatureService
     */
    protected $signatureService;

    /**
     * Client constructor.
     *
     * @param string $clientId Your Commerce client ID
     * @param string $privateKey Your Commerce private key
     * @param string $baseUrl API base URL. Must use https:// if verifySsl is true.
     * @param bool $verifySsl Optional. Whether to verify the server's SSL certificate. Defaults to true. Set to false only for trusted local/testing environments.
     * @throws InvalidArgumentException If baseUrl is not HTTPS when verifySsl is true.
     */
    public function __construct(string $clientId, string $privateKey)
    {
        $this->clientId = $clientId;
        $this->privateKey = $privateKey;
        $this->signatureService = new SignatureService();
    }

    public function setBaseUrl(string $baseUrl, bool $verifySsl = true)
    {
        // Ensure HTTPS is used if verification is enabled
        if ($verifySsl && strpos($baseUrl, 'https://') !== 0) {
            throw new InvalidArgumentException('Base URL must use HTTPS when SSL verification is enabled.');
        }

        $this->baseUrl = $baseUrl;
        $this->verifySsl = $verifySsl;
    }

    /**
     * Execute a request to the API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws GatewayException
     */
    public function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();

        // Generate timestamp for the request
        $timestamp = time();

        // Build canonical string components for signature
        $requestUri = $this->signatureService->extractUri($url); // path + optional query, no scheme/host

        // Prepare JSON body and body hash
        $hasBodyMethod = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']);
        $rawBody = '';
        if ($hasBodyMethod && !empty($data)) {
            $rawBody = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $signature = $this->signatureService->generate(
            $method,
            $requestUri,
            (int) $timestamp,
            $this->clientId,
            $this->privateKey,
            $rawBody
        );

        $headers = [
            'Content-Type: application/json',
            'X-Client-ID: ' . $this->clientId,
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature,
        ];

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        // Set SSL verification options based on the setting
        if ($this->verifySsl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            // Disable SSL verification (use with caution!)
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($rawBody !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody);
        }

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);

        curl_close($curl);

        if ($curlErrno) {
            throw new GatewayException($curlError, 0);
        }

        if (!is_string($response) || $response === '') {
            throw new GatewayException('Empty API response', $statusCode ?: 0);
        }

        $responseData = json_decode($response, true);

        if (!is_array($responseData)) {
            throw new GatewayException('Invalid JSON response from API', $statusCode ?: 0);
        }

        if ($statusCode >= 400) {
            $message = $responseData['message'] ?? 'API request failed';
            $errors = $responseData['errors'] ?? null;

            throw new GatewayException($message, $statusCode, $errors);
        }

        return $responseData;
    }
}
