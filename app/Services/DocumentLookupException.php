<?php

namespace App\Services;

use RuntimeException;

class DocumentLookupException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 503)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
