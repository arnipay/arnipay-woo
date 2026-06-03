<?php

namespace Arnipay\Gateway;

use Arnipay\Exception\GatewayException;

class Webhook
{
    /**
     * @var string
     */
    protected $webhookSecret;

    /**
     * @var SignatureService
     */
    protected $signatureService;

    /**
     * Webhook constructor.
     *
     * @param string $webhookSecret Your webhook secret key
     */
    public function __construct(string $webhookSecret)
    {
        $this->webhookSecret = $webhookSecret;
        $this->signatureService = new SignatureService();
    }

    /**
     * Capture webhook request details from the PHP runtime or provided overrides.
     *
     * @param array|null $server Optional server data; defaults to $_SERVER
     * @param string|null $payload Optional payload; defaults to php://input contents
     * @return array{method:string,requestUri:string,timestamp:string,clientId:string,payload:string,signature:string}
     */
    public function captureRequest(?array $server = null, ?string $payload = null): array
    {
        $server = $server ?? $_SERVER;

        if ($payload === null) {
            $input = file_get_contents('php://input');
            $payload = $input === false ? '' : $input;
        }

        $method = strtoupper($server['REQUEST_METHOD'] ?? 'POST');

        $rawUri = $server['REQUEST_URI'] ?? ($server['HTTP_X_ORIGINAL_URI'] ?? '/');
        $requestUri = $this->signatureService->extractUri($rawUri);

        $timestamp = (string) ($server['HTTP_X_TIMESTAMP'] ?? '');
        $clientId = (string) ($server['HTTP_X_CLIENT_ID'] ?? '');
        $signature = (string) ($server['HTTP_X_SIGNATURE'] ?? '');

        return [
            'method' => $method,
            'requestUri' => $requestUri,
            'timestamp' => $timestamp,
            'clientId' => $clientId,
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    /**
     * Convenience wrapper to validate and process an incoming webhook HTTP request.
     *
     * @param array|null $server Optional server data; defaults to $_SERVER
     * @param string|null $payload Optional payload; defaults to php://input contents
     * @return array Processed event data
     *
     * @throws GatewayException
     */
    public function handleRequest(?array $server = null, ?string $payload = null): array
    {
        $captured = $this->captureRequest($server, $payload);

        return $this->processEvent(
            $captured['method'],
            $captured['requestUri'],
            $captured['timestamp'],
            $captured['clientId'],
            $captured['payload'],
            $captured['signature']
        );
    }

    /**
     * Validate the webhook signature using the canonical string
     *
     * @param string $method HTTP method used for the webhook request
     * @param string $requestUri Request URI (path + optional query, no scheme/host)
     * @param string $timestamp Timestamp from X-Timestamp header
     * @param string $clientId Client identifier from X-Client-ID header
     * @param string $payload Raw request payload
     * @param string $signature Signature from X-Signature header
     * @return bool Whether the signature is valid
     */
    public function validateSignature(string $method, string $requestUri, string $timestamp, string $clientId, string $payload, string $signature): bool
    {
        $expectedSignature = $this->signatureService->generate(
            $method,
            $requestUri,
            (int) $timestamp,
            $clientId,
            $this->webhookSecret,
            $payload
        );

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook event
     *
     * @param string $method HTTP method used for the webhook request
     * @param string $requestUri Request URI (path + optional query)
     * @param string $timestamp Timestamp from X-Timestamp header
     * @param string $clientId Client identifier from X-Client-ID header
     * @param string $payload Raw request payload
     * @param string $signature Signature from X-Signature header
     * @return array Processed event data or empty array if invalid
     */
    public function processEvent(string $method, string $requestUri, string $timestamp, string $clientId, string $payload, string $signature): array
    {
        if (!$this->validateSignature($method, $requestUri, $timestamp, $clientId, $payload, $signature)) {
            throw new GatewayException('Invalid webhook signature', 401);
        }

        $event = json_decode($payload, true);

        if ($event === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new GatewayException('Invalid JSON payload', 400);
        }

        if (!is_array($event) || !isset($event['event']) || !isset($event['data'])) {
            throw new GatewayException('Invalid webhook payload', 422);
        }

        return $event;
    }
}
