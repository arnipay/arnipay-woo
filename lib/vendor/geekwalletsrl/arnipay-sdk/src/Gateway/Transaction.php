<?php

namespace Arnipay\Gateway;

use Arnipay\Exception\GatewayException;

class Transaction
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * Transaction constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * List transactions.
     *
     * @param array $filters Optional filters (e.g., ['link_payment_id' => 10, 'page' => 1])
     * @return array Paginated list of transactions
     * @throws GatewayException
     */
    public function list(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = '/transactions' . ($queryString ? '?' . $queryString : '');
        
        $response = $this->client->request('GET', $endpoint);

        return $response['data'] ?? [];
    }

    /**
     * Get a single transaction by ID.
     *
     * @param string $id Transaction ID
     * @return array Transaction details
     * @throws GatewayException
     */
    public function get(string $id): array
    {
        $response = $this->client->request('GET', "/transactions/{$id}");

        return $response['data'] ?? [];
    }

    /**
     * Reverse a transaction (refund).
     *
     * @param string $id Transaction ID to reverse
     * @param string|null $reason Optional reason for the reversal
     * @return array Response data containing status
     * @throws GatewayException
     */
    public function reverse(string $id, ?string $reason = null): array
    {
        $data = [];
        if ($reason !== null) {
            $data['reason'] = $reason;
        }

        $response = $this->client->request('POST', "/transactions/{$id}/reverse", $data);

        return $response['data'] ?? [];
    }
}
