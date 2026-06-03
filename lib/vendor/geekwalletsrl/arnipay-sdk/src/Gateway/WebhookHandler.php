<?php

namespace Arnipay\Gateway;

class WebhookHandler
{
    /**
     * @var string
     */
    protected $secret;

    /**
     * WebhookHandler constructor.
     *
     * @param string $secret
     */
    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Process the webhook request and return an event object.
     *
     * @return WebhookEvent
     * @throws \Arnipay\Exception\GatewayException
     */
    public function process(): WebhookEvent
    {
        $webhook = new Webhook($this->secret);
        $data = $webhook->handleRequest();
        
        return new WebhookEvent($data);
    }

    /**
     * Handle the webhook request with a callback.
     *
     * @param callable $callback
     * @return mixed
     * @throws \Arnipay\Exception\GatewayException
     */
    public function handle(callable $callback)
    {
        $event = $this->process();
        return $callback($event);
    }
}
