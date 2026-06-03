<?php

namespace Arnipay\Gateway;

use Arnipay\Exception\GatewayException;

class PaymentLink
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * PaymentLink constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new payment link
     *
     * @param float $price The price of the item
     * @param string $title Title of the payment link
     * @param string|null $description Optional description
     * @param array $options Additional options
     * @return array The created payment link data
     * @throws GatewayException
     */
    public function create(float $price, string $title, ?string $description = null, array $options = []): array
    {
        $data = array_merge([
            'price' => $price,
            'title' => $title,
        ], $options);

        if ($description !== null) {
            $data['description'] = $description;
        }

        $response = $this->client->request('POST', '/payment', $data);

        return $response['data'] ?? [];
    }

    /**
     * Get a specific payment link by ID
     *
     * @param string $id Payment link ID
     * @return array Payment link data
     * @throws GatewayException
     */
    public function get(string $id): array
    {
        $response = $this->client->request('GET', "/payment/{$id}");

        return $response['data'] ?? [];
    }

    /**
     * Get a list of all payment links
     *
     * @return array List of payment links
     * @throws GatewayException
     */
    public function list(): array
    {
        $response = $this->client->request('GET', '/payment');

        return $response['data'] ?? [];
    }

    /**
     * Reverse a payment
     *
     * @param string $id Payment link ID
     * @param string|null $reason Optional reason for the reversal
     * @return array Response data
     * @throws GatewayException
     */
    public function reverse(string $id, ?string $reason = null): array
    {
        $data = [];
        if ($reason !== null) {
            $data['reason'] = $reason;
        }

        $response = $this->client->request('POST', "/payment/{$id}/reverse", $data);

        return $response['data'] ?? [];
    }
}
