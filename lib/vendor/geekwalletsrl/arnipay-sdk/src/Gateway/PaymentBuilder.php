<?php

namespace Arnipay\Gateway;

class PaymentBuilder
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $description = null;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * PaymentBuilder constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Set the amount for the payment.
     *
     * @param float $amount
     * @return self
     */
    public function amount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Set the title for the payment.
     *
     * @param string $title
     * @return self
     */
    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the description for the payment.
     *
     * @param string $description
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the allowed payment methods.
     *
     * @param array $methods
     * @return self
     */
    public function allow(array $methods): self
    {
        $this->options['payment_methods'] = $methods;
        return $this;
    }

    /**
     * Set the reference for the payment.
     *
     * @param string $reference
     * @return self
     */
    public function reference(string $reference): self
    {
        $this->options['reference'] = $reference;
        return $this;
    }

    /**
     * Set redirection URLs.
     *
     * @param string $successUrl
     * @param string|null $failureUrl
     * @return self
     */
    public function redirect(string $successUrl, ?string $failureUrl = null): self
    {
        $this->options['approved_redirection_url'] = $successUrl;
        if ($failureUrl) {
            $this->options['failed_redirection_url'] = $failureUrl;
        }
        return $this;
    }

    /**
     * Add any other option.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function with(string $key, $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Create the payment link.
     *
     * @return array
     */
    public function create(): array
    {
        $link = new PaymentLink($this->client);
        return $link->create($this->amount, $this->title, $this->description, $this->options);
    }

    /**
     * Create the payment link and return just the URL.
     *
     * @return string|null
     */
    public function createUrl(): ?string
    {
        $result = $this->create();
        return $result['url'] ?? null;
    }
}
