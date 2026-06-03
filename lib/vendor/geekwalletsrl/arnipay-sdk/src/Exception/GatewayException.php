<?php

namespace Arnipay\Exception;

use Exception;

class GatewayException extends Exception
{
    /**
     * @var array|null
     */
    protected $errors;

    /**
     * @var int
     */
    protected $statusCode;

    /**
     * GatewayException constructor.
     *
     * @param string $message
     * @param int $statusCode
     * @param array|null $errors
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, int $statusCode = 0, ?array $errors = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    /**
     * Get the validation errors if any
     *
     * @return array|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
