<?php

namespace Arnipay;

use Arnipay\Gateway\Client;
use Arnipay\Gateway\PaymentBuilder;
use Arnipay\Gateway\Transaction;
use Arnipay\Gateway\WebhookHandler;

class Arnipay
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * Arnipay constructor.
     *
     * @param string $clientId
     * @param string $privateKey
     * @param bool $isSandbox Whether to use the sandbox environment (default: false)
     */
    public function __construct(string $clientId, string $privateKey, bool $isSandbox = false)
    {
        $this->client = new Client($clientId, $privateKey);

        if ($isSandbox) {
            // Set the sandbox URL automatically
            $this->client->setBaseUrl('https://sandbox.arnipay.com.py/api/v1', false);
        }
    }

    /**
     * Get a payment builder instance.
     *
     * @return PaymentBuilder
     */
    public function payment(): PaymentBuilder
    {
        return new PaymentBuilder($this->client);
    }

    /**
     * Get a webhook handler instance.
     *
     * @param string $secret
     * @return WebhookHandler
     */
    public function webhook(string $secret): WebhookHandler
    {
        return new WebhookHandler($secret);
    }

    /**
     * Get the underlying Client instance.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get a transaction instance.
     *
     * @return Transaction
     */
    public function transaction(): Transaction
    {
        return new Transaction($this->client);
    }

    /**
     * Retrieves a list of available payment methods.
     *
     * @return array
     * @throws Exception\GatewayException
     */
    public function getPaymentMethods(): array
    {
        $response = $this->client->request('GET', '/payment_methods');
        return $response['data'] ?? [];
    }
}
