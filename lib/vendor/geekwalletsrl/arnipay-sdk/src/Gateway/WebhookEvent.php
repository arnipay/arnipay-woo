<?php

namespace Arnipay\Gateway;

class WebhookEvent
{
    /**
     * @var array
     */
    protected $data;

    /**
     * WebhookEvent constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the event type.
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->data['event'] ?? null;
    }

    /**
     * Check if the event is a payment completion.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->getType() === 'payment.completed';
    }

    /**
     * Magic getter to access event properties.
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === 'type') {
            return $this->getType();
        }

        // Try to find property in 'data' sub-array first
        if (isset($this->data['data']) && is_array($this->data['data']) && array_key_exists($name, $this->data['data'])) {
            return $this->data['data'][$name];
        }

        // Fallback to top-level array
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return null;
    }

    /**
     * Get the raw event data.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
